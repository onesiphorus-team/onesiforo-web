<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('/deployment-health', App\Http\Controllers\DeploymentHealthController::class)
    ->withoutMiddleware('web')
    ->middleware('throttle:30,1')
    ->name('deployment.health');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', App\Livewire\Dashboard\OnesiBoxList::class)
        ->name('dashboard');

    Route::get('dashboard/{onesiBox}', App\Livewire\Dashboard\OnesiBoxDetail::class)
        ->name('dashboard.show');
});

require __DIR__.'/settings.php';
