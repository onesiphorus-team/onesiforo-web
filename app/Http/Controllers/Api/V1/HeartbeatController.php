<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessHeartbeatAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HeartbeatRequest;
use App\Http\Resources\Api\V1\HeartbeatResource;

/**
 * Handles heartbeat signals from OnesiBox appliances.
 */
class HeartbeatController extends Controller
{
    /**
     * Record a heartbeat from an OnesiBox appliance.
     *
     * POST /api/v1/appliances/heartbeat
     *
     * The OnesiBox is identified by the Sanctum token - no ID in URL needed.
     */
    public function store(HeartbeatRequest $request, ProcessHeartbeatAction $action): HeartbeatResource
    {
        $box = $request->onesiBox();
        $action($box, $request->validated());

        return HeartbeatResource::success($box->fresh());
    }
}
