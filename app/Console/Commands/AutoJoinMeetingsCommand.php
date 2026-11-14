<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\OnesiBoxStatus;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\MeetingAttendance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Connects the boxes set to "auto" to their congregation meeting.
 *
 * The join starts JOIN_MINUTES_BEFORE minutes before the meeting. A box that is off or
 * offline at that time is tried again every minute (this command runs every minute), until
 * RETRY_MINUTES_AFTER_START minutes after the start: a box turned on late still joins.
 */
#[\Illuminate\Console\Attributes\Description('Auto-join meetings for boxes configured with auto mode')]
#[\Illuminate\Console\Attributes\Signature('meetings:auto-join')]
class AutoJoinMeetingsCommand extends Command
{
    public const int JOIN_MINUTES_BEFORE = 15;

    public const int RETRY_MINUTES_AFTER_START = 90;

    public function handle(OnesiBoxCommandService $commandService): int
    {
        $attendances = MeetingAttendance::query()
            ->where('join_mode', MeetingJoinMode::Auto)
            ->where('status', MeetingAttendanceStatus::Pending)
            ->whereHas('meetingInstance', function ($query): void {
                $query->whereBetween('scheduled_at', [
                    now()->subMinutes(self::RETRY_MINUTES_AFTER_START),
                    now()->addMinutes(self::JOIN_MINUTES_BEFORE),
                ]);
            })
            ->with(['meetingInstance', 'onesiBox.recipient'])
            ->get();

        foreach ($attendances as $attendance) {
            /** @var OnesiBox $box */
            $box = $attendance->onesiBox;
            /** @var \App\Models\MeetingInstance $instance */
            $instance = $attendance->meetingInstance;

            // Off or offline: tried again at the next run, without filling the log every minute.
            if (! $box->isOnline()) {
                continue;
            }

            // Already in the meeting (e.g. connected by hand): nothing to send.
            if ($box->status === OnesiBoxStatus::Calling && $box->current_meeting_url === $instance->zoom_url) {
                $attendance->update(['status' => MeetingAttendanceStatus::Joined, 'joined_at' => now()]);

                continue;
            }

            $participantName = $box->recipient->full_name ?? $box->name;

            try {
                $commandService->sendZoomUrlCommand($box, $instance->zoom_url, $participantName);

                $attendance->update([
                    'status' => MeetingAttendanceStatus::Joined,
                    'joined_at' => now(),
                ]);
            } catch (OnesiBoxOfflineException $e) {
                // The box went offline between the check above and the command.
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
