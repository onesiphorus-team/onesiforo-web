<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HeartbeatRequest;
use App\Http\Resources\Api\V1\HeartbeatResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
    public function store(HeartbeatRequest $request): HeartbeatResource|JsonResponse
    {
        $onesiBox = $request->onesiBox();

        if (! $onesiBox->is_active) {
            return response()->json([
                'message' => 'Appliance disabilitata.',
                'error_code' => 'E002',
            ], Response::HTTP_FORBIDDEN);
        }

        $onesiBox->recordHeartbeat();

        return HeartbeatResource::success();
    }
}
