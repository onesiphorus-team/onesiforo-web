<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogSnapshotSource;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time capture of the OnesiBox application log.
 *
 * Rows are produced whenever a `get_logs` command is acknowledged, either
 * because an operator asked for logs or because the periodic collector
 * scheduled one. Failed acknowledgements are stored too, with `error_message`
 * populated and no lines, so the panel can show that a collection attempt
 * happened and why it produced no output.
 *
 * @property int $id
 * @property int $onesi_box_id
 * @property int|null $command_id
 * @property LogSnapshotSource $source
 * @property CarbonInterface $captured_at
 * @property string|null $log_file
 * @property int|null $total_lines
 * @property int|null $returned_lines
 * @property array<int, string> $lines
 * @property string|null $error_message
 * @property CarbonInterface|null $created_at
 * @property-read OnesiBox $onesiBox
 * @property-read Command|null $command
 */
#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'onesi_box_id',
    'command_id',
    'source',
    'captured_at',
    'log_file',
    'total_lines',
    'returned_lines',
    'lines',
    'error_message',
])]
class OnesiBoxLogSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\OnesiBoxLogSnapshotFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class, 'onesi_box_id');
    }

    /**
     * @return BelongsTo<Command, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }

    public function hasError(): bool
    {
        return $this->error_message !== null;
    }

    /**
     * The captured lines rendered as a single blob, ready for a <pre> block.
     */
    public function linesAsText(): string
    {
        return implode("\n", $this->lines);
    }

    /**
     * Scope a query to the most recent snapshots first.
     *
     * @param  Builder<OnesiBoxLogSnapshot>  $query
     * @return Builder<OnesiBoxLogSnapshot>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function latestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('captured_at')->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LogSnapshotSource::class,
            'captured_at' => 'datetime',
            'total_lines' => 'integer',
            'returned_lines' => 'integer',
            'lines' => 'array',
        ];
    }
}
