<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CommandController;
use App\Http\Controllers\Api\V1\HeartbeatController;
use App\Http\Controllers\Api\V1\PlaybackController;
use App\Http\Controllers\Api\V1\ScreenshotController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Broadcasting auth endpoint for appliances using Sanctum token auth.
// Appliances POST to /api/broadcasting/auth to authorize private channel subscriptions.
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | OnesiBox Appliance Routes
    |--------------------------------------------------------------------------
    |
    | These routes handle communication with OnesiBox appliances.
    | Authentication is done via Sanctum API tokens owned by the appliance.
    | The token identifies the OnesiBox - no ID needed in the URL.
    |
    */
    Route::middleware(['auth:sanctum', 'appliance.active'])
        ->prefix('appliances')
        ->name('appliances.')
        ->group(function (): void {
            Route::post('/heartbeat', [HeartbeatController::class, 'store'])
                ->middleware('throttle:heartbeat')
                ->name('heartbeat');

            Route::get('/commands', [CommandController::class, 'index'])
                ->middleware('throttle:commands')
                ->name('commands');

            Route::post('/playback', [PlaybackController::class, 'store'])
                ->middleware('throttle:playback')
                ->name('playback');

            Route::post('/screenshot', [ScreenshotController::class, 'store'])
                ->middleware('throttle:screenshot-upload')
                ->name('screenshot.store');
        });

    /*
    |--------------------------------------------------------------------------
    | Command Routes
    |--------------------------------------------------------------------------
    |
    | Routes for command acknowledgment.
    | Uses UUID route key binding for the command parameter.
    |
    */
    Route::middleware(['auth:sanctum', 'appliance.active'])
        ->prefix('commands')
        ->name('commands.')
        ->group(function (): void {
            Route::post('/{command}/ack', [CommandController::class, 'acknowledge'])
                ->middleware('throttle:command-ack')
                ->name('ack');
        });

    /*
    |--------------------------------------------------------------------------
    | Screenshot Download Route
    |--------------------------------------------------------------------------
    |
    | Serves stored diagnostic screenshots. Uses signed URLs with short expiry
    | so admin and caregiver dashboards can embed <img src> without exposing
    | a permanent download link.
    |
    */
    // The browser <img src> for the caregiver carousel uses a session cookie.
    // routes/api.php doesn't include the 'web' middleware group, so StartSession
    // is not loaded here by default — without it 'auth:web' can never see a
    // session even when one is valid. Mounting the 'web' group on this single
    // route loads StartSession + cookie encryption + auth driver wiring, then
    // the multi-guard auth:sanctum,web resolves either Bearer tokens or
    // session cookies. 'signed' still gates URL signature + expiry.
    Route::get('/screenshots/{screenshot}', [ScreenshotController::class, 'show'])
        ->middleware(['web', 'auth:sanctum,web', 'signed'])
        ->name('screenshots.show');
});
