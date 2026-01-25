<?php

declare(strict_types=1);

use App\Actions\Commands\CancelCommandAction;
use App\Enums\CommandStatus;
use App\Models\Command;
use App\Models\OnesiBox;

test('action cancels pending command', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->for($onesiBox)->pending()->create();
    $action = new CancelCommandAction;

    $result = $action->execute($command);

    expect($result)->toBeTrue();
    expect($command->fresh()->status)->toBe(CommandStatus::Cancelled);
});

test('action returns false for already completed command', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->for($onesiBox)->completed()->create();
    $action = new CancelCommandAction;

    $result = $action->execute($command);

    expect($result)->toBeFalse();
    expect($command->fresh()->status)->toBe(CommandStatus::Completed);
});

test('action returns false for already failed command', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->for($onesiBox)->failed()->create();
    $action = new CancelCommandAction;

    $result = $action->execute($command);

    expect($result)->toBeFalse();
    expect($command->fresh()->status)->toBe(CommandStatus::Failed);
});

test('action returns false for already expired command', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->for($onesiBox)->expired()->create();
    $action = new CancelCommandAction;

    $result = $action->execute($command);

    expect($result)->toBeFalse();
    expect($command->fresh()->status)->toBe(CommandStatus::Expired);
});

test('action returns false for already cancelled command', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->for($onesiBox)->create([
        'status' => CommandStatus::Cancelled,
    ]);
    $action = new CancelCommandAction;

    $result = $action->execute($command);

    expect($result)->toBeFalse();
    expect($command->fresh()->status)->toBe(CommandStatus::Cancelled);
});

test('action sets executed_at timestamp when cancelling', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $command = Command::factory()->for($onesiBox)->pending()->create();
    $action = new CancelCommandAction;

    $action->execute($command);

    $freshCommand = $command->fresh();
    expect($freshCommand->executed_at)->not->toBeNull();
    expect($freshCommand->executed_at->isToday())->toBeTrue();
});
