<?php

declare(strict_types=1);

use App\Actions\Commands\CreateVolumeCommandAction;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\Command;
use App\Models\OnesiBox;
use Illuminate\Validation\ValidationException;

test('action creates volume command with valid level via service', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create();
    $action = app(CreateVolumeCommandAction::class);

    $action->execute($onesiBox, 60);

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)->latest('id')->first();

    expect($command)->not->toBeNull();
    expect($command->type)->toBe(CommandType::SetVolume);
    expect($command->status)->toBe(CommandStatus::Pending);
    expect($command->payload)->toBe(['level' => 60]);
});

test('action rejects invalid volume levels', function (int $level): void {
    $onesiBox = OnesiBox::factory()->online()->create();
    $action = app(CreateVolumeCommandAction::class);

    expect(fn () => $action->execute($onesiBox, $level))
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
    $onesiBox = OnesiBox::factory()->online()->create();
    $action = app(CreateVolumeCommandAction::class);

    $action->execute($onesiBox, $level);

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)->latest('id')->first();

    expect($command->payload['level'])->toBe($level);
})->with([0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100]);

test('action throws when onesi box is offline', function (): void {
    $onesiBox = OnesiBox::factory()->create(['last_seen_at' => null]);
    $action = app(CreateVolumeCommandAction::class);

    expect(fn () => $action->execute($onesiBox, 80))
        ->toThrow(OnesiBoxOfflineException::class);
});

test('action sets expiration time for volume command', function (): void {
    $onesiBox = OnesiBox::factory()->online()->create();
    $action = app(CreateVolumeCommandAction::class);

    $action->execute($onesiBox, 60);

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)->latest('id')->first();

    expect($command->expires_at)->not->toBeNull();
    expect($command->expires_at->isAfter(now()))->toBeTrue();
});
