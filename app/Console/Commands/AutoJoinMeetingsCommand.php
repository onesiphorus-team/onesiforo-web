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
use Illuminate\Support\Facades\Log;

#[\Illuminate\Console\Attributes\Description('Auto-join meetings for boxes configured with auto mode')]
#[\Illuminate\Console\Attributes\Signature('meetings:auto-join')]
class AutoJoinMeetingsCommand extends Command
{
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
                Log::warning('Auto-join skipped: OnesiBox offline', [
                    'onesi_box_id' => $box->id,
                    'meeting_instance_id' => $instance->id,
                    'exception_message' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
