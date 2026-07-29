<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\LogSnapshotSource;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\Command;
use App\Models\OnesiBox;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

function logCommandsFor(OnesiBox $box): int
{
    return Command::query()
        ->where('onesi_box_id', $box->id)
        ->where('type', CommandType::GetLogs)
        ->count();
}

it('requests logs from an online box that has never been collected', function (): void {
    $box = OnesiBox::factory()->online()->create();

    $this->artisan('onesibox:collect-logs')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(1)
        ->and($box->fresh()->last_log_requested_at)->not->toBeNull();

    Queue::assertPushed(SendOnesiBoxCommand::class);
});

it('tags scheduled requests so the snapshot can be attributed', function (): void {
    $box = OnesiBox::factory()->online()->create(['log_collection_lines' => 123]);

    $this->artisan('onesibox:collect-logs')->assertSuccessful();

    $command = Command::query()->where('onesi_box_id', $box->id)->sole();

    expect($command->payload['source'])->toBe(LogSnapshotSource::Scheduled->value)
        ->and($command->payload['lines'])->toBe(123);
});

it('skips boxes that are offline', function (): void {
    $box = OnesiBox::factory()->offline()->create();

    $this->artisan('onesibox:collect-logs')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(0)
        ->and($box->fresh()->last_log_requested_at)->toBeNull();
});

it('skips boxes with log collection disabled', function (): void {
    $box = OnesiBox::factory()->online()->create(['log_collection_enabled' => false]);

    $this->artisan('onesibox:collect-logs')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(0);
});

it('skips inactive boxes', function (): void {
    $box = OnesiBox::factory()->online()->inactive()->create();

    $this->artisan('onesibox:collect-logs')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(0);
});

it('skips boxes whose interval has not elapsed yet', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'log_collection_interval_minutes' => 60,
        'last_log_requested_at' => now()->subMinutes(10),
    ]);

    $this->artisan('onesibox:collect-logs')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(0);
});

it('requests again once the interval has elapsed', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'log_collection_interval_minutes' => 60,
        'last_log_requested_at' => now()->subMinutes(61),
    ]);

    $this->artisan('onesibox:collect-logs')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(1);
});

it('ignores the interval when forced', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'log_collection_interval_minutes' => 60,
        'last_log_requested_at' => now()->subMinute(),
    ]);

    $this->artisan('onesibox:collect-logs --force')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(1);
});

it('does not pile up requests while one is still pending', function (): void {
    $box = OnesiBox::factory()->online()->create();

    Command::factory()
        ->for($box, 'onesiBox')
        ->pending()
        ->ofType(CommandType::GetLogs)
        ->create();

    $this->artisan('onesibox:collect-logs --force')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(1);
});

it('requests again once the previous command is no longer pending', function (): void {
    $box = OnesiBox::factory()->online()->create();

    Command::factory()
        ->for($box, 'onesiBox')
        ->ofType(CommandType::GetLogs)
        ->create(['status' => CommandStatus::Completed, 'executed_at' => now()->subHour()]);

    $this->artisan('onesibox:collect-logs --force')->assertSuccessful();

    expect(logCommandsFor($box))->toBe(2);
});

it('can be restricted to a single box', function (): void {
    $target = OnesiBox::factory()->online()->create();
    $other = OnesiBox::factory()->online()->create();

    $this->artisan('onesibox:collect-logs', ['--box' => $target->id])->assertSuccessful();

    expect(logCommandsFor($target))->toBe(1)
        ->and(logCommandsFor($other))->toBe(0);
});
