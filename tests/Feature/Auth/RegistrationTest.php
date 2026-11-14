<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Accounts are created by administrators (invitations), so nobody can sign up on their own:
 * a self-registered user would only land on an "awaiting activation" page.
 */

test('the registration screen is not available', function (): void {
    $this->get('/register')->assertNotFound();
});

test('nobody can register an account on their own', function (): void {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('the login page does not offer to sign up', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Registrati');
});
