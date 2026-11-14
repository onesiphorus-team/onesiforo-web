<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingType;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Illuminate\Support\Facades\Cache;

/**
 * Connects an OnesiBox to its congregation Zoom meeting right now.
 *
 * Outside the scheduled meetings this records an ad-hoc meeting and the box attendance,
 * so it shows up in the meeting history; when the box is already attending a meeting in
 * progress, it is only asked to join again.
 */
class JoinCongregationMeetingAction
{
    public function __construct(
        private readonly OnesiBoxCommandService $commandService,
    ) {}

    /**
     * @return bool False when the box has no congregation or another join is being sent right now
     *
     * @throws OnesiBoxOfflineException
     */
    public function execute(OnesiBox $onesiBox): bool
    {
        $congregation = $onesiBox->recipient?->congregation;

        if ($congregation === null) {
            return false;
        }

        $lock = Cache::lock("onesi-box:{$onesiBox->id}:join-now", 10);

        if (! $lock->get()) {
            return false;
        }

        try {
            $isAttendingMeetingInProgress = MeetingAttendance::query()
                ->where('onesi_box_id', $onesiBox->id)
                ->where('status', MeetingAttendanceStatus::Joined)
                ->whereHas('meetingInstance', fn ($query) => $query->where('status', MeetingInstanceStatus::InProgress))
                ->exists();

            if (! $isAttendingMeetingInProgress) {
                $instance = MeetingInstance::query()->create([
                    'congregation_id' => $congregation->id,
                    'type' => MeetingType::Adhoc,
                    'scheduled_at' => now(),
                    'zoom_url' => $congregation->zoom_url,
                    'status' => MeetingInstanceStatus::InProgress,
                ]);

                MeetingAttendance::query()->create([
                    'meeting_instance_id' => $instance->id,
                    'onesi_box_id' => $onesiBox->id,
                    'join_mode' => $onesiBox->meeting_join_mode,
                    'status' => MeetingAttendanceStatus::Joined,
                    'joined_at' => now(),
                ]);
            }

            $this->commandService->sendZoomUrlCommand(
                $onesiBox,
                $congregation->zoom_url,
                $onesiBox->recipient->full_name ?? $onesiBox->name,
            );

            return true;
        } finally {
            $lock->release();
        }
    }
}
