<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingType;
use App\Models\Congregation;
use App\Models\MeetingAttendance;
use App\Models\MeetingInstance;
use App\Notifications\MeetingUpcomingNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckUpcomingMeetingsCommand extends Command
{
    protected $signature = 'meetings:check-upcoming';

    protected $description = 'Check for upcoming meetings and create instances with notifications';

    public function handle(): int
    {
        $congregations = Congregation::query()
            ->where('is_active', true)
            ->with('onesiBoxes.recipient')
            ->get();

        foreach ($congregations as $congregation) {
            $this->checkMeetingType($congregation, MeetingType::Midweek, $congregation->midweek_day, $congregation->midweek_time);
            $this->checkMeetingType($congregation, MeetingType::Weekend, $congregation->weekend_day, $congregation->weekend_time);
        }

        return self::SUCCESS;
    }

    private function checkMeetingType(Congregation $congregation, MeetingType $type, int $day, string $time): void
    {
        $tz = $congregation->timezone;
        $now = Carbon::now($tz);

        [$hour, $minute] = explode(':', $time);
        $meetingTime = $now->copy()->setTime((int) $hour, (int) $minute, 0);

        // If today is the meeting day
        if ($now->dayOfWeek !== $day) {
            return;
        }

        // Check if within 30-minute window before meeting
        $minutesUntil = $now->diffInMinutes($meetingTime, false);
        if ($minutesUntil < 0 || $minutesUntil > 30) {
            return;
        }

        // Idempotency: check if instance already exists
        $existing = MeetingInstance::query()
            ->where('congregation_id', $congregation->id)
            ->where('type', $type)
            ->where('scheduled_at', $meetingTime->utc())
            ->exists();

        if ($existing) {
            return;
        }

        $instance = MeetingInstance::query()->create([
            'congregation_id' => $congregation->id,
            'type' => $type,
            'scheduled_at' => $meetingTime->utc(),
            'zoom_url' => $congregation->zoom_url,
            'status' => MeetingInstanceStatus::Scheduled,
        ]);

        /** @var \App\Models\OnesiBox $box */
        foreach ($congregation->onesiBoxes as $box) {
            MeetingAttendance::query()->create([
                'meeting_instance_id' => $instance->id,
                'onesi_box_id' => $box->id,
                'join_mode' => $box->meeting_join_mode,
                'status' => MeetingAttendanceStatus::Pending,
            ]);

            // Notify caregivers if notifications enabled
            if ($box->meeting_notifications_enabled) {
                $box->load('caregivers');
                foreach ($box->caregivers as $caregiver) {
                    $caregiver->notify(new MeetingUpcomingNotification($instance, $box));
                }
            }
        }

        $instance->update(['status' => MeetingInstanceStatus::Notified]);
    }
}
