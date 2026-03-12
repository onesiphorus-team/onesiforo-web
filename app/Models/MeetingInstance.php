<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingInstance extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingInstanceFactory> */
    use HasFactory;

    protected $fillable = [
        'congregation_id',
        'type',
        'scheduled_at',
        'zoom_url',
        'status',
        'cancelled_reason',
    ];

    /** @return BelongsTo<Congregation, $this> */
    public function congregation(): BelongsTo
    {
        return $this->belongsTo(Congregation::class);
    }

    /** @return HasMany<MeetingAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    /** @param Builder<self> $query */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function nonTerminal(Builder $query): void
    {
        $query->whereNotIn('status', [
            MeetingInstanceStatus::Completed->value,
            MeetingInstanceStatus::Cancelled->value,
        ]);
    }

    protected function casts(): array
    {
        return [
            'type' => MeetingType::class,
            'status' => MeetingInstanceStatus::class,
            'scheduled_at' => 'datetime',
        ];
    }
}
