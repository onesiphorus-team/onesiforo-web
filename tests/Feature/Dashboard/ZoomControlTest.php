<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\ZoomCall;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Livewire\Livewire;
use Mockery\MockInterface;

it('sends zoom url command with full permission using recipient name', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->forRecipient()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $validZoomUrl = 'https://us05web.zoom.us/j/85099838349?pwd=DATUPWNZhaXXUwkvuiA5OKWUfgbdTb.1';
    $expectedName = $onesiBox->recipient->full_name;

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock) use ($validZoomUrl, $expectedName): void {
        $mock->shouldReceive('sendZoomUrlCommand')
            ->once()
            ->withArgs(fn ($box, $url, $name): bool => $box instanceof OnesiBox && $url === $validZoomUrl && $name === $expectedName);
    });

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', $validZoomUrl)
        ->call('startCall')
        ->assertHasNoErrors();
});

it('sends zoom url command with onesibox name when no recipient', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $validZoomUrl = 'https://us05web.zoom.us/j/85099838349?pwd=DATUPWNZhaXXUwkvuiA5OKWUfgbdTb.1';
    $expectedName = $onesiBox->name;

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock) use ($validZoomUrl, $expectedName): void {
        $mock->shouldReceive('sendZoomUrlCommand')
            ->once()
            ->withArgs(fn ($box, $url, $name): bool => $box instanceof OnesiBox && $url === $validZoomUrl && $name === $expectedName);
    });

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', $validZoomUrl)
        ->call('startCall')
        ->assertHasNoErrors();
});

it('blocks zoom command with readonly permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', 'https://us05web.zoom.us/j/85099838349?pwd=test')
        ->call('startCall')
        ->assertForbidden();
});

it('validates zoom url is required', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', '')
        ->call('startCall')
        ->assertHasErrors(['zoomUrl' => 'required']);
});

it('validates zoom url format', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', 'not-a-valid-url')
        ->call('startCall')
        ->assertHasErrors(['zoomUrl' => 'url']);
});

it('validates zoom url must be from zoom.us', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', 'https://example.com/meeting')
        ->call('startCall')
        ->assertHasErrors(['zoomUrl' => 'regex']);
});

it('validates zoom url must contain meeting id', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', 'https://zoom.us/')
        ->call('startCall')
        ->assertHasErrors(['zoomUrl' => 'regex']);
});

it('accepts zoom urls with various subdomains', function (string $zoomUrl): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->forRecipient()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendZoomUrlCommand')
            ->once();
    });

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', $zoomUrl)
        ->call('startCall')
        ->assertHasNoErrors();
})->with([
    'us05web subdomain' => ['https://us05web.zoom.us/j/85099838349?pwd=DATUPWNZhaXXUwkvuiA5OKWUfgbdTb.1'],
    'us02web subdomain' => ['https://us02web.zoom.us/j/123456789?pwd=abc123'],
    'jworg subdomain' => ['https://jworg.zoom.us/j/85942985883?pwd=xyz'],
]);
