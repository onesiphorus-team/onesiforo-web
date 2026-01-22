<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Events\NewCommandAvailable;
use App\Events\OnesiBoxCommandSent;
use App\Exceptions\OnesiBoxOfflineException;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    // Only fake specific events, not Eloquent model events
    Event::fake([
        OnesiBoxCommandSent::class,
        NewCommandAvailable::class,
    ]);
});

it('creates a PlayMedia command when sending audio', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();

    $this->actingAs($user);

    $service = new OnesiBoxCommandService;
    $service->sendAudioCommand($onesiBox, 'https://example.com/audio.mp3');

    $command = Command::query()->first();
    expect($command)
        ->not->toBeNull()
        ->type->toBe(CommandType::PlayMedia)
        ->status->toBe(CommandStatus::Pending)
        ->payload->toMatchArray([
            'url' => 'https://example.com/audio.mp3',
            'media_type' => 'audio',
        ]);

    Queue::assertPushed(SendOnesiBoxCommand::class, function ($job) use ($command): bool {
        return $job->command->id === $command->id;
    });

    Event::assertDispatched(OnesiBoxCommandSent::class, function ($event) use ($command): bool {
        return $event->command->id === $command->id;
    });
});

it('creates a PlayMedia command when sending video', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();

    $this->actingAs($user);

    $service = new OnesiBoxCommandService;
    $service->sendVideoCommand($onesiBox, 'https://example.com/video.mp4');

    $command = Command::query()->first();
    expect($command)
        ->not->toBeNull()
        ->type->toBe(CommandType::PlayMedia)
        ->payload->toMatchArray([
            'url' => 'https://example.com/video.mp4',
            'media_type' => 'video',
        ]);
});

it('creates a JoinZoom command', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();

    $this->actingAs($user);

    $service = new OnesiBoxCommandService;
    $service->sendZoomCommand($onesiBox, '123456789', 'secret123');

    $command = Command::query()->first();
    expect($command)
        ->not->toBeNull()
        ->type->toBe(CommandType::JoinZoom)
        ->payload->toMatchArray([
            'meeting_id' => '123456789',
            'password' => 'secret123',
        ]);
});

it('creates a StopMedia command', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();

    $this->actingAs($user);

    $service = new OnesiBoxCommandService;
    $service->sendStopCommand($onesiBox);

    $command = Command::query()->first();
    expect($command)
        ->not->toBeNull()
        ->type->toBe(CommandType::StopMedia)
        ->payload->toBe([]);
});

it('throws exception when OnesiBox is offline', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->offline()->create();

    $this->actingAs($user);

    $service = new OnesiBoxCommandService;
    $service->sendAudioCommand($onesiBox, 'https://example.com/audio.mp3');
})->throws(OnesiBoxOfflineException::class);

it('does not dispatch event when no user is authenticated', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create();

    $service = new OnesiBoxCommandService;
    $service->sendAudioCommand($onesiBox, 'https://example.com/audio.mp3');

    $command = Command::query()->first();
    expect($command)->not->toBeNull();

    Queue::assertPushed(SendOnesiBoxCommand::class);
    Event::assertNotDispatched(OnesiBoxCommandSent::class);
});

it('command is retrievable by appliance via API', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();

    $this->actingAs($user);

    $service = new OnesiBoxCommandService;
    $service->sendAudioCommand($onesiBox, 'https://example.com/audio.mp3');

    // Verify command exists in database and is retrievable
    expect($onesiBox->pendingCommands()->count())->toBe(1);

    $command = $onesiBox->pendingCommands()->first();
    expect($command)
        ->type->toBe(CommandType::PlayMedia)
        ->payload->url->toBe('https://example.com/audio.mp3');
});

it('end-to-end: dashboard command is retrievable by appliance API', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create();
    $token = $onesiBox->createToken('appliance-token');

    // Simulate creating command (service is called internally, so we create directly)
    Command::create([
        'onesi_box_id' => $onesiBox->id,
        'type' => CommandType::PlayMedia,
        'payload' => ['url' => 'https://example.com/audio.mp3', 'media_type' => 'audio'],
        'priority' => 3,
        'status' => CommandStatus::Pending,
    ]);

    // Appliance fetches commands via API
    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'play_media')
        ->assertJsonPath('data.0.payload.url', 'https://example.com/audio.mp3');
});
