<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\MeetingType;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class MeetingSchedule extends Component
{
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;

    public OnesiBox $onesiBox;

    public function mount(OnesiBox $onesiBox): void
    {
        $this->authorizeCaregiver();
        $this->onesiBox = $onesiBox->load('recipient.congregation');
    }

    public function toggleJoinMode(): void
    {
        $this->authorizeCaregiver();

        $newMode = $this->onesiBox->meeting_join_mode === MeetingJoinMode::Auto
            ? MeetingJoinMode::Manual
            : MeetingJoinMode::Auto;

        $this->onesiBox->update(['meeting_join_mode' => $newMode]);
        $this->dispatch('meeting-join-mode-updated');
    }

    public function confirmJoin(int $attendanceId): void
    {
        $this->authorizeCaregiver();

        $attendance = MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->findOrFail($attendanceId);

        $instance = $attendance->meetingInstance;
        $participantName = $this->onesiBox->recipient->full_name ?? $this->onesiBox->name;

        $this->executeWithErrorHandling(
            fn () => resolve(OnesiBoxCommandService::class)->sendZoomUrlCommand(
                $this->onesiBox,
                (string) $instance->zoom_url,
                $participantName,
            ),
            'Collegamento in corso...',
        );

        $attendance->update([
            'status' => MeetingAttendanceStatus::Joined,
            'joined_at' => now(),
        ]);
    }

    public function skipMeeting(int $attendanceId): void
    {
        $this->authorizeCaregiver();

        MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->where('id', $attendanceId)
            ->update(['status' => MeetingAttendanceStatus::Skipped]);
    }

    public function joinNow(): void
    {
        $this->authorizeCaregiver();

        $congregation = $this->onesiBox->recipient?->congregation;
        if (! $congregation) {
            return;
        }

        $participantName = $this->onesiBox->recipient->full_name ?? $this->onesiBox->name;

        $lock = Cache::lock("onesi-box:{$this->onesiBox->id}:join-now", 10);

        if (! $lock->get()) {
            return;
        }

        try {
            $existingAttendance = MeetingAttendance::query()
                ->where('onesi_box_id', $this->onesiBox->id)
                ->where('status', MeetingAttendanceStatus::Joined)
                ->whereHas('meetingInstance', fn ($q) => $q->where('status', MeetingInstanceStatus::InProgress))
                ->first();

            if ($existingAttendance !== null) {
                $this->executeWithErrorHandling(
                    fn () => resolve(OnesiBoxCommandService::class)->sendZoomUrlCommand(
                        $this->onesiBox,
                        $congregation->zoom_url,
                        $participantName,
                    ),
                    'Collegamento ad-hoc in corso...',
                );

                return;
            }

            $instance = MeetingInstance::query()->create([
                'congregation_id' => $congregation->id,
                'type' => MeetingType::Adhoc,
                'scheduled_at' => now(),
                'zoom_url' => $congregation->zoom_url,
                'status' => MeetingInstanceStatus::InProgress,
            ]);

            MeetingAttendance::query()->create([
                'meeting_instance_id' => $instance->id,
                'onesi_box_id' => $this->onesiBox->id,
                'join_mode' => $this->onesiBox->meeting_join_mode,
                'status' => MeetingAttendanceStatus::Joined,
                'joined_at' => now(),
            ]);

            $this->executeWithErrorHandling(
                fn () => resolve(OnesiBoxCommandService::class)->sendZoomUrlCommand(
                    $this->onesiBox,
                    $congregation->zoom_url,
                    $participantName,
                ),
                'Collegamento ad-hoc in corso...',
            );
        } finally {
            $lock->release();
        }
    }

    /** @return array{type: MeetingType, scheduled_at: \Carbon\Carbon}|null */
    public function getNextMeetingProperty(): ?array
    {
        $congregation = $this->onesiBox->recipient?->congregation;
        if (! $congregation) {
            return null;
        }

        return $congregation->nextMeeting();
    }

    public function getPendingAttendanceProperty(): ?MeetingAttendance
    {
        return MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->whereIn('status', [MeetingAttendanceStatus::Pending, MeetingAttendanceStatus::Confirmed])
            ->whereHas('meetingInstance', fn ($q) => $q->whereNotIn('status', [
                MeetingInstanceStatus::Completed->value,
                MeetingInstanceStatus::Cancelled->value,
            ]))
            ->with('meetingInstance')
            ->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, MeetingAttendance> */
    public function getRecentAttendancesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->with('meetingInstance.congregation')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        return view('livewire.dashboard.controls.meeting-schedule');
    }

    private function authorizeCaregiver(): void
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $this->onesiBox->userCanView($user),
            403,
        );
    }
}
