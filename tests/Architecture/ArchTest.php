<?php

declare(strict_types=1);

use App\Traits\LogsActivityAllDirty;

it('finds missing debug statements', function (): void {
    // Act & Assert
    expect(['dd', 'dump', 'ray', 'die'])
        ->not()
        ->toBeUsed();
});

test('all models use trait LogsActivityAllDirty', function (): void {
    expect('App\Models')
        ->toUseTrait(LogsActivityAllDirty::class)
        ->ignoring(App\Models\PageVisit::class)
        ->ignoring(App\Models\ApplianceScreenshot::class) // high-volume telemetry (~1/min per box), exempt for same reason as PageVisit
        ->ignoring('App\Models\Scopes');
});

test('all policies to have methods deleteAny and forceDeleteAny', function (): void {
    expect('App\Policies')
        ->toHaveMethods(['deleteAny', 'forceDeleteAny']);
});

arch()->preset()->php();
arch()->preset()->laravel()
    ->ignoring(App\Http\Controllers\Api\V1\CommandController::class); // Has action method 'acknowledge' for POST /commands/{uuid}/ack
arch()->preset()->security();
