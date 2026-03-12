<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\MeetingType;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Livewire\Component;

class MeetingSchedule extends Component
{
    public OnesiBox $onesiBox;

    public function mount(OnesiBox $onesiBox): void
    {
        $this->onesiBox = $onesiBox->load('recipient.congregation');
    }

    public function toggleJoinMode(): void
    {
        $newMode = $this->onesiBox->meeting_join_mode === MeetingJoinMode::Auto
            ? MeetingJoinMode::Manual
            : MeetingJoinMode::Auto;

        $this->onesiBox->update(['meeting_join_mode' => $newMode]);
        $this->dispatch('meeting-join-mode-updated');
    }

    public function confirmJoin(int $attendanceId): void
    {
        $attendance = MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->findOrFail($attendanceId);

        $instance = $attendance->meetingInstance;
        $participantName = $this->onesiBox->recipient?->full_name ?? $this->onesiBox->name;

        app(OnesiBoxCommandService::class)->sendZoomUrlCommand(
            $this->onesiBox,
            $instance->zoom_url,
            $participantName,
        );

        $attendance->update([
            'status' => MeetingAttendanceStatus::Joined,
            'joined_at' => now(),
        ]);
    }

    public function skipMeeting(int $attendanceId): void
    {
        MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->where('id', $attendanceId)
            ->update(['status' => MeetingAttendanceStatus::Skipped]);
    }

    public function joinNow(): void
    {
        $congregation = $this->onesiBox->recipient?->congregation;
        if (! $congregation) {
            return;
        }

        $instance = MeetingInstance::create([
            'congregation_id' => $congregation->id,
            'type' => MeetingType::Adhoc,
            'scheduled_at' => now(),
            'zoom_url' => $congregation->zoom_url,
            'status' => MeetingInstanceStatus::InProgress,
        ]);

        MeetingAttendance::create([
            'meeting_instance_id' => $instance->id,
            'onesi_box_id' => $this->onesiBox->id,
            'join_mode' => $this->onesiBox->meeting_join_mode,
            'status' => MeetingAttendanceStatus::Joined,
            'joined_at' => now(),
        ]);

        $participantName = $this->onesiBox->recipient?->full_name ?? $this->onesiBox->name;

        app(OnesiBoxCommandService::class)->sendZoomUrlCommand(
            $this->onesiBox,
            $congregation->zoom_url,
            $participantName,
        );
    }

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
            ->whereHas('meetingInstance', fn ($q) => $q->nonTerminal())
            ->with('meetingInstance')
            ->first();
    }

    public function getRecentAttendancesProperty()
    {
        return MeetingAttendance::query()
            ->where('onesi_box_id', $this->onesiBox->id)
            ->with('meetingInstance.congregation')
            ->latest()
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.dashboard.controls.meeting-schedule');
    }
}
