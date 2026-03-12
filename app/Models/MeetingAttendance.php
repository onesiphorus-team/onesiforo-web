<?php

namespace App\Models;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendance extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingAttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_instance_id',
        'onesi_box_id',
        'join_mode',
        'joined_at',
        'left_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'join_mode' => MeetingJoinMode::class,
            'status' => MeetingAttendanceStatus::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function meetingInstance(): BelongsTo
    {
        return $this->belongsTo(MeetingInstance::class);
    }

    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', MeetingAttendanceStatus::Joined);
    }
}
