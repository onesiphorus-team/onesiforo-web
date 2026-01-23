<?php

declare(strict_types=1);

use App\Actions\AcknowledgeCommandAction;
use App\Enums\CommandStatus;
use App\Models\Command;
use App\Models\OnesiBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new AcknowledgeCommandAction;
    $this->onesiBox = OnesiBox::factory()->create();
});

it('acknowledges command with success status', function (): void {
    $command = Command::factory()->pending()->for($this->onesiBox)->create();
    $executedAt = Date::now();

    $result = ($this->action)(
        command: $command,
        status: 'success',
        executedAt: $executedAt
    );

    expect($result)->toBeTrue()
        ->and($command->fresh()->status)->toBe(CommandStatus::Completed)
        ->and($command->fresh()->executed_at->toDateTimeString())->toBe($executedAt->toDateTimeString());
});

it('acknowledges command with failed status', function (): void {
    $command = Command::factory()->pending()->for($this->onesiBox)->create();
    $executedAt = Date::now();

    $result = ($this->action)(
        command: $command,
        status: 'failed',
        executedAt: $executedAt,
        errorCode: 'ERR001',
        errorMessage: 'Test error message'
    );

    expect($result)->toBeTrue()
        ->and($command->fresh()->status)->toBe(CommandStatus::Failed)
        ->and($command->fresh()->error_code)->toBe('ERR001')
        ->and($command->fresh()->error_message)->toBe('Test error message');
});

it('acknowledges command with skipped status as completed', function (): void {
    $command = Command::factory()->pending()->for($this->onesiBox)->create();
    $executedAt = Date::now();

    $result = ($this->action)(
        command: $command,
        status: 'skipped',
        executedAt: $executedAt
    );

    expect($result)->toBeTrue()
        ->and($command->fresh()->status)->toBe(CommandStatus::Completed);
});

it('is idempotent - returns true for already completed command', function (): void {
    $command = Command::factory()->completed()->for($this->onesiBox)->create();

    $result = ($this->action)(
        command: $command,
        status: 'success',
        executedAt: Date::now()
    );

    expect($result)->toBeTrue();
});

it('is idempotent - does not modify already processed command', function (): void {
    $originalExecutedAt = Date::now()->subHour();
    $command = Command::factory()
        ->completed()
        ->for($this->onesiBox)
        ->create(['executed_at' => $originalExecutedAt]);

    ($this->action)(
        command: $command,
        status: 'failed',
        executedAt: Date::now(),
        errorCode: 'ERR001'
    );

    // Should not be modified
    expect($command->fresh()->status)->toBe(CommandStatus::Completed)
        ->and($command->fresh()->error_code)->toBeNull();
});

it('accepts string date for executed_at', function (): void {
    $command = Command::factory()->pending()->for($this->onesiBox)->create();

    $result = ($this->action)(
        command: $command,
        status: 'success',
        executedAt: '2025-01-15 10:30:00'
    );

    expect($result)->toBeTrue()
        ->and($command->fresh()->executed_at->format('Y-m-d H:i:s'))->toBe('2025-01-15 10:30:00');
});

it('returns false for invalid status', function (): void {
    $command = Command::factory()->pending()->for($this->onesiBox)->create();

    $result = ($this->action)(
        command: $command,
        status: 'invalid_status',
        executedAt: Date::now()
    );

    expect($result)->toBeFalse()
        ->and($command->fresh()->status)->toBe(CommandStatus::Pending);
});

it('correctly identifies already processed commands', function (): void {
    $pendingCommand = Command::factory()->pending()->for($this->onesiBox)->create();
    $completedCommand = Command::factory()->completed()->for($this->onesiBox)->create();
    $failedCommand = Command::factory()->failed()->for($this->onesiBox)->create();

    expect($this->action->isAlreadyProcessed($pendingCommand))->toBeFalse()
        ->and($this->action->isAlreadyProcessed($completedCommand))->toBeTrue()
        ->and($this->action->isAlreadyProcessed($failedCommand))->toBeTrue();
});

it('returns correct command status', function (): void {
    $pendingCommand = Command::factory()->pending()->for($this->onesiBox)->create();
    $completedCommand = Command::factory()->completed()->for($this->onesiBox)->create();

    expect($this->action->getCommandStatus($pendingCommand))->toBe(CommandStatus::Pending)
        ->and($this->action->getCommandStatus($completedCommand))->toBe(CommandStatus::Completed);
});
