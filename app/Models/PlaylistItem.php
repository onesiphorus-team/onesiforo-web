<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\LogsActivityAllDirty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Represents a single media item within a playlist.
 *
 * @property int $id
 * @property int $playlist_id
 * @property string $media_url
 * @property string|null $title
 * @property int|null $duration_seconds
 * @property int $position
 * @property Carbon|null $created_at
 * @property-read Playlist $playlist
 */
class PlaylistItem extends Model
{
    /** @use HasFactory<\Database\Factories\PlaylistItemFactory> */
    use HasFactory;

    use LogsActivityAllDirty;

    /**
     * Disable updated_at timestamp — this model only uses created_at.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'playlist_id',
        'media_url',
        'title',
        'duration_seconds',
        'position',
    ];

    /**
     * Get the playlist that owns this item.
     *
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration_seconds' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
