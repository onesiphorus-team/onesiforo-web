<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Traits\LogsActivityAllDirty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property MeetingAttendanceStatus $status
 * @property MeetingJoinMode $join_mode
 */
class MeetingAttendance extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingAttendanceFactory> */
    use HasFactory;

    use LogsActivityAllDirty;

    protected $fillable = [
        'meeting_instance_id',
        'onesi_box_id',
        'join_mode',
        'joined_at',
        'left_at',
        'status',
    ];

    /** @return BelongsTo<MeetingInstance, $this> */
    public function meetingInstance(): BelongsTo
    {
        return $this->belongsTo(MeetingInstance::class);
    }

    /** @return BelongsTo<OnesiBox, $this> */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    protected function casts(): array
    {
        return [
            'join_mode' => MeetingJoinMode::class,
            'status' => MeetingAttendanceStatus::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', MeetingAttendanceStatus::Joined);
    }
}
