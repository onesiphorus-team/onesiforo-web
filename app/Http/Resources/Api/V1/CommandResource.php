<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Command;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for command API responses.
 *
 * @property Command $resource
 */
class CommandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->uuid,
            'type' => $this->resource->type->value,
            'payload' => $this->resource->payload,
            'priority' => $this->resource->priority,
            'status' => $this->resource->status->value,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'expires_at' => $this->resource->expires_at->toIso8601String(),
        ];
    }
}
