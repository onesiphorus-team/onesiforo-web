<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\MeetingJoinMode;
use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Traits\LogsActivityAllDirty;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Support\LogOptions;

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
 * @property string|null $current_media_url
 * @property string|null $current_media_type
 * @property string|null $current_media_title
 * @property int|null $current_media_position
 * @property int|null $current_media_duration
 * @property string|null $current_meeting_id
 * @property string|null $current_meeting_url
 * @property CarbonInterface|null $current_meeting_joined_at
 * @property int $volume
 * @property CarbonInterface|null $last_system_info_at
 * @property int|null $cpu_usage
 * @property int|null $memory_usage
 * @property int|null $disk_usage
 * @property float|null $temperature
 * @property int|null $uptime
 * @property string|null $network_type
 * @property string|null $network_interface
 * @property string|null $ip_address
 * @property string|null $netmask
 * @property string|null $gateway
 * @property string|null $mac_address
 * @property array<string>|null $dns_servers
 * @property string|null $wifi_ssid
 * @property int|null $wifi_signal_dbm
 * @property int|null $wifi_signal_percent
 * @property int|null $wifi_channel
 * @property int|null $wifi_frequency
 * @property int|null $memory_total
 * @property int|null $memory_used
 * @property int|null $memory_free
 * @property int|null $memory_available
 * @property int|null $memory_buffers
 * @property int|null $memory_cached
 * @property string|null $app_version
 * @property MeetingJoinMode $meeting_join_mode
 * @property bool $meeting_notifications_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Recipient|null $recipient
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $caregivers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Command> $commands
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlaybackEvent> $playbackEvents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Playlist> $playlists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlaybackSession> $playbackSessions
 */
class OnesiBox extends Model implements AuthenticatableContract
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\OnesiBoxFactory> */
    use HasFactory;

    use LogsActivityAllDirty {
        LogsActivityAllDirty::getActivitylogOptions as getBaseActivitylogOptions;
    }
    use SoftDeletes;

    /**
     * Telemetry fields excluded from activity logging.
     *
     * These are updated every heartbeat (~30s) and would generate excessive
     * activity_log entries. They are transient metrics, not meaningful state
     * changes worth auditing.
     *
     * @var list<string>
     */
    private const array TELEMETRY_FIELDS = [
        'last_seen_at',
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'temperature',
        'uptime',
        'network_type',
        'network_interface',
        'ip_address',
        'netmask',
        'gateway',
        'mac_address',
        'dns_servers',
        'wifi_ssid',
        'wifi_signal_dbm',
        'wifi_signal_percent',
        'wifi_channel',
        'wifi_frequency',
        'memory_total',
        'memory_used',
        'memory_free',
        'memory_available',
        'memory_buffers',
        'memory_cached',
        'last_system_info_at',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Override activity log options to exclude telemetry fields.
     *
     * Heartbeat updates are frequent (~30s per device). Without this exclusion,
     * each heartbeat generates an activity_log record with all dirty telemetry
     * fields. With N devices: N * 2 * 60 * 24 = 2880*N records/day.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->getBaseActivitylogOptions()
            ->logExcept(self::TELEMETRY_FIELDS)
            ->dontLogIfAttributesChangedOnly(self::TELEMETRY_FIELDS);
    }

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
     * Saves all pending attribute changes along with the heartbeat timestamp.
     */
    public function recordHeartbeat(): void
    {
        $this->last_seen_at = Carbon::now();
        $this->save();
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

        /** @var OnesiBoxPermission|null $permission */
        $permission = $caregiver->pivot->getAttribute('permission');

        return $permission === OnesiBoxPermission::Full;
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

    /**
     * Get the playlists for this OnesiBox.
     *
     * @return HasMany<Playlist, $this>
     */
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    /**
     * Get the playback sessions for this OnesiBox.
     *
     * @return HasMany<PlaybackSession, $this>
     */
    public function playbackSessions(): HasMany
    {
        return $this->hasMany(PlaybackSession::class);
    }

    /**
     * Get the currently active playback session, if any.
     */
    public function activeSession(): ?PlaybackSession
    {
        return $this->playbackSessions()
            ->active()
            ->first();
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
            'current_media_position' => 'integer',
            'current_media_duration' => 'integer',
            'current_meeting_joined_at' => 'datetime',
            'meeting_join_mode' => MeetingJoinMode::class,
            'meeting_notifications_enabled' => 'boolean',
            'volume' => 'integer',
            'last_system_info_at' => 'datetime',
            'cpu_usage' => 'integer',
            'memory_usage' => 'integer',
            'disk_usage' => 'integer',
            'temperature' => 'float',
            'uptime' => 'integer',
            'dns_servers' => 'array',
            'wifi_signal_dbm' => 'integer',
            'wifi_signal_percent' => 'integer',
            'wifi_channel' => 'integer',
            'wifi_frequency' => 'integer',
            'memory_total' => 'integer',
            'memory_used' => 'integer',
            'memory_free' => 'integer',
            'memory_available' => 'integer',
            'memory_buffers' => 'integer',
            'memory_cached' => 'integer',
            'screenshot_enabled' => 'boolean',
            'screenshot_interval_seconds' => 'integer',
        ];
    }
}
