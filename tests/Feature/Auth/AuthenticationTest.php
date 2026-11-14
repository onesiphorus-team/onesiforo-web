<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function (): void {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function (): void {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

/**
 * The "remember me" checkbox tag of the login page, as rendered.
 */
function rememberCheckboxTag(Illuminate\Testing\TestResponse $response): string
{
    preg_match('/<[^>]*name="remember"[^>]*>/', (string) $response->getContent(), $matches);

    return $matches[0] ?? '';
}

test('remember me is ticked by default', function (): void {
    expect(rememberCheckboxTag($this->get(route('login'))))->toContain(' checked');
});

test('remember me stays unticked after a failed login where the user unticked it', function (): void {
    $user = User::factory()->create();

    $this->from(route('login'))->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    expect(rememberCheckboxTag($this->get(route('login'))))->not->toBeEmpty()->not->toContain(' checked');
});

test('remember me stays ticked after a failed login where the user left it ticked', function (): void {
    $user = User::factory()->create();

    $this->from(route('login'))->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password', 'remember' => 'on']);

    expect(rememberCheckboxTag($this->get(route('login'))))->toContain(' checked');
});
