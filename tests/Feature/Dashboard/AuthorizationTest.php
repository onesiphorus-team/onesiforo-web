<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\OnesiBoxDetail;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

it('allows caregiver to view assigned OnesiBox', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    $this->actingAs($user)
        ->get(route('dashboard.show', $onesiBox))
        ->assertOk();
});

it('denies caregiver access to unassigned OnesiBox', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create();
    // Not attached

    $this->actingAs($user)
        ->get(route('dashboard.show', $onesiBox))
        ->assertForbidden();
});

it('allows full permission caregiver to see control components', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSet('canControl', true);
});

it('denies readonly permission caregiver from seeing control components', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSet('canControl', false);
});

it('does not allow viewing soft-deleted OnesiBox', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);
    $onesiBox->delete();

    $this->actingAs($user)
        ->get(route('dashboard.show', $onesiBox->id))
        ->assertNotFound();
});
