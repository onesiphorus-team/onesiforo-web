<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlaybackEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlaybackEventRequest;
use App\Http\Resources\Api\V1\PlaybackEventResource;
use App\Models\PlaybackEvent;

/**
 * Handles playback event operations for OnesiBox appliances.
 *
 * @tags Playback
 */
class PlaybackController extends Controller
{
    /**
     * Record a playback event from the authenticated appliance.
     *
     * Notifies the server of playback state changes (started, paused, resumed, stopped, completed, error).
     * Events are persisted for 30 days for analytics and debugging.
     *
     * POST /api/v1/appliances/playback
     *
     * @response array{data: array{logged: bool, event_id: int}}
     * @response 401 array{message: string}
     * @response 403 array{message: string, error_code: string}
     * @response 422 array{message: string, errors: array}
     */
    public function store(PlaybackEventRequest $request): PlaybackEventResource
    {
        $onesiBox = $request->onesiBox();

        $playbackEvent = PlaybackEvent::query()->create([
            'onesi_box_id' => $onesiBox->id,
            'event' => PlaybackEventType::from($request->input('event')),
            'media_url' => $request->input('media_url'),
            'media_type' => $request->input('media_type'),
            'position' => $request->input('position'),
            'duration' => $request->input('duration'),
            'error_message' => $request->input('error_message'),
        ]);

        return new PlaybackEventResource([
            'logged' => true,
            'event_id' => $playbackEvent->id,
        ]);
    }
}
