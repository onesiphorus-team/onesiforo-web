<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\LogSnapshotSource;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\Command as OnesiBoxCommand;
use App\Models\OnesiBox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * Periodically asks every reachable OnesiBox for its latest application log.
 *
 * The scheduler runs this often; the per-box `log_collection_interval_minutes`
 * decides who is actually due, so a single cron cadence serves boxes with
 * different collection intervals. The appliance answers by acknowledging the
 * command, and the acknowledgement pipeline stores the snapshot.
 */
#[\Illuminate\Console\Attributes\Description('Request the application log from every OnesiBox that is due for a periodic collection')]
#[\Illuminate\Console\Attributes\Signature('onesibox:collect-logs {--force : Ignore the per-box interval and request logs from every online box} {--box= : Restrict the collection to a single OnesiBox id}')]
class CollectOnesiBoxLogsCommand extends Command
{
    /**
     * Priority of scheduled log requests (1 = highest).
     *
     * Kept below user-driven commands so a background collection never delays
     * playback or meeting commands sitting in the same queue.
     */
    private const int PRIORITY = 4;

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $stats = ['requested' => 0, 'offline' => 0, 'not_due' => 0, 'already_pending' => 0];

        $boxes = OnesiBox::query()
            ->where('is_active', true)
            ->where('log_collection_enabled', true)
            ->when($this->option('box'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        foreach ($boxes as $box) {
            if (! $box->isOnline()) {
                $stats['offline']++;

                continue;
            }

            if (! $force && ! $box->isLogCollectionDue()) {
                $stats['not_due']++;

                continue;
            }

            if ($this->hasPendingLogRequest($box)) {
                $stats['already_pending']++;

                continue;
            }

            $this->requestLogs($box);
            $stats['requested']++;
        }

        $summary = sprintf(
            'collect-logs: requested=%d, offline=%d, not_due=%d, already_pending=%d',
            $stats['requested'], $stats['offline'], $stats['not_due'], $stats['already_pending']
        );

        if ($stats['requested'] > 0) {
            Log::info($summary);
        }

        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * A box that has not answered the previous request yet must not be asked
     * again: the pending command still expires on its own.
     */
    private function hasPendingLogRequest(OnesiBox $box): bool
    {
        return $box->commands()
            ->pending()
            ->where('type', CommandType::GetLogs)
            ->exists();
    }

    private function requestLogs(OnesiBox $box): void
    {
        $command = OnesiBoxCommand::query()->create([
            'onesi_box_id' => $box->id,
            'type' => CommandType::GetLogs,
            'payload' => [
                'lines' => $box->log_collection_lines,
                'source' => LogSnapshotSource::Scheduled->value,
            ],
            'status' => CommandStatus::Pending,
            'priority' => self::PRIORITY,
        ]);

        dispatch(new SendOnesiBoxCommand($command));

        $box->update(['last_log_requested_at' => Date::now()]);
    }
}
