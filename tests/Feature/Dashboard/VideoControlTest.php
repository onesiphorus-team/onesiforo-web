<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\VideoPlayer;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Livewire\Livewire;
use Mockery\MockInterface;

it('sends video command with full permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $validJwOrgUrl = 'https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODMinistryTools/pub-mwbv_202401_1_VIDEO';

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock) use ($validJwOrgUrl): void {
        $mock->shouldReceive('sendVideoCommand')
            ->once()
            ->withArgs(fn ($box, $url): bool => $box instanceof OnesiBox && $url === $validJwOrgUrl);
    });

    Livewire::actingAs($user)
        ->test(VideoPlayer::class, ['onesiBox' => $onesiBox])
        ->set('videoUrl', $validJwOrgUrl)
        ->call('playVideo')
        ->assertHasNoErrors();
});

it('blocks video command with readonly permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(VideoPlayer::class, ['onesiBox' => $onesiBox])
        ->set('videoUrl', 'https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODMinistryTools/pub-mwbv_202401_1_VIDEO')
        ->call('playVideo')
        ->assertForbidden();
});

it('validates video URL format', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(VideoPlayer::class, ['onesiBox' => $onesiBox])
        ->set('videoUrl', 'invalid-url')
        ->call('playVideo')
        ->assertHasErrors(['videoUrl' => 'url']);
});

it('validates video URL must be from jw.org', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(VideoPlayer::class, ['onesiBox' => $onesiBox])
        ->set('videoUrl', 'https://example.com/video.mp4')
        ->call('playVideo')
        ->assertHasErrors(['videoUrl']);
});
