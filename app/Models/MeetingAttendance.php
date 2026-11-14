<?php

declare(strict_types=1);

namespace App\Models;

use App\Console\Commands\AutoJoinMeetingsCommand;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Traits\LogsActivityAllDirty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $meeting_instance_id
 * @property int $onesi_box_id
 * @property MeetingAttendanceStatus $status
 * @property MeetingJoinMode $join_mode
 * @property \Carbon\CarbonInterface|null $joined_at
 * @property \Carbon\CarbonInterface|null $left_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read MeetingInstance $meetingInstance
 * @property-read OnesiBox $onesiBox
 */
#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'meeting_instance_id',
    'onesi_box_id',
    'join_mode',
    'joined_at',
    'left_at',
    'status',
])]
class MeetingAttendance extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingAttendanceFactory> */
    use HasFactory;

    use LogsActivityAllDirty;

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

    /**
     * The last command that asked the box to join this meeting, from the moment the automatic
     * join may start. It tells joins apart: one still waiting for the box, one the box
     * executed, one that failed or expired.
     */
    public function latestJoinCommand(): ?Command
    {
        $meeting = $this->meetingInstance;

        return Command::query()
            ->where('onesi_box_id', $this->onesi_box_id)
            ->where('type', CommandType::JoinZoom)
            ->where('created_at', '>=', $meeting->scheduled_at->copy()->subMinutes(AutoJoinMeetingsCommand::JOIN_MINUTES_BEFORE))
            ->latest('id')
            ->get()
            ->first(fn (Command $command): bool => ($command->payload['meeting_url'] ?? null) === $meeting->zoom_url);
    }

    /**
     * Whether Onesiforo will still connect the box to this meeting by itself: the automatic
     * join is not over, and nobody stepped in (a join the box executed, or one someone cancelled,
     * is not sent again: see AutoJoinMeetingsCommand).
     */
    public function willBeJoinedAutomatically(): bool
    {
        if ($this->join_mode !== MeetingJoinMode::Auto || $this->status !== MeetingAttendanceStatus::Pending) {
            return false;
        }

        if ($this->meetingInstance->scheduled_at->copy()->addMinutes(AutoJoinMeetingsCommand::RETRY_MINUTES_AFTER_START)->isPast()) {
            return false;
        }

        $lastJoin = $this->latestJoinCommand();

        return $lastJoin === null || ! in_array($lastJoin->status, [CommandStatus::Completed, CommandStatus::Cancelled], true);
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
