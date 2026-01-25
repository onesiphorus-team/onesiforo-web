<?php

declare(strict_types=1);

use App\Actions\Commands\CreateVolumeCommandAction;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Command;
use App\Models\OnesiBox;
use Illuminate\Validation\ValidationException;

test('action creates volume command with valid level', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $action = new CreateVolumeCommandAction;

    $command = $action->execute($onesiBox, 60);

    expect($command)->toBeInstanceOf(Command::class);
    expect($command->onesi_box_id)->toBe($onesiBox->id);
    expect($command->type)->toBe(CommandType::SetVolume);
    expect($command->status)->toBe(CommandStatus::Pending);
    expect($command->payload)->toBe(['level' => 60]);
});

test('action rejects invalid volume levels', function (int $level): void {
    $onesiBox = OnesiBox::factory()->create();
    $action = new CreateVolumeCommandAction;

    expect(fn (): Command => $action->execute($onesiBox, $level))
        ->toThrow(ValidationException::class);
})->with([
    'zero' => 0,
    'ten' => 10,
    'thirty' => 30,
    'fifty' => 50,
    'seventy' => 70,
    'ninety' => 90,
    'over hundred' => 150,
    'negative' => -20,
]);

test('action accepts all valid volume levels', function (int $level): void {
    $onesiBox = OnesiBox::factory()->create();
    $action = new CreateVolumeCommandAction;

    $command = $action->execute($onesiBox, $level);

    expect($command->payload['level'])->toBe($level);
})->with([20, 40, 60, 80, 100]);

test('action sets appropriate priority for volume command', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $action = new CreateVolumeCommandAction;

    $command = $action->execute($onesiBox, 80);

    expect($command->priority)->toBe(3);
});

test('action sets expiration time for volume command', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $action = new CreateVolumeCommandAction;

    $command = $action->execute($onesiBox, 60);

    expect($command->expires_at)->not->toBeNull();
    expect($command->expires_at->isAfter(now()))->toBeTrue();
});
