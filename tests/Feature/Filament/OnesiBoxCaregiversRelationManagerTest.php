<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Filament\Resources\OnesiBoxes\Pages\EditOnesiBox;
use App\Filament\Resources\OnesiBoxes\RelationManagers\CaregiversRelationManager;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oltrematica\RoleLite\Models\Role;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);

    $this->onesiBox = OnesiBox::factory()->create();

    $this->caregiver = User::factory()->create(['name' => 'Test Caregiver']);
    $this->caregiver->assignRole('caregiver');
});

// ============================================================================
// Render Tests
// ============================================================================

describe('Render CaregiversRelationManager', function (): void {
    it('can render the CaregiversRelationManager', function (): void {
        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertOk();
    });

    it('displays empty state when no caregivers exist', function (): void {
        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([]);
    });

    it('displays assigned caregivers in the table', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertCanSeeTableRecords([$this->caregiver]);
    });
});

// ============================================================================
// Attach Caregiver Tests
// ============================================================================

describe('Attach Caregiver', function (): void {
    it('can attach a caregiver with full permission', function (): void {
        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('attach', data: [
                'recordId' => $this->caregiver->id,
                'permission' => OnesiBoxPermission::Full->value,
            ])
            ->assertHasNoTableActionErrors();

        expect($this->onesiBox->caregivers()->count())->toBe(1)
            ->and($this->onesiBox->caregivers()->first()->id)->toBe($this->caregiver->id);
    });

    it('can attach a caregiver with read-only permission', function (): void {
        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('attach', data: [
                'recordId' => $this->caregiver->id,
                'permission' => OnesiBoxPermission::ReadOnly->value,
            ])
            ->assertHasNoTableActionErrors();

        $assignedCaregiver = $this->onesiBox->caregivers()->first();
        expect($assignedCaregiver->pivot->permission)->toBe(OnesiBoxPermission::ReadOnly);
    });

    it('logs activity when caregiver is attached', function (): void {
        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('attach', data: [
                'recordId' => $this->caregiver->id,
                'permission' => OnesiBoxPermission::Full->value,
            ]);

        $activity = Activity::query()
            ->where('subject_type', OnesiBox::class)
            ->where('subject_id', $this->onesiBox->id)
            ->where('description', 'Caregiver assigned')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($this->admin->id)
            ->and($activity->properties['caregiver_id'])->toBe($this->caregiver->id)
            ->and($activity->properties['caregiver_name'])->toBe($this->caregiver->name);
    });

    it('only shows caregivers in the attach select', function (): void {
        $nonCaregiver = User::factory()->create(['name' => 'Non Caregiver User']);
        $nonCaregiver->assignRole('admin');

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->mountTableAction('attach')
            ->assertTableActionDataSet([]);
    });
});

// ============================================================================
// Edit Permission Tests
// ============================================================================

describe('Edit Caregiver Permission', function (): void {
    it('can edit caregiver permission', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('edit', $this->caregiver, data: [
                'permission' => OnesiBoxPermission::ReadOnly->value,
            ])
            ->assertHasNoTableActionErrors();

        $this->onesiBox->refresh();
        $updatedCaregiver = $this->onesiBox->caregivers()->first();
        expect($updatedCaregiver->pivot->permission)->toBe(OnesiBoxPermission::ReadOnly);
    });

    it('logs activity when caregiver permission is updated', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('edit', $this->caregiver, data: [
                'permission' => OnesiBoxPermission::ReadOnly->value,
            ]);

        $activity = Activity::query()
            ->where('subject_type', OnesiBox::class)
            ->where('subject_id', $this->onesiBox->id)
            ->where('description', 'Caregiver permission updated')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($this->admin->id)
            ->and($activity->properties['caregiver_id'])->toBe($this->caregiver->id);
    });
});

// ============================================================================
// Detach Caregiver Tests
// ============================================================================

describe('Detach Caregiver', function (): void {
    it('can detach a caregiver', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        expect($this->onesiBox->caregivers()->count())->toBe(1);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('detach', $this->caregiver)
            ->assertHasNoTableActionErrors();

        expect($this->onesiBox->caregivers()->count())->toBe(0);
    });

    it('logs activity when caregiver is detached', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('detach', $this->caregiver);

        $activity = Activity::query()
            ->where('subject_type', OnesiBox::class)
            ->where('subject_id', $this->onesiBox->id)
            ->where('description', 'Caregiver removed')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($this->admin->id)
            ->and($activity->properties['caregiver_id'])->toBe($this->caregiver->id)
            ->and($activity->properties['caregiver_name'])->toBe($this->caregiver->name);
    });
});

// ============================================================================
// Table Columns Tests
// ============================================================================

describe('Table Columns', function (): void {
    it('displays name column', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertTableColumnExists('name');
    });

    it('displays email column', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertTableColumnExists('email');
    });

    it('displays permission column', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertTableColumnExists('permission');
    });
});

// ============================================================================
// Multiple Caregivers Tests
// ============================================================================

describe('Multiple Caregivers', function (): void {
    it('can display multiple caregivers', function (): void {
        $caregiver2 = User::factory()->create(['name' => 'Second Caregiver']);
        $caregiver2->assignRole('caregiver');

        $this->onesiBox->caregivers()->attach([
            $this->caregiver->id => ['permission' => OnesiBoxPermission::Full->value],
            $caregiver2->id => ['permission' => OnesiBoxPermission::ReadOnly->value],
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertCanSeeTableRecords([$this->caregiver, $caregiver2]);
    });

    it('can search caregivers by name', function (): void {
        $caregiver2 = User::factory()->create(['name' => 'Different Name']);
        $caregiver2->assignRole('caregiver');

        $this->onesiBox->caregivers()->attach([
            $this->caregiver->id => ['permission' => OnesiBoxPermission::Full->value],
            $caregiver2->id => ['permission' => OnesiBoxPermission::ReadOnly->value],
        ]);

        livewire(CaregiversRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->searchTable('Test Caregiver')
            ->assertCanSeeTableRecords([$this->caregiver])
            ->assertCanNotSeeTableRecords([$caregiver2]);
    });
});
