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

    $validJwOrgUrl = 'https://www.jw.org/it/biblioteca/audio/#it/mediaitems/VODMinistryTools/pub-mwbv_202401_1_AUDIO';

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock) use ($validJwOrgUrl): void {
        $mock->shouldReceive('sendMediaCommand')
            ->once()
            ->withArgs(fn ($box, $url, $type): bool => $box instanceof OnesiBox && $url === $validJwOrgUrl && $type === 'audio');
    });

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->set('audioUrl', $validJwOrgUrl)
        ->call('playAudio')
        ->assertHasNoErrors();
});

it('blocks audio command with readonly permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->set('audioUrl', 'https://www.jw.org/it/biblioteca/audio/#it/mediaitems/VODMinistryTools/pub-mwbv_202401_1_AUDIO')
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

it('validates audio URL must be from jw.org', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->set('audioUrl', 'https://example.com/audio.mp3')
        ->call('playAudio')
        ->assertHasErrors(['audioUrl']);
});

it('stops audio playback with full permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendStopCommand')
            ->once()
            ->withArgs(fn ($box): bool => $box instanceof OnesiBox);
    });

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->call('stopPlayback')
        ->assertHasNoErrors();
});

it('blocks stop playback with readonly permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->call('stopPlayback')
        ->assertForbidden();
});
