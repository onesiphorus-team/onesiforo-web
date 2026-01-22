<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CommandController;
use App\Http\Controllers\Api\V1\HeartbeatController;
use App\Http\Controllers\Api\V1\PlaybackController;
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
    Route::middleware('auth:sanctum')
        ->prefix('appliances')
        ->name('appliances.')
        ->group(function (): void {
            Route::post('/heartbeat', [HeartbeatController::class, 'store'])
                ->name('heartbeat');

            Route::get('/commands', [CommandController::class, 'index'])
                ->name('commands');

            Route::post('/playback', [PlaybackController::class, 'store'])
                ->name('playback');
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
    Route::middleware('auth:sanctum')
        ->prefix('commands')
        ->name('commands.')
        ->group(function (): void {
            Route::post('/{command}/ack', [CommandController::class, 'acknowledge'])
                ->name('ack');
        });
});
