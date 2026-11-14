<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Meetings\JoinCongregationMeetingAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\MeetingType;
use App\Models\MeetingAttendance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class MeetingSchedule extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    public OnesiBox $onesiBox;

    /**
     * The meeting to confirm currently on screen, to re-render only when it changes.
     */
    #[Locked]
    public ?int $shownPendingAttendanceId = null;

    public function mount(OnesiBox $onesiBox): void
    {
        Gate::authorize('view', $onesiBox);

        $this->onesiBox = $onesiBox->load('recipient.congregation');
        $this->shownPendingAttendanceId = $this->pendingAttendanceQuery()->value('meeting_attendances.id');
    }

    /**
     * Called by the poll: show (or hide) the "meeting coming up, confirm or skip" box as
     * soon as it changes. When nothing changed the component is not rendered again.
     */
    public function checkPendingAttendance(): void
    {
        $pendingAttendanceId = $this->pendingAttendanceQuery()->value('meeting_attendances.id');

        if ($pendingAttendanceId === $this->shownPendingAttendanceId) {
            $this->skipRender();

            return;
        }

        $this->shownPendingAttendanceId = $pendingAttendanceId;
    }

    /**
     * Show the box as reachable (or not) again as soon as the page notices: the poll above
     * renders nothing when no meeting is waiting, so "Collega ora" would stay disabled.
     *
     * Nothing to reload here: Livewire fetches the box again on every request.
     */
    #[On('box-state-changed')]
    public function refreshBox(): void {}

    public function toggleJoinMode(): void
    {
        $this->authorize('control', $this->onesiBox);

        $newMode = $this->onesiBox->meeting_join_mode === MeetingJoinMode::Auto
            ? MeetingJoinMode::Manual
            : MeetingJoinMode::Auto;

        $this->onesiBox->update(['meeting_join_mode' => $newMode]);
        $this->dispatch('meeting-join-mode-updated');
    }

    public function confirmJoin(int $attendanceId): void
    {
        $this->authorize('control', $this->onesiBox);

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
        $this->authorize('control', $this->onesiBox);

        MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->where('id', $attendanceId)
            ->update(['status' => MeetingAttendanceStatus::Skipped]);
    }

    public function joinNow(JoinCongregationMeetingAction $joinCongregationMeeting): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->onesiBox->recipient?->congregation === null) {
            return;
        }

        $this->executeWithErrorHandling(
            fn (): bool => $joinCongregationMeeting->execute($this->onesiBox),
            "Sto collegando il box all'adunanza…",
        );
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
        return $this->pendingAttendanceQuery()->with('meetingInstance')->first();
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
        return view('livewire.dashboard.controls.meeting-schedule', [
            'displayTimezone' => config('app.display_timezone', 'Europe/Rome'),
        ]);
    }

    /**
     * @return Builder<MeetingAttendance>
     */
    private function pendingAttendanceQuery(): Builder
    {
        return MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->whereIn('status', [MeetingAttendanceStatus::Pending, MeetingAttendanceStatus::Confirmed])
            ->whereHas('meetingInstance', fn ($q) => $q->whereNotIn('status', [
                MeetingInstanceStatus::Completed->value,
                MeetingInstanceStatus::Cancelled->value,
            ]));
    }
}
