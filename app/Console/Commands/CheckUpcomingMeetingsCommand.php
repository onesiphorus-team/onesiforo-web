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
use Illuminate\Support\Facades\DB;

class CheckUpcomingMeetingsCommand extends Command
{
    protected $signature = 'meetings:check-upcoming';

    protected $description = 'Check for upcoming meetings and create instances with notifications';

    public function handle(): int
    {
        $congregations = Congregation::query()
            ->where('is_active', true)
            ->with(['onesiBoxes.recipient', 'onesiBoxes.caregivers'])
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

        DB::transaction(function () use ($congregation, $type, $meetingTime): void {
            $instance = MeetingInstance::query()->firstOrCreate(
                [
                    'congregation_id' => $congregation->id,
                    'type' => $type,
                    'scheduled_at' => $meetingTime->utc(),
                ],
                [
                    'zoom_url' => $congregation->zoom_url,
                    'status' => MeetingInstanceStatus::Scheduled,
                ]
            );

            if (! $instance->wasRecentlyCreated) {
                return;
            }

            /** @var \App\Models\OnesiBox $box */
            foreach ($congregation->onesiBoxes as $box) {
                MeetingAttendance::query()->create([
                    'meeting_instance_id' => $instance->id,
                    'onesi_box_id' => $box->id,
                    'join_mode' => $box->meeting_join_mode,
                    'status' => MeetingAttendanceStatus::Pending,
                ]);

                if ($box->meeting_notifications_enabled) {
                    foreach ($box->caregivers as $caregiver) {
                        $caregiver->notify(new MeetingUpcomingNotification($instance, $box));
                    }
                }
            }

            if ($congregation->onesiBoxes->isNotEmpty()) {
                $instance->update(['status' => MeetingInstanceStatus::Notified]);
            }
        });
    }
}
