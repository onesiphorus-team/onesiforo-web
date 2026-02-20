<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlaybackEventType;
use App\Traits\LogsActivityAllDirty;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a playback event from an OnesiBox appliance.
 *
 * @property int $id
 * @property int $onesi_box_id
 * @property PlaybackEventType $event
 * @property string $media_url
 * @property string $media_type
 * @property int|null $position
 * @property int|null $duration
 * @property string|null $error_message
 * @property string|null $session_id
 * @property CarbonInterface $created_at
 * @property-read OnesiBox $onesiBox
 */
class PlaybackEvent extends Model
{
    /** @use HasFactory<\Database\Factories\PlaybackEventFactory> */
    use HasFactory;

    use LogsActivityAllDirty;

    /**
     * Indicates if the model should be timestamped.
     * This model only has created_at, managed manually.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'onesi_box_id',
        'event',
        'media_url',
        'media_type',
        'position',
        'duration',
        'error_message',
        'session_id',
        'created_at',
    ];

    /**
     * Get the OnesiBox that owns this playback event.
     *
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    /**
     * Bootstrap the model and register events.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PlaybackEvent $playbackEvent): void {
            if (empty($playbackEvent->created_at)) {
                $playbackEvent->created_at = \Illuminate\Support\Facades\Date::now();
            }
        });
    }

    /**
     * Get the casts array for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => PlaybackEventType::class,
            'position' => 'integer',
            'duration' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
