<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Events\NewCommandAvailable;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\Command;
use App\Models\OnesiBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake([NewCommandAvailable::class]);
});

it('broadcasts NewCommandAvailable event when job handles', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->pending()->for($onesiBox)->create();

    $job = new SendOnesiBoxCommand($command);
    $job->handle();

    Event::assertDispatched(NewCommandAvailable::class, fn ($event): bool => $event->command->id === $command->id);
});

it('has correct retry configuration', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->pending()->for($onesiBox)->create();

    $job = new SendOnesiBoxCommand($command);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe(5);
});

it('marks command as failed when job fails', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->pending()->for($onesiBox)->create();

    $job = new SendOnesiBoxCommand($command);
    $exception = new RuntimeException('Test failure message');

    $job->failed($exception);

    $command->refresh();

    expect($command->status)->toBe(CommandStatus::Failed)
        ->and($command->error_code)->toBe('JOB_FAILED')
        ->and($command->error_message)->toBe('Test failure message');
});

it('includes correct command data in job', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()
        ->pending()
        ->for($onesiBox)
        ->ofType(CommandType::PlayMedia)
        ->withPayload(['url' => 'https://example.com/video.mp4', 'media_type' => 'video'])
        ->create();

    $job = new SendOnesiBoxCommand($command);

    expect($job->command->id)->toBe($command->id)
        ->and($job->command->onesi_box_id)->toBe($onesiBox->id)
        ->and($job->command->type)->toBe(CommandType::PlayMedia)
        ->and($job->command->payload['url'])->toBe('https://example.com/video.mp4');
});

it('is a queued job', function (): void {
    expect(SendOnesiBoxCommand::class)
        ->toImplement(Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('can be dispatched to queue', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->pending()->for($onesiBox)->create();

    dispatch(new SendOnesiBoxCommand($command));

    Illuminate\Support\Facades\Queue::assertPushed(SendOnesiBoxCommand::class, fn ($job): bool => $job->command->id === $command->id);
});

it('logs command information when handling', function (): void {
    Illuminate\Support\Facades\Log::spy();

    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()
        ->pending()
        ->for($onesiBox)
        ->ofType(CommandType::StopMedia)
        ->create();

    $job = new SendOnesiBoxCommand($command);
    $job->handle();

    Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn ($message, array $context): bool => $message === 'OnesiBox command queued'
            && $context['command_uuid'] === $command->uuid
            && $context['onesibox_id'] === $onesiBox->id
            && $context['type'] === 'stop_media');
});

it('logs error information when job fails', function (): void {
    Illuminate\Support\Facades\Log::spy();

    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->pending()->for($onesiBox)->create();

    $job = new SendOnesiBoxCommand($command);
    $exception = new RuntimeException('Connection refused');

    $job->failed($exception);

    Illuminate\Support\Facades\Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn ($message, array $context): bool => $message === 'OnesiBox command job failed'
            && $context['command_uuid'] === $command->uuid
            && $context['error'] === 'Connection refused');
});
