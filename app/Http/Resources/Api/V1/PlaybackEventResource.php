<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for playback event API responses.
 *
 * @property bool $logged
 * @property int $event_id
 */
class PlaybackEventResource extends JsonResource
{
    /**
     * Create a new resource instance.
     *
     * @param  array{logged: bool, event_id: int}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'logged' => $this->resource['logged'],
            'event_id' => $this->resource['event_id'],
        ];
    }
}
