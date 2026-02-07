<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlaybackSessionStatus;
use App\Traits\LogsActivityAllDirty;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Represents a timed playback session on an OnesiBox.
 *
 * @property int $id
 * @property string $uuid
 * @property int $onesi_box_id
 * @property int $playlist_id
 * @property PlaybackSessionStatus $status
 * @property int $duration_minutes
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $ended_at
 * @property int $current_position
 * @property int $items_played
 * @property int $items_skipped
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OnesiBox $onesiBox
 * @property-read Playlist $playlist
 */
class PlaybackSession extends Model
{
    /** @use HasFactory<\Database\Factories\PlaybackSessionFactory> */
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
        'playlist_id',
        'status',
        'duration_minutes',
        'started_at',
        'ended_at',
        'current_position',
        'items_played',
        'items_skipped',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the OnesiBox that owns this session.
     *
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    /**
     * Get the playlist for this session.
     *
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * Get the expiration timestamp for this session.
     */
    public function expiresAt(): CarbonInterface
    {
        return $this->started_at->addMinutes($this->duration_minutes);
    }

    /**
     * Check if the session has expired based on started_at + duration_minutes.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt()->isPast();
    }

    /**
     * Get the remaining time in seconds for this session.
     */
    public function timeRemainingSeconds(): int
    {
        $remaining = (int) Carbon::now()->diffInSeconds($this->expiresAt(), false);

        return max(0, $remaining);
    }

    /**
     * Get the current playlist item at the current_position.
     */
    public function currentItem(): ?PlaylistItem
    {
        return $this->playlist->items()
            ->where('position', $this->current_position)
            ->first();
    }

    /**
     * Bootstrap the model and register events.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PlaybackSession $session): void {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Scope a query to only include active sessions.
     *
     * @param  Builder<PlaybackSession>  $query
     * @return Builder<PlaybackSession>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', PlaybackSessionStatus::Active);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlaybackSessionStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'current_position' => 'integer',
            'items_played' => 'integer',
            'items_skipped' => 'integer',
        ];
    }
}
