<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlaylistSourceType;
use App\Traits\LogsActivityAllDirty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a playlist of media items for an OnesiBox.
 *
 * @property int $id
 * @property int $onesi_box_id
 * @property string|null $name
 * @property PlaylistSourceType $source_type
 * @property string|null $source_url
 * @property bool $is_saved
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read OnesiBox $onesiBox
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlaylistItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlaybackSession> $playbackSessions
 */
class Playlist extends Model
{
    /** @use HasFactory<\Database\Factories\PlaylistFactory> */
    use HasFactory;

    use LogsActivityAllDirty;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'onesi_box_id',
        'name',
        'source_type',
        'source_url',
        'is_saved',
    ];

    /**
     * Get the OnesiBox that owns this playlist.
     *
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    /**
     * Get the items in this playlist, ordered by position.
     *
     * @return HasMany<PlaylistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position');
    }

    /**
     * Get the playback sessions for this playlist.
     *
     * @return HasMany<PlaybackSession, $this>
     */
    public function playbackSessions(): HasMany
    {
        return $this->hasMany(PlaybackSession::class);
    }

    /**
     * Scope a query to only include saved playlists.
     *
     * @param  Builder<Playlist>  $query
     * @return Builder<Playlist>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function onlySaved(Builder $query): Builder
    {
        return $query->where('is_saved', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => PlaylistSourceType::class,
            'is_saved' => 'boolean',
        ];
    }
}
