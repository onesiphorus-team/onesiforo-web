<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for heartbeat API responses.
 *
 * @property \Carbon\CarbonInterface $server_time
 * @property int $next_heartbeat
 */
class HeartbeatResource extends JsonResource
{
    /**
     * The default heartbeat interval in seconds.
     */
    public const int DEFAULT_HEARTBEAT_INTERVAL = 30;

    /**
     * Create a new resource instance.
     *
     * @param  array{server_time: \Carbon\CarbonInterface, next_heartbeat: int}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Create a successful heartbeat response.
     */
    public static function success(int $nextHeartbeat = self::DEFAULT_HEARTBEAT_INTERVAL): self
    {
        return new self([
            'server_time' => now(),
            'next_heartbeat' => $nextHeartbeat,
        ]);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'server_time' => $this->resource['server_time']->toIso8601String(),
            'next_heartbeat' => $this->resource['next_heartbeat'],
        ];
    }
}
