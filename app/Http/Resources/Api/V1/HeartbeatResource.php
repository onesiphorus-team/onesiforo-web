<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\OnesiBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for heartbeat API responses.
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
     * @param  array{server_time: \Carbon\CarbonInterface, next_heartbeat: int, screenshot_enabled: bool, screenshot_interval_seconds: int}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Create a successful heartbeat response for a given OnesiBox.
     *
     * Includes diagnostic screenshot config so the client can apply on-the-fly
     * enable/disable and interval changes pushed from admin.
     */
    public static function success(OnesiBox $box, int $nextHeartbeat = self::DEFAULT_HEARTBEAT_INTERVAL): self
    {
        return new self([
            'server_time' => now(),
            'next_heartbeat' => $nextHeartbeat,
            'screenshot_enabled' => $box->screenshot_enabled,
            'screenshot_interval_seconds' => $box->screenshot_interval_seconds,
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
            'screenshot_enabled' => (bool) $this->resource['screenshot_enabled'],
            'screenshot_interval_seconds' => (int) $this->resource['screenshot_interval_seconds'],
        ];
    }
}
