<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\MeetingAttendance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Illuminate\Console\Command;

class AutoJoinMeetingsCommand extends Command
{
    protected $signature = 'meetings:auto-join';

    protected $description = 'Auto-join meetings for boxes configured with auto mode';

    public function handle(OnesiBoxCommandService $commandService): int
    {
        $attendances = MeetingAttendance::query()
            ->where('join_mode', MeetingJoinMode::Auto)
            ->where('status', MeetingAttendanceStatus::Pending)
            ->whereHas('meetingInstance', function ($query): void {
                $query->whereBetween('scheduled_at', [
                    now()->addMinutes(3),
                    now()->addMinutes(7),
                ]);
            })
            ->with(['meetingInstance', 'onesiBox.recipient'])
            ->get();

        foreach ($attendances as $attendance) {
            /** @var OnesiBox $box */
            $box = $attendance->onesiBox;
            /** @var \App\Models\MeetingInstance $instance */
            $instance = $attendance->meetingInstance;
            $participantName = $box->recipient->full_name ?? $box->name;

            try {
                $commandService->sendZoomUrlCommand($box, $instance->zoom_url, $participantName);

                $attendance->update([
                    'status' => MeetingAttendanceStatus::Joined,
                    'joined_at' => now(),
                ]);
            } catch (OnesiBoxOfflineException $e) {
                $this->warn("OnesiBox {$box->name} is offline, skipping auto-join: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
