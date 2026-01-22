<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Traits\LogsActivityAllDirty;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Represents an OnesiBox appliance hardware device.
 *
 * @property int $id
 * @property string $name
 * @property string $serial_number
 * @property int|null $recipient_id
 * @property string|null $firmware_version
 * @property Carbon|null $last_seen_at
 * @property bool $is_active
 * @property OnesiBoxStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Recipient|null $recipient
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $caregivers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Command> $commands
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlaybackEvent> $playbackEvents
 */
class OnesiBox extends Model implements AuthenticatableContract
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\OnesiBoxFactory> */
    use HasFactory;

    use LogsActivityAllDirty;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'serial_number',
        'recipient_id',
        'firmware_version',
        'last_seen_at',
        'is_active',
        'status',
        'notes',
    ];

    /**
     * Get the recipient associated with this OnesiBox.
     *
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Get the caregivers (users) who can manage this OnesiBox.
     *
     * @return BelongsToMany<User, $this, OnesiBoxUser, 'pivot'>
     */
    public function caregivers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OnesiBoxUser::class)
            ->withPivot('id', 'permission')
            ->withTimestamps();
    }

    /**
     * Check if the OnesiBox is currently online.
     * An appliance is considered offline after 5 minutes without heartbeat.
     */
    public function isOnline(): bool
    {
        if ($this->last_seen_at === null) {
            return false;
        }

        return $this->last_seen_at->isAfter(now()->subMinutes(5));
    }

    /**
     * Record a heartbeat from the appliance.
     */
    public function recordHeartbeat(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Check if a user has full permission on this OnesiBox.
     */
    public function userHasFullPermission(User $user): bool
    {
        $caregiver = $this->caregivers()->where('user_id', $user->id)->first();

        if ($caregiver === null) {
            return false;
        }

        /** @var string|null $permission */
        $permission = $caregiver->pivot->getAttribute('permission');

        return $permission === OnesiBoxPermission::Full->value;
    }

    /**
     * Check if a user can view this OnesiBox.
     */
    public function userCanView(User $user): bool
    {
        return $this->caregivers()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the commands for this OnesiBox.
     *
     * @return HasMany<Command, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(Command::class);
    }

    /**
     * Get pending commands for this OnesiBox.
     * Commands are ordered by priority (highest first) and then by creation date (oldest first).
     *
     * @return HasMany<Command, $this>
     */
    public function pendingCommands(): HasMany
    {
        return $this->commands()
            ->where('status', CommandStatus::Pending)
            ->where('expires_at', '>', now())
            ->orderBy('priority')->oldest();
    }

    /**
     * Get playback events for this OnesiBox.
     *
     * @return HasMany<PlaybackEvent, $this>
     */
    public function playbackEvents(): HasMany
    {
        return $this->hasMany(PlaybackEvent::class);
    }

    // ========================================
    // AuthenticatableContract Implementation
    // ========================================

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        // Not used for API token auth
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Get the online status as a computed attribute.
     */
    protected function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
            'status' => OnesiBoxStatus::class,
        ];
    }
}
