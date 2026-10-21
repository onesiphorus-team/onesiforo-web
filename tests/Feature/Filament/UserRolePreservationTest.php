<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'caregiver'] as $role) {
        Role::query()->firstOrCreate(['name' => $role]);
    }
});

it('preserves protected roles when an admin edits personal information', function (string $role): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $user->id])
        ->fillForm(['name' => 'Updated Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Updated Name')
        ->and($user->hasRole($role))->toBeTrue();
})->with(['admin']);

it('ignores tampered protected roles submitted by an admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $user->id])
        ->fillForm(['name' => 'Updated Name', 'roles' => ['caregiver']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->roles->pluck('name')->all())->toBe(['admin']);
});

it('prevents an admin from editing a super admin account', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $user->id])->assertForbidden();
});

it('allows a super admin to edit another super admin account', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $user->id])
        ->fillForm(['name' => 'Updated Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Updated Name')
        ->and($user->hasRole('super-admin'))->toBeTrue();
});

it('allows a super admin to explicitly remove all roles', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $user = User::factory()->create();
    $user->assignRole('caregiver');
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $user->id])
        ->fillForm(['roles' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->roles)->toBeEmpty();
});

it('rejects removing the last super admin role before saving personal information', function (): void {
    $admin = User::factory()->create(['name' => 'Original Name']);
    $admin->assignRole('super-admin');
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $admin->id])
        ->fillForm(['name' => 'Forbidden Change', 'roles' => ['admin']])
        ->call('save')
        ->assertForbidden();

    expect($admin->refresh()->name)->toBe('Original Name')
        ->and($admin->roles->pluck('name')->all())->toBe(['super-admin']);
});

it('allows removing a super admin role when another active super admin remains', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('super-admin');
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $admin->id])
        ->fillForm(['name' => 'Updated Name', 'roles' => ['admin']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->name)->toBe('Updated Name')
        ->and($admin->roles->pluck('name')->all())->toBe(['admin'])
        ->and($otherAdmin->refresh()->hasRole('super-admin'))->toBeTrue();
});

it('rechecks remaining super admins when roles change after the edit form loads', function (): void {
    $admin = User::factory()->create(['name' => 'Original Name']);
    $admin->assignRole('super-admin');
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('super-admin');
    $this->actingAs($admin);

    $page = livewire(EditUser::class, ['record' => $admin->id])
        ->fillForm(['name' => 'Forbidden Change', 'roles' => []]);

    $otherAdmin->removeRole('super-admin');

    $page->call('save')->assertForbidden();

    expect($admin->refresh()->name)->toBe('Original Name')
        ->and($admin->hasRole('super-admin'))->toBeTrue();
});
