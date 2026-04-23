<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\Controls\HeroCard;
use App\Livewire\Dashboard\OnesiBoxDetail;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

test('onesibox detail shows idle status with label', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create([
        'status' => OnesiBoxStatus::Idle,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSeeLivewire(HeroCard::class)
        ->assertStatus(200);
});

test('onesibox detail shows playing status with media info', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/video.mp4',
        'current_media_type' => 'video',
        'current_media_title' => 'Test Video Title',
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSeeLivewire(HeroCard::class)
        ->assertSet('heroState', 'media')
        ->assertStatus(200);
});

test('onesibox detail shows calling status with meeting info', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '123456789',
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSeeLivewire(HeroCard::class)
        ->assertSet('heroState', 'call')
        ->assertStatus(200);
});

test('onesibox detail shows error status', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create([
        'status' => OnesiBoxStatus::Error,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSee('Dispositivo in stato di errore')
        ->assertStatus(200);
});

test('onesibox detail shows online status', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSee('Online')
        ->assertStatus(200);
});

test('onesibox detail shows offline status', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->offline()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSee('Offline')
        ->assertStatus(200);
});

test('onesibox detail shows current volume', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create([
        'volume' => 60,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSet('onesiBox.volume', 60)
        ->assertStatus(200);
});

test('onesibox detail refreshes on StatusUpdated event', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create([
        'status' => OnesiBoxStatus::Idle,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    $component = Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox]);

    // Update the OnesiBox status in the database
    $onesiBox->update(['status' => OnesiBoxStatus::Playing]);

    // Simulate the Echo event
    $component->dispatch('echo-private:onesibox.'.$onesiBox->id.',StatusUpdated', [
        'id' => $onesiBox->id,
        'status' => OnesiBoxStatus::Playing->value,
    ]);

    // The component should have refreshed
    $component->assertSet('onesiBox.status', OnesiBoxStatus::Playing);
});

test('readonly user can view onesibox status', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/video.mp4',
        'current_media_type' => 'video',
        'current_media_title' => 'Video for Readonly',
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSeeLivewire(HeroCard::class)
        ->assertSet('heroState', 'media')
        ->assertSet('canControl', false)
        ->assertStatus(200);
});
