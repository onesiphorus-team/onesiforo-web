<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CommandStatus;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\OnesiBoxStatus;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\Command as BoxCommand;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Connects the boxes set to "auto" to their congregation meeting.
 *
 * The join starts JOIN_MINUTES_BEFORE minutes before the meeting. This command runs every
 * minute until RETRY_MINUTES_AFTER_START minutes after the start, so a box turned on late
 * still joins:
 * - a box that is off or offline is tried again at the next run;
 * - the attendance stays pending until the box reports being in the meeting: sending the
 *   command is not joining;
 * - while a join is waiting for the box no other is queued; one that expired or failed is
 *   sent again;
 * - a join the box executed (or someone cancelled) is never sent again: if the box is not in
 *   the meeting after that, a person took it out, and it must not be pulled back in a loop.
 */
#[\Illuminate\Console\Attributes\Description('Auto-join meetings for boxes configured with auto mode')]
#[\Illuminate\Console\Attributes\Signature('meetings:auto-join')]
class AutoJoinMeetingsCommand extends Command
{
    public const int JOIN_MINUTES_BEFORE = 15;

    public const int RETRY_MINUTES_AFTER_START = 90;

    public const int MINUTES_BETWEEN_FAILED_ATTEMPTS = 5;

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
            /** @var MeetingInstance $instance */
            $instance = $attendance->meetingInstance;

            // Off or offline: tried again at the next run, without filling the log every minute.
            if (! $box->isOnline()) {
                continue;
            }

            $lastJoin = $attendance->latestJoinCommand();

            if ($this->isInTheMeeting($box, $instance, $lastJoin)) {
                $attendance->update(['status' => MeetingAttendanceStatus::Joined, 'joined_at' => now()]);

                continue;
            }

            if ($lastJoin instanceof BoxCommand && ! $this->shouldSendAgain($lastJoin)) {
                continue;
            }

            $participantName = $box->recipient->full_name ?? $box->name;

            try {
                $commandService->sendZoomUrlCommand($box, $instance->zoom_url, $participantName);
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

    /**
     * In the meeting room, or in a call right after executing the join of this meeting (the box
     * may report the Zoom URL in a slightly different form). Also true for a box connected by hand.
     */
    private function isInTheMeeting(OnesiBox $box, MeetingInstance $instance, ?BoxCommand $lastJoin): bool
    {
        if ($box->status !== OnesiBoxStatus::Calling || $box->current_meeting_url === null) {
            return false;
        }

        return $box->current_meeting_url === $instance->zoom_url
            || $lastJoin?->status === CommandStatus::Completed;
    }

    private function shouldSendAgain(BoxCommand $lastJoin): bool
    {
        return match ($lastJoin->status) {
            // Still waiting for the box, unless its time is up.
            CommandStatus::Pending => $lastJoin->isExpired(),
            CommandStatus::Expired => true,
            // A box that keeps failing is not asked to open Zoom every minute.
            CommandStatus::Failed => $lastJoin->created_at === null
                || $lastJoin->created_at->lte(now()->subMinutes(self::MINUTES_BETWEEN_FAILED_ATTEMPTS)),
            // Executed by the box, or cancelled by someone: a person decides from here on.
            CommandStatus::Completed, CommandStatus::Cancelled => false,
        };
    }
}
