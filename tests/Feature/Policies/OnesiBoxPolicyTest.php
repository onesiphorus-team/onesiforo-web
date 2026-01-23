<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\User;
use App\Policies\OnesiBoxPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oltrematica\RoleLite\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);

    $this->policy = new OnesiBoxPolicy;
});

describe('viewAny', function (): void {
    it('allows any authenticated user to view onesibox list', function (): void {
        $user = User::factory()->create();

        expect($this->policy->viewAny($user))->toBeTrue();
    });
});

describe('view', function (): void {
    it('allows caregiver with full permission to view', function (): void {
        $user = User::factory()->create();
        $onesiBox = OnesiBox::factory()->create();
        $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

        expect($this->policy->view($user, $onesiBox))->toBeTrue();
    });

    it('allows caregiver with read-only permission to view', function (): void {
        $user = User::factory()->create();
        $onesiBox = OnesiBox::factory()->create();
        $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

        expect($this->policy->view($user, $onesiBox))->toBeTrue();
    });

    it('denies non-caregiver from viewing', function (): void {
        $user = User::factory()->create();
        $onesiBox = OnesiBox::factory()->create();

        expect($this->policy->view($user, $onesiBox))->toBeFalse();
    });
});

describe('control', function (): void {
    it('allows caregiver with full permission to control', function (): void {
        $user = User::factory()->create();
        $onesiBox = OnesiBox::factory()->create();
        $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

        expect($this->policy->control($user, $onesiBox))->toBeTrue();
    });

    it('denies caregiver with read-only permission from controlling', function (): void {
        $user = User::factory()->create();
        $onesiBox = OnesiBox::factory()->create();
        $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

        expect($this->policy->control($user, $onesiBox))->toBeFalse();
    });

    it('denies non-caregiver from controlling', function (): void {
        $user = User::factory()->create();
        $onesiBox = OnesiBox::factory()->create();

        expect($this->policy->control($user, $onesiBox))->toBeFalse();
    });
});

describe('create', function (): void {
    it('allows super-admin to create', function (): void {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows admin to create', function (): void {
        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($this->policy->create($user))->toBeTrue();
    });

    it('denies caregiver from creating', function (): void {
        $user = User::factory()->create();
        $user->assignRole('caregiver');

        expect($this->policy->create($user))->toBeFalse();
    });

    it('denies regular user from creating', function (): void {
        $user = User::factory()->create();

        expect($this->policy->create($user))->toBeFalse();
    });
});

describe('update', function (): void {
    it('allows super-admin to update', function (): void {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $onesiBox = OnesiBox::factory()->create();

        expect($this->policy->update($user, $onesiBox))->toBeTrue();
    });

    it('allows admin to update', function (): void {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $onesiBox = OnesiBox::factory()->create();

        expect($this->policy->update($user, $onesiBox))->toBeTrue();
    });

    it('denies caregiver from updating', function (): void {
        $user = User::factory()->create();
        $user->assignRole('caregiver');
        $onesiBox = OnesiBox::factory()->create();
        $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

        expect($this->policy->update($user, $onesiBox))->toBeFalse();
    });
});

describe('delete', function (): void {
    it('allows super-admin to delete', function (): void {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $onesiBox = OnesiBox::factory()->create();

        expect($this->policy->delete($user, $onesiBox))->toBeTrue();
    });

    it('denies admin from deleting', function (): void {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $onesiBox = OnesiBox::factory()->create();

        expect($this->policy->delete($user, $onesiBox))->toBeFalse();
    });

    it('denies caregiver from deleting', function (): void {
        $user = User::factory()->create();
        $user->assignRole('caregiver');
        $onesiBox = OnesiBox::factory()->create();

        expect($this->policy->delete($user, $onesiBox))->toBeFalse();
    });
});

describe('deleteAny', function (): void {
    it('allows super-admin to bulk delete', function (): void {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        expect($this->policy->deleteAny($user))->toBeTrue();
    });

    it('denies admin from bulk deleting', function (): void {
        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($this->policy->deleteAny($user))->toBeFalse();
    });
});

describe('forceDeleteAny', function (): void {
    it('allows super-admin to force bulk delete', function (): void {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        expect($this->policy->forceDeleteAny($user))->toBeTrue();
    });

    it('denies admin from force bulk deleting', function (): void {
        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($this->policy->forceDeleteAny($user))->toBeFalse();
    });
});
