<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\LogsActivityAllDirty;
use Database\Factories\ZoomMeetingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Zoom meeting known to an OnesiBox (other than its congregation meeting),
 * e.g. "Adunanza Loreto" or "Comitiva Cappelle".
 *
 * @property int $id
 * @property int $onesi_box_id
 * @property string $name
 * @property string $zoom_url
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OnesiBox $onesiBox
 */
#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'onesi_box_id',
    'name',
    'zoom_url',
    'last_used_at',
])]
class ZoomMeeting extends Model
{
    /** @use HasFactory<ZoomMeetingFactory> */
    use HasFactory;

    use LogsActivityAllDirty;

    /**
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    /**
     * Most recently used first, then the never-used ones by name.
     *
     * @param  Builder<ZoomMeeting>  $query
     * @return Builder<ZoomMeeting>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function mostRecentlyUsedFirst(Builder $query): Builder
    {
        return $query->orderByRaw('last_used_at IS NULL')->orderByDesc('last_used_at')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }
}
