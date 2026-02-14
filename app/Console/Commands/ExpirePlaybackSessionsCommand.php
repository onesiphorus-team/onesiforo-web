<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlaybackSessionStatus;
use App\Models\PlaybackSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

class ExpirePlaybackSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire playback sessions that have exceeded their duration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Date::now();

        $expiredSessions = PlaybackSession::query()
            ->active()
            ->whereRaw('DATE_ADD(started_at, INTERVAL duration_minutes MINUTE) < ?', [$now])
            ->get();

        $count = $expiredSessions->count();

        foreach ($expiredSessions as $session) {
            $session->update([
                'status' => PlaybackSessionStatus::Completed,
                'ended_at' => $now,
            ]);
        }

        if ($count > 0) {
            Log::info("Expired {$count} playback session(s).");
            $this->info("Expired {$count} playback session(s).");
        } else {
            $this->info('No expired sessions found.');
        }

        return Command::SUCCESS;
    }
}
