<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    // Ensure roles exist
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);
});

describe('super-admin invite action', function (): void {
    beforeEach(function (): void {
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');
        $this->actingAs($this->superAdmin);
    });

    it('can see the invite action', function (): void {
        livewire(ListUsers::class)
            ->assertActionVisible(TestAction::make('invite'));
    });

    it('can invite a user with admin role', function (): void {
        Notification::fake();

        livewire(ListUsers::class)
            ->callAction('invite', [
                'name' => 'New Admin User',
                'email' => 'newadmin@example.com',
                'role' => 'admin',
            ])
            ->assertNotified();

        $invitedUser = User::query()->where('email', 'newadmin@example.com')->first();

        expect($invitedUser)->not->toBeNull()
            ->and($invitedUser->name)->toBe('New Admin User')
            ->and($invitedUser->hasRole('admin'))->toBeTrue();

        Notification::assertSentTo($invitedUser, UserInvitedNotification::class);
    });

    it('can invite a user with caregiver role', function (): void {
        Notification::fake();

        livewire(ListUsers::class)
            ->callAction('invite', [
                'name' => 'New Caregiver',
                'email' => 'caregiver@example.com',
                'role' => 'caregiver',
            ])
            ->assertNotified();

        $invitedUser = User::query()->where('email', 'caregiver@example.com')->first();

        expect($invitedUser)->not->toBeNull()
            ->and($invitedUser->hasRole('caregiver'))->toBeTrue();

        Notification::assertSentTo($invitedUser, UserInvitedNotification::class);
    });

    it('can invite a user with super-admin role', function (): void {
        Notification::fake();

        livewire(ListUsers::class)
            ->callAction('invite', [
                'name' => 'New Super Admin',
                'email' => 'newsuperadmin@example.com',
                'role' => 'super-admin',
            ])
            ->assertNotified();

        $invitedUser = User::query()->where('email', 'newsuperadmin@example.com')->first();

        expect($invitedUser)->not->toBeNull()
            ->and($invitedUser->hasRole('super-admin'))->toBeTrue();
    });

    it('cannot invite with an existing email', function (): void {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        livewire(ListUsers::class)
            ->callAction('invite', [
                'name' => 'Duplicate User',
                'email' => 'existing@example.com',
                'role' => 'caregiver',
            ])
            ->assertHasActionErrors(['email' => 'unique']);
    });

    it('validates required fields', function (): void {
        livewire(ListUsers::class)
            ->callAction('invite', [
                'name' => '',
                'email' => '',
                'role' => '',
            ])
            ->assertHasActionErrors([
                'name' => 'required',
                'email' => 'required',
                'role' => 'required',
            ]);
    });

    it('validates email format', function (): void {
        livewire(ListUsers::class)
            ->callAction('invite', [
                'name' => 'Test User',
                'email' => 'invalid-email',
                'role' => 'caregiver',
            ])
            ->assertHasActionErrors(['email' => 'email']);
    });
});

describe('admin invite action', function (): void {
    beforeEach(function (): void {
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    });

    it('can see the invite action', function (): void {
        livewire(ListUsers::class)
            ->assertActionVisible(TestAction::make('invite'));
    });

    it('can only invite users with caregiver role', function (): void {
        Notification::fake();

        livewire(ListUsers::class)
            ->callAction('invite', [
                'name' => 'New Caregiver',
                'email' => 'caregiver@example.com',
                'role' => 'caregiver',
            ])
            ->assertNotified();

        $invitedUser = User::query()->where('email', 'caregiver@example.com')->first();

        expect($invitedUser)->not->toBeNull()
            ->and($invitedUser->hasRole('caregiver'))->toBeTrue();
    });
});
