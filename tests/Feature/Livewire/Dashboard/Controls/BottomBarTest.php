<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\BottomBar;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the 4 slots when the user can control and the box is online', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertSeeHtml('data-slot="stop"')
        ->assertSeeHtml('data-slot="volume"')
        ->assertSeeHtml('data-slot="new"')
        ->assertSeeHtml('data-slot="call"');
});

it('renders nothing when the user has no caregiver relationship', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    // No caregivers attach — user has no permission

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertDontSeeHtml('data-slot="stop"')
        ->assertDontSeeHtml('data-slot="volume"');
});
