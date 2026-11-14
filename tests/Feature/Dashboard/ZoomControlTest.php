<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\ZoomCall;
use App\Models\OnesiBox;
use App\Models\User;
use App\Rules\ZoomUrl;
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
        ->assertHasErrors(['zoomUrl' => ZoomUrl::class]);
});

it('validates zoom url must contain meeting id', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', 'https://zoom.us/')
        ->call('startCall')
        ->assertHasErrors(['zoomUrl' => ZoomUrl::class]);
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
    'no subdomain' => ['https://zoom.us/j/123456789?pwd=abc'],
    'hyphenated subdomain' => ['https://jw-org.zoom.us/j/123456789'],
    'extra query parameters' => ['https://us05web.zoom.us/j/123456789?omn=1&pwd=abc'],
    'webinar link' => ['https://us05web.zoom.us/w/123456789?pwd=abc'],
]);

it('sends and saves the pasted link without the spaces and newlines around it', function (string $pastedUrl): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->forRecipient()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $cleanUrl = 'https://us05web.zoom.us/j/123456789?pwd=abc';

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock) use ($cleanUrl): void {
        $mock->shouldReceive('sendZoomUrlCommand')
            ->once()
            ->withArgs(fn ($box, $url, $name): bool => $url === $cleanUrl);
    });

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', $pastedUrl)
        ->set('saveMeeting', true)
        ->set('meetingName', 'Adunanza Loreto')
        ->call('startCall')
        ->assertHasNoErrors();

    expect($onesiBox->zoomMeetings()->sole()->zoom_url)->toBe($cleanUrl);
})->with([
    'spaces around' => ['  https://us05web.zoom.us/j/123456789?pwd=abc  '],
    'trailing newline' => ["https://us05web.zoom.us/j/123456789?pwd=abc\n"],
    'tabs and carriage returns' => ["\thttps://us05web.zoom.us/j/123456789?pwd=abc\r\n"],
]);

it('refuses a pasted link with something else inside it, in plain Italian', function (string $zoomUrl): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->forRecipient()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('sendZoomUrlCommand');
    });

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('zoomUrl', $zoomUrl)
        ->call('startCall')
        ->assertHasErrors('zoomUrl')
        ->assertSee('Questo non sembra un link Zoom');
})->with([
    'two lines' => ["https://us05web.zoom.us/j/123456789\nhttps://evil.example/x"],
    'text after the link' => ['https://us05web.zoom.us/j/123456789?pwd=abc def'],
    'lookalike domain' => ['https://evil.zoom.us.attacker.com/j/123456789'],
    'not a link' => ['ciao'],
]);
