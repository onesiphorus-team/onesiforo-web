<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\User;

test('guests are redirected to the login page', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function (): void {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('unverified users are redirected to email verification', function (): void {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/dashboard')->assertRedirect('/email/verify');
});

test('users without roles see the pending activation message', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Account in attesa di attivazione');
});

test('users with roles do not see the pending activation message', function (): void {
    $this->actingAs(User::factory()->role(Roles::Caregiver)->create());

    $this->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Account in attesa di attivazione');
});

test('admin users see the admin area link', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Accedi all\'area amministrazione');
});

test('super admin users see the admin area link', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Accedi all\'area amministrazione');
});

test('non-admin users do not see the admin area link', function (): void {
    $this->actingAs(User::factory()->role(Roles::Caregiver)->create());

    $this->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Accedi all\'area amministrazione');
});
