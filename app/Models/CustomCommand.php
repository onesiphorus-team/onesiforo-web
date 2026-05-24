<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\LogsActivityAllDirty;
use Database\Factories\CustomCommandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Per-box custom shell command definition.
 *
 * @property int $id
 * @property int $onesi_box_id
 * @property string $name
 * @property string|null $description
 * @property string $script_name
 * @property array<int, string> $static_args
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OnesiBox $onesiBox
 */
class CustomCommand extends Model
{
    /** @use HasFactory<CustomCommandFactory> */
    use HasFactory;

    use LogsActivityAllDirty;
    use SoftDeletes;

    public const SCRIPT_NAME_REGEX = '/^[a-zA-Z0-9_.\-]+\.sh$/';

    protected $table = 'onesi_box_custom_commands';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'onesi_box_id',
        'name',
        'description',
        'script_name',
        'static_args',
        'icon',
        'sort_order',
        'is_enabled',
    ];

    /**
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    /**
     * @param  Builder<CustomCommand>  $query
     * @return Builder<CustomCommand>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function enabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * @param  Builder<CustomCommand>  $query
     * @return Builder<CustomCommand>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'static_args' => 'array',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
