<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use Illuminate\Console\Command;

class CleanupMeetingsCommand extends Command
{
    protected $signature = 'meetings:cleanup';

    protected $description = 'Close stale meeting instances and mark unresolved attendances';

    public function handle(): int
    {
        $staleInstances = MeetingInstance::query()
            ->nonTerminal()
            ->where('scheduled_at', '<', now()->subHours(4))
            ->with('attendances')
            ->get();

        foreach ($staleInstances as $instance) {
            /** @var MeetingAttendance $attendance */
            foreach ($instance->attendances as $attendance) {
                if (in_array($attendance->status, [MeetingAttendanceStatus::Pending, MeetingAttendanceStatus::Confirmed])) {
                    $attendance->update(['status' => MeetingAttendanceStatus::Skipped]);
                } elseif ($attendance->status === MeetingAttendanceStatus::Joined) {
                    $attendance->update([
                        'status' => MeetingAttendanceStatus::Completed,
                        'left_at' => now(),
                    ]);
                }
            }

            $instance->update(['status' => MeetingInstanceStatus::Completed]);
        }

        $this->info("Cleaned up {$staleInstances->count()} stale meeting instances.");

        return self::SUCCESS;
    }
}
