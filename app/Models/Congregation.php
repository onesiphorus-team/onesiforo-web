<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingType;
use App\Rules\ZoomUrl;
use App\Traits\LogsActivityAllDirty;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Validator;

class Congregation extends Model
{
    /** @use HasFactory<\Database\Factories\CongregationFactory> */
    use HasFactory;

    use LogsActivityAllDirty;

    protected $fillable = [
        'name',
        'zoom_url',
        'midweek_day',
        'midweek_time',
        'weekend_day',
        'weekend_time',
        'timezone',
        'is_active',
    ];

    /** @return HasMany<Recipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    /** @return HasMany<MeetingInstance, $this> */
    public function meetingInstances(): HasMany
    {
        return $this->hasMany(MeetingInstance::class);
    }

    /** @return HasManyThrough<OnesiBox, Recipient, $this> */
    public function onesiBoxes(): HasManyThrough
    {
        return $this->hasManyThrough(OnesiBox::class, Recipient::class);
    }

    public function nextMidweekMeeting(): Carbon
    {
        return $this->nextMeetingOnDay($this->midweek_day, $this->midweek_time);
    }

    public function nextWeekendMeeting(): Carbon
    {
        return $this->nextMeetingOnDay($this->weekend_day, $this->weekend_time);
    }

    /**
     * @return array{type: MeetingType, scheduled_at: Carbon}
     */
    public function nextMeeting(): array
    {
        $midweek = $this->nextMidweekMeeting();
        $weekend = $this->nextWeekendMeeting();

        if ($midweek->lt($weekend)) {
            return ['type' => MeetingType::Midweek, 'scheduled_at' => $midweek];
        }

        return ['type' => MeetingType::Weekend, 'scheduled_at' => $weekend];
    }

    protected static function booted(): void
    {
        static::saving(function (Congregation $congregation): void {
            if ($congregation->isDirty('zoom_url')) {
                Validator::make(
                    ['zoom_url' => $congregation->zoom_url],
                    ['zoom_url' => [new ZoomUrl]],
                )->validate();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'midweek_day' => 'integer',
            'weekend_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    private function nextMeetingOnDay(int $dayOfWeek, string $time): Carbon
    {
        $tz = $this->timezone;
        $now = Carbon::now($tz);

        [$hour, $minute] = explode(':', $time);

        $next = $now->copy()->next($dayOfWeek)->setTime((int) $hour, (int) $minute, 0);

        // If today is meeting day and time hasn't passed, use today
        $today = $now->copy()->setTime((int) $hour, (int) $minute, 0);
        if ($now->dayOfWeek === $dayOfWeek && $now->lte($today)) {
            return $today;
        }

        return $next;
    }
}
