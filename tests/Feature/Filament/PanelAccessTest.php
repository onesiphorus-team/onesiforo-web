<?php

declare(strict_types=1);

use App\Models\User;
use Oltrematica\RoleLite\Models\Role;

beforeEach(function (): void {
    // Ensure roles exist
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);
});

it('allows super-admin to access admin panel', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

it('allows admin to access admin panel', function (): void {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

it('denies caregiver access to admin panel', function (): void {
    $user = User::factory()->create();
    $user->assignRole('caregiver');

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('denies user without role access to admin panel', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('redirects unauthenticated user to login', function (): void {
    $this->get('/admin')
        ->assertRedirect('/login');
});

it('allows user with both admin and caregiver roles to access panel', function (): void {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $user->assignRole('caregiver');

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});
