<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Moves old activity log entries out of the database and into a CSV file.
 *
 * The order is what makes it safe: the file is written and checked first, and
 * rows are deleted only afterwards, limited to the ones that were exported. A
 * failure at any point before the delete leaves the table untouched; a failure
 * during the delete leaves rows that the next run exports again, so an entry
 * can end up in two files but never in none.
 *
 * Values are exported as stored: timestamps are in the application timezone
 * (UTC) and JSON columns are raw JSON.
 */
#[Description('Export activity log entries older than the retention window to CSV, then delete them')]
#[Signature('activity-log:archive
    {--months= : Months of activity kept in the database (defaults to config)}
    {--disk= : Filesystem disk the CSV is written to (defaults to config)}
    {--path= : Directory on the disk (defaults to config)}
    {--dry-run : Report what would be archived without writing or deleting anything}')]
class ArchiveActivityLogCommand extends Command
{
    private const int CHUNK_SIZE = 1000;

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?? config('onesiforo.activity_log.retention_months'));

        // A zero or negative window would archive the whole table, which is
        // far more likely a typo in the environment than an intention.
        if ($months < 1) {
            $this->error('activity-log:archive: the retention must be at least 1 month.');

            return self::FAILURE;
        }

        $cutoff = Date::now()->subMonthsNoOverflow($months);
        $lastId = $this->expired($cutoff)->max('id');

        if ($lastId === null) {
            $this->info("activity-log:archive: nothing older than {$months} month(s) to archive.");

            return self::SUCCESS;
        }

        $lastId = (int) $lastId;

        if ($this->option('dry-run')) {
            $count = $this->expired($cutoff, $lastId)->count();
            $this->info("activity-log:archive: {$count} entries older than {$cutoff->toDateString()} would be archived (dry run).");

            return self::SUCCESS;
        }

        $disk = (string) ($this->option('disk') ?? config('onesiforo.activity_log.archive_disk'));
        $directory = trim((string) ($this->option('path') ?? config('onesiforo.activity_log.archive_path')), '/');
        $file = ltrim($directory.'/'.$this->fileName($cutoff), '/');

        try {
            $exported = $this->export($cutoff, $lastId, $disk, $file);
        } catch (Throwable $exception) {
            report($exception);
            Log::error('activity-log:archive: export failed, nothing was deleted.', ['error' => $exception->getMessage()]);
            $this->error('activity-log:archive: export failed, nothing was deleted: '.$exception->getMessage());

            return self::FAILURE;
        }

        // The delete selects rows again instead of replaying the exported
        // ids, so it only runs if that selection is still exactly the export.
        $pending = $this->expired($cutoff, $lastId)->count();

        if ($pending !== $exported) {
            $message = "activity-log:archive: {$exported} entries were exported to {$disk}:{$file} but {$pending} now match; nothing was deleted.";
            Log::error($message);
            $this->error($message);

            return self::FAILURE;
        }

        $deleted = $this->deleteExported($cutoff, $lastId);

        $summary = sprintf(
            'activity-log:archive: exported=%d, deleted=%d, older_than=%s, file=%s:%s',
            $exported, $deleted, $cutoff->toDateString(), $disk, $file
        );

        if ($deleted !== $exported) {
            Log::error($summary.' (counts differ)');
            $this->error($summary.' (counts differ)');

            return self::FAILURE;
        }

        Log::info($summary);
        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * Entries past the cutoff, optionally frozen at the highest id seen when
     * the run started so the export and the delete cover the same rows.
     */
    private function expired(CarbonInterface $cutoff, ?int $lastId = null): Builder
    {
        return Activity::query()
            ->toBase()
            ->where('created_at', '<', $cutoff)
            ->when($lastId !== null, fn (Builder $query) => $query->where('id', '<=', $lastId));
    }

    /**
     * Writes the CSV to a local temporary file, copies it to the disk and
     * confirms the copy is complete.
     *
     * @return int number of entries written
     */
    private function export(CarbonInterface $cutoff, int $lastId, string $disk, string $file): int
    {
        $handle = tmpfile();

        throw_if($handle === false, RuntimeException::class, 'Unable to create a temporary file.');

        try {
            $activity = new Activity;
            $columns = Schema::connection($activity->getConnectionName())->getColumnListing($activity->getTable());
            $exported = 0;
            $bytes = $this->writeRow($handle, $columns);

            foreach ($this->expired($cutoff, $lastId)->select($columns)->lazyById(self::CHUNK_SIZE) as $row) {
                $bytes += $this->writeRow($handle, array_map(
                    fn (string $column): mixed => $row->{$column},
                    $columns,
                ));
                $exported++;
            }

            throw_unless(fflush($handle), RuntimeException::class, 'Unable to finish writing the temporary file.');

            // A full disk can cut a row short without fputcsv() reporting it.
            throw_unless((fstat($handle)['size'] ?? null) === $bytes, RuntimeException::class, 'The temporary file is shorter than what was written.');

            throw_unless(rewind($handle), RuntimeException::class, 'Unable to reread the temporary file.');

            $storage = Storage::disk($disk);

            throw_if($storage->exists($file), RuntimeException::class, "The archive file {$file} already exists.");
            throw_unless($storage->writeStream($file, $handle), RuntimeException::class, "Unable to write {$file} to the {$disk} disk.");

            if ($storage->size($file) !== $bytes) {
                // Do not leave a truncated file that looks like a good archive.
                $storage->delete($file);

                throw new RuntimeException("The archive file {$file} on the {$disk} disk was incomplete and has been removed.");
            }

            return $exported;
        } finally {
            // Closing a tmpfile() handle also removes the file. Some disks
            // close the stream themselves once it has been written.
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     * @return int bytes written
     */
    private function writeRow($handle, array $row): int
    {
        // An empty escape character gives RFC 4180 output: quotes are doubled
        // and nothing else is special, so JSON columns survive a round trip.
        $bytes = fputcsv($handle, $row, escape: '');

        throw_if($bytes === false, RuntimeException::class, 'Unable to write to the temporary file.');

        return $bytes;
    }

    private function deleteExported(CarbonInterface $cutoff, int $lastId): int
    {
        $deleted = 0;

        do {
            $ids = $this->expired($cutoff, $lastId)->orderBy('id')->limit(self::CHUNK_SIZE)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $removed = Activity::query()->toBase()->whereIn('id', $ids)->delete();
            $deleted += $removed;
        } while ($removed > 0);

        return $deleted;
    }

    private function fileName(CarbonInterface $cutoff): string
    {
        return sprintf(
            'activity-log_before-%s_archived-%s.csv',
            $cutoff->format('Y-m-d_His'),
            Date::now()->format('Y-m-d_His'),
        );
    }
}
