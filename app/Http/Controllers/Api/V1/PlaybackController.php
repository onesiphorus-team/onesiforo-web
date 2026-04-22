<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sessions\AdvancePlaybackSessionAction;
use App\Actions\StorePlaybackEventAction;
use App\Enums\PlaybackEventType;
use App\Events\PlaybackEventReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlaybackEventRequest;
use App\Http\Resources\Api\V1\PlaybackEventResource;

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
    public function store(
        PlaybackEventRequest $request,
        StorePlaybackEventAction $storeAction,
        AdvancePlaybackSessionAction $advanceAction,
    ): PlaybackEventResource {
        $onesiBox = $request->onesiBox();

        /** @var array{event: string, media_url: string, media_type: string, position?: int|null, duration?: int|null, error_message?: string|null, error_code?: string|null, session_id?: string|null} $data */
        $data = $request->validated();
        $playbackEvent = $storeAction->fromArray($onesiBox, $data);

        broadcast(new PlaybackEventReceived($playbackEvent));

        $eventType = $playbackEvent->event;

        if ($eventType === PlaybackEventType::Completed || $eventType === PlaybackEventType::Error) {
            $advanceAction->execute($onesiBox, $eventType, $data['media_url']);
        }

        return new PlaybackEventResource([
            'logged' => true,
            'event_id' => $playbackEvent->id,
        ]);
    }
}
