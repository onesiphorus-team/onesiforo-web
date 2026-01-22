<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);
});

it('creates a user without roles and sends verification email', function (): void {
    Notification::fake();

    artisan('app:create-user')
        ->expectsQuestion('Nome completo', 'Test User')
        ->expectsQuestion('Email', 'user@test.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Ruoli', [])
        ->assertSuccessful();

    $user = User::query()->where('email', 'user@test.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test User')
        ->and($user->roles)->toHaveCount(0)
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('creates a user with single role', function (): void {
    artisan('app:create-user')
        ->expectsQuestion('Nome completo', 'Admin User')
        ->expectsQuestion('Email', 'admin@test.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Ruoli', ['admin'])
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin@test.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('admin'))->toBeTrue()
        ->and($user->hasRole('super-admin'))->toBeFalse();
});

it('creates a user with multiple roles', function (): void {
    artisan('app:create-user')
        ->expectsQuestion('Nome completo', 'Multi Role User')
        ->expectsQuestion('Email', 'multi@test.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Ruoli', ['admin', 'caregiver'])
        ->assertSuccessful();

    $user = User::query()->where('email', 'multi@test.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('admin'))->toBeTrue()
        ->and($user->hasRole('caregiver'))->toBeTrue();
});

it('hashes the password correctly', function (): void {
    artisan('app:create-user')
        ->expectsQuestion('Nome completo', 'Test User')
        ->expectsQuestion('Email', 'hashtest@test.com')
        ->expectsQuestion('Password', 'mypassword')
        ->expectsQuestion('Ruoli', [])
        ->assertSuccessful();

    $user = User::query()->where('email', 'hashtest@test.com')->first();

    expect($user->password)->not->toBe('mypassword')
        ->and(password_verify('mypassword', (string) $user->password))->toBeTrue();
});

it('prevents creating user with existing email', function (): void {
    User::factory()->create(['email' => 'existing@test.com']);

    expect(User::query()->where('email', 'existing@test.com')->count())->toBe(1);
});
