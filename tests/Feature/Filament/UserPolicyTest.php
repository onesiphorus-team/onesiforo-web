<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    // Ensure roles exist
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);
});

describe('role management by super-admin', function (): void {
    beforeEach(function (): void {
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');
        $this->actingAs($this->superAdmin);
    });

    it('can see all role options in the edit form', function (): void {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->assertFormFieldExists('roles');
    });

    it('can assign admin role to a user', function (): void {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'roles' => ['admin'],
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();
        expect($user->hasRole('admin'))->toBeTrue();
    });

    it('can assign caregiver role to a user', function (): void {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'roles' => ['caregiver'],
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();
        expect($user->hasRole('caregiver'))->toBeTrue();
    });

    it('can assign super-admin role to a user', function (): void {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'roles' => ['super-admin'],
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();
        expect($user->hasRole('super-admin'))->toBeTrue();
    });

    it('can assign multiple roles to a user', function (): void {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'roles' => ['admin', 'caregiver'],
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();
        expect($user->hasRole('admin'))->toBeTrue()
            ->and($user->hasRole('caregiver'))->toBeTrue();
    });

    it('can remove a role from a user', function (): void {
        $user = User::factory()->create();
        $user->assignRole('admin');

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'roles' => [],
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();
        expect($user->hasRole('admin'))->toBeFalse();
    });
});

describe('role management by admin', function (): void {
    beforeEach(function (): void {
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    });

    it('can see role field in edit form', function (): void {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->assertFormFieldExists('roles');
    });

    it('can assign caregiver role', function (): void {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'roles' => ['caregiver'],
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();
        expect($user->hasRole('caregiver'))->toBeTrue();
    });

    it('cannot assign admin role (role is filtered out of options)', function (): void {
        $user = User::factory()->create();

        // Admin can only see caregiver in the options
        // Even if admin tries to submit with admin role, it will be filtered out by validation
        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'roles' => ['admin'],
            ])
            ->call('save');

        // The role should not be assigned because it's not in the available options
        $user->refresh();
        expect($user->hasRole('admin'))->toBeFalse();
    });

    it('cannot assign super-admin role (role is filtered out of options)', function (): void {
        $user = User::factory()->create();

        // Admin can only see caregiver in the options
        // Even if admin tries to submit with super-admin role, it will be filtered out by validation
        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'roles' => ['super-admin'],
            ])
            ->call('save');

        // The role should not be assigned because it's not in the available options
        $user->refresh();
        expect($user->hasRole('super-admin'))->toBeFalse();
    });

    it('cannot edit roles of an admin user', function (): void {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');

        // The roles field should be disabled for admin users when edited by another admin
        livewire(EditUser::class, ['record' => $adminUser->id])
            ->assertFormFieldIsDisabled('roles');
    });

    it('cannot edit roles of a super-admin user', function (): void {
        $superAdminUser = User::factory()->create();
        $superAdminUser->assignRole('super-admin');

        // The roles field should be disabled for super-admin users when edited by admin
        livewire(EditUser::class, ['record' => $superAdminUser->id])
            ->assertFormFieldIsDisabled('roles');
    });
});

// User deletion tests (US9)
describe('delete user by super-admin', function (): void {
    beforeEach(function (): void {
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');
        $this->actingAs($this->superAdmin);
    });

    it('can soft delete another user', function (): void {
        $user = User::factory()->create();

        livewire(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->callTableAction(Filament\Actions\DeleteAction::class, $user)
            ->assertNotified();

        expect(User::query()->find($user->id))->toBeNull()
            ->and(User::withTrashed()->find($user->id))->not->toBeNull();
    });

    it('can restore a soft deleted user', function (): void {
        $user = User::factory()->create();
        $user->delete();

        livewire(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->filterTable('trashed', true)
            ->callTableAction(Filament\Actions\RestoreAction::class, $user)
            ->assertNotified();

        expect(User::query()->find($user->id))->not->toBeNull();
    });

    it('can force delete a soft deleted user', function (): void {
        $user = User::factory()->create();
        $user->delete();

        livewire(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->filterTable('trashed', true)
            ->callTableAction(Filament\Actions\ForceDeleteAction::class, $user)
            ->assertNotified();

        expect(User::withTrashed()->find($user->id))->toBeNull();
    });

    it('cannot delete themselves', function (): void {
        // The delete action should not be visible for the current user
        livewire(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->assertTableActionHidden(Filament\Actions\DeleteAction::class, $this->superAdmin);
    });

    it('can force delete a super-admin when another one exists', function (): void {
        // Create another super-admin to delete
        $otherSuperAdmin = User::factory()->create();
        $otherSuperAdmin->assignRole('super-admin');

        // Create a third super-admin so we have 3 total
        $thirdSuperAdmin = User::factory()->create();
        $thirdSuperAdmin->assignRole('super-admin');

        // Soft delete the other super-admin first
        $otherSuperAdmin->delete();

        // Force delete should work because there are still 2 active super-admins
        livewire(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->filterTable('trashed', true)
            ->callTableAction(Filament\Actions\ForceDeleteAction::class, $otherSuperAdmin)
            ->assertNotified();

        expect(User::withTrashed()->find($otherSuperAdmin->id))->toBeNull();
    });
});

describe('delete user by admin', function (): void {
    beforeEach(function (): void {
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    });

    it('cannot see delete action', function (): void {
        $user = User::factory()->create();

        livewire(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->assertTableActionHidden(Filament\Actions\DeleteAction::class, $user);
    });

    it('cannot see force delete action', function (): void {
        $user = User::factory()->create();
        $user->delete();

        livewire(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->filterTable('trashed', true)
            ->assertTableActionHidden(Filament\Actions\ForceDeleteAction::class, $user);
    });

    it('cannot see restore action', function (): void {
        $user = User::factory()->create();
        $user->delete();

        livewire(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->filterTable('trashed', true)
            ->assertTableActionHidden(Filament\Actions\RestoreAction::class, $user);
    });
});

// Policy unit tests
describe('user policy delete methods', function (): void {
    it('allows super-admin to delete other users', function (): void {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $user = User::factory()->create();

        $policy = new App\Policies\UserPolicy;
        expect($policy->delete($superAdmin, $user))->toBeTrue();
    });

    it('prevents super-admin from deleting themselves', function (): void {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $policy = new App\Policies\UserPolicy;
        expect($policy->delete($superAdmin, $superAdmin))->toBeFalse();
    });

    it('prevents admin from deleting users', function (): void {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();

        $policy = new App\Policies\UserPolicy;
        expect($policy->delete($admin, $user))->toBeFalse();
    });

    it('allows force deleting a super-admin when others exist', function (): void {
        // Create two super-admins
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $otherSuperAdmin = User::factory()->create();
        $otherSuperAdmin->assignRole('super-admin');

        $policy = new App\Policies\UserPolicy;

        // Should be able to force delete because there are 2 active super-admins
        expect($policy->forceDelete($superAdmin, $otherSuperAdmin))->toBeTrue();
    });

    it('prevents force deleting when it would leave no active super-admin', function (): void {
        // Create only one super-admin
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        // Create another user who is also super-admin but will be soft-deleted
        $otherSuperAdmin = User::factory()->create();
        $otherSuperAdmin->assignRole('super-admin');

        // Soft delete the other super-admin, leaving only 1 active
        $otherSuperAdmin->delete();

        $policy = new App\Policies\UserPolicy;

        // Cannot force delete the soft-deleted super-admin because the model has super-admin role
        // and there's only 1 active super-admin left (the current user)
        expect($policy->forceDelete($superAdmin, $otherSuperAdmin))->toBeFalse();
    });
});
