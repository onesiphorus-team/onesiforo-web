<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneScreenshotsCommand extends Command
{
    protected $signature = 'onesibox:prune-screenshots {--sweep-orphans}';

    protected $description = 'Apply rollup retention (top 10 + 1 per hour within 24h) to appliance screenshots';

    public function handle(): int
    {
        $startedAt = microtime(true);

        if ($this->option('sweep-orphans')) {
            $orphans = $this->sweepOrphans();
            $this->info("Orphan sweep: {$orphans} files removed.");

            return self::SUCCESS;
        }

        $stats = ['boxes' => 0, 'older_24h' => 0, 'rollup' => 0];

        OnesiBox::query()->chunkById(100, function (Collection $boxes) use (&$stats): void {
            foreach ($boxes as $box) {
                $stats['boxes']++;
                $stats['older_24h'] += $this->deleteOlderThan24h($box);
                $stats['rollup'] += $this->rollupBeyondTop10($box);
            }
        });

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        Log::info('prune-screenshots completed', [
            'boxes' => $stats['boxes'],
            'deleted_total' => $stats['older_24h'] + $stats['rollup'],
            'older_24h' => $stats['older_24h'],
            'rollup' => $stats['rollup'],
            'duration_ms' => $durationMs,
        ]);

        $this->info(sprintf(
            'Pruned screenshots: boxes=%d, older_24h=%d, rollup=%d, duration_ms=%d',
            $stats['boxes'], $stats['older_24h'], $stats['rollup'], $durationMs
        ));

        return self::SUCCESS;
    }

    private function deleteOlderThan24h(OnesiBox $box): int
    {
        $count = 0;

        ApplianceScreenshot::query()
            ->where('onesi_box_id', $box->id)
            ->where('captured_at', '<', now()->subHours(24))
            ->chunkById(500, function (Collection $stale) use (&$count): void {
                foreach ($stale as $screenshot) {
                    $screenshot->delete();
                    $count++;
                }
            });

        return $count;
    }

    private function rollupBeyondTop10(OnesiBox $box): int
    {
        $all = ApplianceScreenshot::query()
            ->where('onesi_box_id', $box->id)
            ->latest('captured_at')
            ->get(['id', 'captured_at', 'storage_path']);

        if ($all->count() <= 10) {
            return 0;
        }

        $top10 = $all->take(10);
        $rest = $all->slice(10);

        $keepIds = $top10->pluck('id')->all();

        $top10Buckets = $top10
            ->map(fn (ApplianceScreenshot $s) => $s->captured_at->format('Y-m-d H:00'))
            ->unique()
            ->all();

        $byHour = $rest->groupBy(fn (ApplianceScreenshot $s) => $s->captured_at->format('Y-m-d H:00'));
        foreach ($byHour as $bucketKey => $bucket) {
            if (in_array($bucketKey, $top10Buckets, true)) {
                continue; // bucket already represented by a top-10 record
            }
            $keepIds[] = $bucket->first()->id; // most recent (already sorted desc)
        }

        $toDelete = ApplianceScreenshot::query()
            ->where('onesi_box_id', $box->id)
            ->whereNotIn('id', $keepIds)
            ->get();

        $count = $toDelete->count();
        $toDelete->each->delete();

        return $count;
    }

    private function sweepOrphans(): int
    {
        $disk = Storage::disk('local');
        $base = 'onesi-boxes';

        if (! $disk->exists($base)) {
            return 0;
        }

        $removed = 0;
        foreach ($disk->directories($base) as $boxDir) {
            $screenshotsDir = "{$boxDir}/screenshots";
            if (! $disk->exists($screenshotsDir)) {
                continue;
            }
            foreach (array_chunk($disk->files($screenshotsDir), 500) as $chunk) {
                $existing = ApplianceScreenshot::query()
                    ->whereIn('storage_path', $chunk)
                    ->pluck('storage_path')
                    ->all();
                $orphans = array_diff($chunk, $existing);
                foreach ($orphans as $path) {
                    $disk->delete($path);
                    $removed++;
                }
            }
        }

        if ($removed > 0) {
            Log::warning("prune-screenshots orphan sweep removed {$removed} files");
        }

        return $removed;
    }
}
