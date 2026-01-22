<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\AudioPlayer;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Livewire\Livewire;
use Mockery\MockInterface;

it('sends audio command with full permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendAudioCommand')
            ->once()
            ->withArgs(fn ($box, $url): bool => $box instanceof OnesiBox && $url === 'https://example.com/audio.mp3');
    });

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->set('audioUrl', 'https://example.com/audio.mp3')
        ->call('playAudio')
        ->assertHasNoErrors();
});

it('blocks audio command with readonly permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->set('audioUrl', 'https://example.com/audio.mp3')
        ->call('playAudio')
        ->assertForbidden();
});

it('validates audio URL is required', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->set('audioUrl', '')
        ->call('playAudio')
        ->assertHasErrors(['audioUrl' => 'required']);
});

it('validates audio URL format', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->set('audioUrl', 'not-a-valid-url')
        ->call('playAudio')
        ->assertHasErrors(['audioUrl' => 'url']);
});
