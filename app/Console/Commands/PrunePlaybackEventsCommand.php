<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PlaybackEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

class PrunePlaybackEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-playback-events {--days=30 : Number of days to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete playback events older than the specified retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Date::now()->subDays($days);

        $count = PlaybackEvent::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        if ($count > 0) {
            Log::info("Pruned {$count} playback event(s) older than {$days} days.");
            $this->info("Pruned {$count} playback event(s) older than {$days} days.");
        } else {
            $this->info('No old playback events to prune.');
        }

        return Command::SUCCESS;
    }
}
