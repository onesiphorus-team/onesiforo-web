<?php

declare(strict_types=1);

use App\Listeners\UpdateLastLogin;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;

it('records the login timestamp on the user when handling a Login event', function (): void {
    $user = User::factory()->create(['last_login_at' => null]);

    Carbon::setTestNow(Carbon::parse('2026-04-26 10:30:00', 'UTC'));

    (new UpdateLastLogin)->handle(new Login('web', $user, false));

    expect($user->fresh()->last_login_at?->toDateTimeString())
        ->toBe('2026-04-26 10:30:00');

    Carbon::setTestNow();
});

it('does not touch the user record when the authenticatable is not a User (e.g. OnesiBox)', function (): void {
    $box = OnesiBox::factory()->create();
    $user = User::factory()->create(['last_login_at' => null]);

    (new UpdateLastLogin)->handle(new Login('sanctum', $box, false));

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('is wired to the Login event in AppServiceProvider', function (): void {
    expect(Illuminate\Support\Facades\Event::hasListeners(Login::class))->toBeTrue();
});
