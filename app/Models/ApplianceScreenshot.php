<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $onesi_box_id
 * @property CarbonInterface $captured_at
 * @property int $width
 * @property int $height
 * @property int $bytes
 * @property string $storage_path
 * @property CarbonInterface|null $created_at
 * @property-read OnesiBox $onesiBox
 */
class ApplianceScreenshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'onesi_box_id',
        'captured_at',
        'width',
        'height',
        'bytes',
        'storage_path',
    ];

    /**
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class, 'onesi_box_id');
    }

    public function signedUrl(int $minutes = 5): string
    {
        return URL::signedRoute(
            'api.v1.screenshots.show',
            ['screenshot' => $this->id],
            now()->addMinutes($minutes)
        );
    }

    protected static function booted(): void
    {
        static::deleting(function (self $screenshot): void {
            Storage::disk('local')->delete($screenshot->storage_path);
        });
    }

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
        ];
    }
}
