<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Traits\LogsActivityAllDirty;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Represents a command to be executed on an OnesiBox appliance.
 *
 * @property int $id
 * @property string $uuid
 * @property int $onesi_box_id
 * @property CommandType $type
 * @property array<string, mixed>|null $payload
 * @property int $priority
 * @property CommandStatus $status
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $executed_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string, mixed>|null $result
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read OnesiBox $onesiBox
 */
class Command extends Model
{
    /** @use HasFactory<\Database\Factories\CommandFactory> */
    use HasFactory;

    use LogsActivityAllDirty;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'onesi_box_id',
        'type',
        'payload',
        'priority',
        'status',
        'expires_at',
        'executed_at',
        'error_code',
        'error_message',
        'result',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the OnesiBox that owns this command.
     *
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    /**
     * Check if the command has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the command can be acknowledged.
     */
    public function canBeAcknowledged(): bool
    {
        return $this->status === CommandStatus::Pending;
    }

    /**
     * Mark the command as expired.
     */
    public function markAsExpired(): void
    {
        if ($this->status !== CommandStatus::Pending) {
            return;
        }

        $this->update([
            'status' => CommandStatus::Expired,
            'executed_at' => now(),
        ]);
    }

    /**
     * Mark the command as completed.
     *
     * @param  array<string, mixed>|null  $result  Optional result data from diagnostic commands
     */
    public function markAsCompleted(?CarbonInterface $executedAt = null, ?array $result = null): void
    {
        $this->update([
            'status' => CommandStatus::Completed,
            'executed_at' => $executedAt ?? now(),
            'result' => $result,
        ]);
    }

    /**
     * Mark the command as failed.
     */
    public function markAsFailed(?string $errorCode = null, ?string $errorMessage = null, ?CarbonInterface $executedAt = null): void
    {
        $this->update([
            'status' => CommandStatus::Failed,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'executed_at' => $executedAt ?? now(),
        ]);
    }

    /**
     * Bootstrap the model and register events.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Command $command): void {
            if (empty($command->uuid)) {
                $command->uuid = (string) Str::uuid();
            }

            if (empty($command->expires_at)) {
                $command->expires_at = \Illuminate\Support\Facades\Date::now()->addMinutes($command->type->defaultExpiresInMinutes());
            }
        });
    }

    /**
     * Scope a query to only include pending commands.
     *
     * @param  Builder<Command>  $query
     * @return Builder<Command>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', CommandStatus::Pending)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include expired commands that need to be marked.
     *
     * @param  Builder<Command>  $query
     * @return Builder<Command>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function expiredPending(Builder $query): Builder
    {
        return $query->where('status', CommandStatus::Pending)
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope a query to order by priority (highest first) and then by creation date (oldest first).
     *
     * @param  Builder<Command>  $query
     * @return Builder<Command>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function orderByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority')->oldest();
    }

    /**
     * Get the casts array for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CommandType::class,
            'payload' => 'array',
            'priority' => 'integer',
            'status' => CommandStatus::class,
            'expires_at' => 'datetime',
            'executed_at' => 'datetime',
            'result' => 'array',
        ];
    }
}
