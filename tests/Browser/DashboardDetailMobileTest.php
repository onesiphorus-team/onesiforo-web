<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\User;

it('renders the mobile dashboard detail page without JS errors (idle state)', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'name' => 'Test Dev Box',
    ]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->actingAs($user);

    $page = visit(route('dashboard.show', $box));

    $page->assertPathIs('/dashboard/'.$box->id)
        ->assertSee('Test Dev Box')
        ->assertAttribute('[data-hero-state]', 'data-hero-state', 'idle')
        ->assertPresent('[data-slot="stop"]')
        ->assertPresent('[data-slot="volume"]')
        ->assertPresent('[data-slot="new"]')
        ->assertPresent('[data-slot="call"]')
        ->assertNoJavaScriptErrors();
});

it('renders the media variant of the hero when a media is playing', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'name' => 'Playing Box',
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
        'current_media_title' => 'Ave Maria',
    ]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->actingAs($user);

    $page = visit(route('dashboard.show', $box));

    $page->assertSee('Ave Maria')
        ->assertSee('AUDIO')
        ->assertAttribute('[data-hero-state]', 'data-hero-state', 'media')
        ->assertNoJavaScriptErrors();
});

it('hides the bottom bar for a caregiver without Full permission', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['name' => 'ReadOnly Box']);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    $this->actingAs($user);

    $page = visit(route('dashboard.show', $box));

    $page->assertSee('ReadOnly Box')
        ->assertNotPresent('[data-slot="stop"]')
        ->assertNotPresent('[data-slot="volume"]')
        ->assertNotPresent('[data-slot="new"]')
        ->assertNotPresent('[data-slot="call"]')
        ->assertNoJavaScriptErrors();
});
