<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\OnesiBoxUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oltrematica\RoleLite\Models\Role;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);

    $this->onesiBox = OnesiBox::factory()->create();
    $this->caregiver = User::factory()->create();
    $this->caregiver->assignRole('caregiver');
});

describe('OnesiBoxUser Pivot Model', function (): void {
    it('has correct table name', function (): void {
        $pivot = new OnesiBoxUser;

        expect($pivot->getTable())->toBe('onesi_box_user');
    });

    it('has incrementing id', function (): void {
        $pivot = new OnesiBoxUser;

        expect($pivot->incrementing)->toBeTrue();
    });

    it('has correct fillable attributes', function (): void {
        $pivot = new OnesiBoxUser;

        expect($pivot->getFillable())->toBe(['onesi_box_id', 'user_id', 'permission']);
    });
});

describe('OnesiBoxUser Relations', function (): void {
    it('belongs to an OnesiBox', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $pivot = OnesiBoxUser::query()->first();

        expect($pivot->onesiBox)->toBeInstanceOf(OnesiBox::class)
            ->and($pivot->onesiBox->id)->toBe($this->onesiBox->id);
    });

    it('belongs to a User', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $pivot = OnesiBoxUser::query()->first();

        expect($pivot->user)->toBeInstanceOf(User::class)
            ->and($pivot->user->id)->toBe($this->caregiver->id);
    });
});

describe('OnesiBoxUser Permission Cast', function (): void {
    it('casts permission to OnesiBoxPermission enum', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $pivot = OnesiBoxUser::query()->first();

        expect($pivot->permission)->toBeInstanceOf(OnesiBoxPermission::class)
            ->and($pivot->permission)->toBe(OnesiBoxPermission::Full);
    });

    it('casts read-only permission correctly', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::ReadOnly->value,
        ]);

        $pivot = OnesiBoxUser::query()->first();

        expect($pivot->permission)->toBe(OnesiBoxPermission::ReadOnly);
    });
});

describe('OnesiBoxUser Activity Logging', function (): void {
    it('logs activity when pivot record is created', function (): void {
        $pivot = OnesiBoxUser::query()->create([
            'onesi_box_id' => $this->onesiBox->id,
            'user_id' => $this->caregiver->id,
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $activity = Activity::query()
            ->where('subject_type', OnesiBoxUser::class)
            ->where('subject_id', $pivot->id)
            ->where('description', 'created')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($this->admin->id);
    });

    it('logs activity when pivot record is updated', function (): void {
        $pivot = OnesiBoxUser::query()->create([
            'onesi_box_id' => $this->onesiBox->id,
            'user_id' => $this->caregiver->id,
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $pivot->update(['permission' => OnesiBoxPermission::ReadOnly->value]);

        $activity = Activity::query()
            ->where('subject_type', OnesiBoxUser::class)
            ->where('subject_id', $pivot->id)
            ->where('description', 'updated')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->properties['old']['permission'])->toBe(OnesiBoxPermission::Full->value)
            ->and($activity->properties['attributes']['permission'])->toBe(OnesiBoxPermission::ReadOnly->value);
    });

    it('logs activity when pivot record is deleted', function (): void {
        $pivot = OnesiBoxUser::query()->create([
            'onesi_box_id' => $this->onesiBox->id,
            'user_id' => $this->caregiver->id,
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $pivotId = $pivot->id;
        $pivot->delete();

        $activity = Activity::query()
            ->where('subject_type', OnesiBoxUser::class)
            ->where('subject_id', $pivotId)
            ->where('description', 'deleted')
            ->first();

        expect($activity)->not->toBeNull();
    });
});

describe('OnesiBox BelongsToMany Relation', function (): void {
    it('uses OnesiBoxUser as pivot model', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $caregiver = $this->onesiBox->caregivers()->first();

        expect($caregiver->pivot)->toBeInstanceOf(OnesiBoxUser::class);
    });

    it('includes id in pivot data', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $caregiver = $this->onesiBox->caregivers()->first();

        expect($caregiver->pivot->id)->not->toBeNull()
            ->and($caregiver->pivot->id)->toBeInt();
    });

    it('includes permission in pivot data', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $caregiver = $this->onesiBox->caregivers()->first();

        expect($caregiver->pivot->permission)->toBe(OnesiBoxPermission::Full);
    });

    it('includes timestamps in pivot data', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $caregiver = $this->onesiBox->caregivers()->first();

        expect($caregiver->pivot->created_at)->not->toBeNull()
            ->and($caregiver->pivot->updated_at)->not->toBeNull();
    });
});

describe('User BelongsToMany Relation', function (): void {
    it('uses OnesiBoxUser as pivot model from User side', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $onesiBox = $this->caregiver->onesiBoxes()->first();

        expect($onesiBox->pivot)->toBeInstanceOf(OnesiBoxUser::class);
    });

    it('includes id in pivot data from User side', function (): void {
        $this->onesiBox->caregivers()->attach($this->caregiver->id, [
            'permission' => OnesiBoxPermission::Full->value,
        ]);

        $onesiBox = $this->caregiver->onesiBoxes()->first();

        expect($onesiBox->pivot->id)->not->toBeNull()
            ->and($onesiBox->pivot->id)->toBeInt();
    });
});
