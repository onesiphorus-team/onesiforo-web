<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection resource for commands API responses.
 *
 * @property int $totalCommands
 * @property int $pendingCommands
 */
class CommandCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = CommandResource::class;

    /**
     * Total number of commands (all statuses).
     */
    protected int $totalCommands = 0;

    /**
     * Number of pending commands.
     */
    protected int $pendingCommands = 0;

    /**
     * Set the metadata for the collection.
     */
    public function withMeta(int $total, int $pending): self
    {
        $this->totalCommands = $total;
        $this->pendingCommands = $pending;

        return $this;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->totalCommands,
                'pending' => $this->pendingCommands,
            ],
        ];
    }
}
