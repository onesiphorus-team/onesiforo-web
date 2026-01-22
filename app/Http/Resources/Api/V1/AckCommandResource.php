<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for command acknowledgment API responses.
 *
 * @property bool $acknowledged
 * @property string $command_id
 * @property string $status
 */
class AckCommandResource extends JsonResource
{
    /**
     * Create a new resource instance.
     *
     * @param  array{acknowledged: bool, command_id: string, status: string}  $resource
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
            'acknowledged' => $this->resource['acknowledged'],
            'command_id' => $this->resource['command_id'],
            'status' => $this->resource['status'],
        ];
    }
}
