<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\Controls\HeroCard;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the idle variant with "in attesa" copy when state is idle', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'idle'])
        ->assertSee('In attesa')
        ->assertSeeHtml('data-hero-state="idle"');
});
