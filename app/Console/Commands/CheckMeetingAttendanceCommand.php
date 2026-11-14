<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\OnesiBoxStatus;
use App\Models\MeetingAttendance;
use App\Services\BoxAlerts\BoxActivityMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Alerts on Telegram about boxes that risk missing a congregation meeting:
 * - "auto" boxes not in a meeting that started a few minutes ago;
 * - "manual" boxes whose join nobody confirmed yet, shortly before the start.
 */
#[\Illuminate\Console\Attributes\Description('Alert about boxes missing a meeting or waiting for the join to be confirmed')]
#[\Illuminate\Console\Attributes\Signature('meetings:check-attendance')]
class CheckMeetingAttendanceCommand extends Command
{
    /**
     * Time left to the automatic join before alerting.
     */
    public const int AUTO_GRACE_MINUTES = 5;

    /**
     * Meetings started longer ago than this are not checked anymore.
     */
    public const int AUTO_WINDOW_MINUTES = 30;

    /**
     * How long before the start a manual box still waiting for confirmation is reported.
     */
    public const int MANUAL_REMINDER_MINUTES_BEFORE = 10;

    public function handle(BoxActivityMonitor $activityMonitor): int
    {
        $missedAutoJoins = $this->missedAutoJoins();
        $unconfirmedManualJoins = $this->unconfirmedManualJoins();

        foreach ($missedAutoJoins as $attendance) {
            $activityMonitor->autoJoinMissed($attendance);
        }

        foreach ($unconfirmedManualJoins as $attendance) {
            $activityMonitor->manualJoinNotConfirmed($attendance);
        }

        $this->info("Missed automatic joins: {$missedAutoJoins->count()}, unconfirmed manual joins: {$unconfirmedManualJoins->count()}");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, MeetingAttendance>
     */
    private function missedAutoJoins(): Collection
    {
        return MeetingAttendance::query()
            ->where('join_mode', MeetingJoinMode::Auto)
            ->whereIn('status', [MeetingAttendanceStatus::Pending, MeetingAttendanceStatus::Confirmed, MeetingAttendanceStatus::Joined])
            ->whereHas('meetingInstance', fn ($query) => $query->whereBetween('scheduled_at', [
                now()->subMinutes(self::AUTO_WINDOW_MINUTES),
                now()->subMinutes(self::AUTO_GRACE_MINUTES),
            ]))
            ->with(['onesiBox.recipient', 'meetingInstance.congregation'])
            ->get()
            ->reject(fn (MeetingAttendance $attendance): bool => $this->isInMeeting($attendance))
            ->values()
            ->toBase();
    }

    /**
     * @return Collection<int, MeetingAttendance>
     */
    private function unconfirmedManualJoins(): Collection
    {
        return MeetingAttendance::query()
            ->where('join_mode', MeetingJoinMode::Manual)
            ->where('status', MeetingAttendanceStatus::Pending)
            ->whereHas('meetingInstance', fn ($query) => $query->whereBetween('scheduled_at', [
                now(),
                now()->addMinutes(self::MANUAL_REMINDER_MINUTES_BEFORE),
            ]))
            ->with(['onesiBox.recipient', 'meetingInstance.congregation'])
            ->get()
            ->reject(fn (MeetingAttendance $attendance): bool => $this->isInMeeting($attendance))
            ->values()
            ->toBase();
    }

    private function isInMeeting(MeetingAttendance $attendance): bool
    {
        $onesiBox = $attendance->onesiBox;

        return $onesiBox->isOnline()
            && $onesiBox->status === OnesiBoxStatus::Calling
            && $onesiBox->current_meeting_url !== null;
    }
}
