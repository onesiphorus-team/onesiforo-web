<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OnesiBox;
use App\Models\OnesiBoxLogSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * Applies retention to collected log snapshots.
 *
 * Two independent caps: an absolute age, and a per-box row count so a box
 * collecting every few minutes cannot monopolise the table before the age
 * cutoff kicks in.
 */
#[\Illuminate\Console\Attributes\Description('Delete OnesiBox log snapshots beyond the retention window or the per-box cap')]
#[\Illuminate\Console\Attributes\Signature('onesibox:prune-log-snapshots {--days= : Days to retain (defaults to config)} {--keep= : Maximum snapshots retained per box (defaults to config)}')]
class PruneOnesiBoxLogSnapshotsCommand extends Command
{
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('onesiforo.log_snapshots.retention_days'));
        $keep = (int) ($this->option('keep') ?? config('onesiforo.log_snapshots.max_per_box'));

        $expired = OnesiBoxLogSnapshot::query()
            ->where('captured_at', '<', Date::now()->subDays($days))
            ->delete();

        $trimmed = $this->trimPerBox($keep);

        $summary = sprintf(
            'prune-log-snapshots: expired=%d, trimmed=%d, days=%d, keep=%d',
            $expired, $trimmed, $days, $keep
        );

        if ($expired + $trimmed > 0) {
            Log::info($summary);
        }

        $this->info($summary);

        return self::SUCCESS;
    }

    private function trimPerBox(int $keep): int
    {
        $deleted = 0;

        OnesiBox::query()
            ->select('id')
            ->chunkById(100, function (Collection $boxes) use ($keep, &$deleted): void {
                foreach ($boxes as $box) {
                    $surviving = OnesiBoxLogSnapshot::query()
                        ->where('onesi_box_id', $box->id)
                        ->latestFirst()
                        ->limit($keep)
                        ->pluck('id');

                    if ($surviving->count() < $keep) {
                        continue;
                    }

                    $deleted += OnesiBoxLogSnapshot::query()
                        ->where('onesi_box_id', $box->id)
                        ->whereNotIn('id', $surviving)
                        ->delete();
                }
            });

        return $deleted;
    }
}
