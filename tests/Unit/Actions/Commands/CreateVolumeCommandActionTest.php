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
    'not multiple of 5: 13' => 13,
    'not multiple of 5: 27' => 27,
    'not multiple of 5: 42' => 42,
    'not multiple of 5: 1' => 1,
    'not multiple of 5: 99' => 99,
    'over hundred' => 150,
    'negative' => -20,
    'negative multiple of 5' => -5,
    'just over max' => 105,
]);

test('action accepts all valid volume levels (multiples of 5 from 0 to 100)', function (int $level): void {
    $onesiBox = OnesiBox::factory()->create();
    $action = new CreateVolumeCommandAction;

    $command = $action->execute($onesiBox, $level);

    expect($command->payload['level'])->toBe($level);
})->with([0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100]);

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
