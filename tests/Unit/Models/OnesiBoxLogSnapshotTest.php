<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Enums\LogSnapshotSource;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\OnesiBoxLogSnapshot;

it('belongs to an OnesiBox', function (): void {
    $box = OnesiBox::factory()->create();
    $snapshot = OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create();

    expect($snapshot->onesiBox->id)->toBe($box->id);
});

it('belongs to the command that produced it', function (): void {
    $box = OnesiBox::factory()->create();
    $command = Command::factory()->for($box, 'onesiBox')->ofType(CommandType::GetLogs)->create();

    $snapshot = OnesiBoxLogSnapshot::factory()
        ->for($box, 'onesiBox')
        ->create(['command_id' => $command->id]);

    expect($snapshot->command->id)->toBe($command->id);
});

it('casts source and lines', function (): void {
    $snapshot = OnesiBoxLogSnapshot::factory()->manual()->create(['lines' => ['a', 'b']]);

    expect($snapshot->source)->toBe(LogSnapshotSource::Manual)
        ->and($snapshot->lines)->toBe(['a', 'b']);
});

it('renders its lines as a single text blob', function (): void {
    $snapshot = OnesiBoxLogSnapshot::factory()->create(['lines' => ['prima', 'seconda']]);

    expect($snapshot->linesAsText())->toBe("prima\nseconda");
});

it('knows when it carries an error', function (): void {
    expect(OnesiBoxLogSnapshot::factory()->create()->hasError())->toBeFalse()
        ->and(OnesiBoxLogSnapshot::factory()->failed()->create()->hasError())->toBeTrue();
});

it('orders the latest capture first', function (): void {
    $box = OnesiBox::factory()->create();

    OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create(['captured_at' => now()->subHours(2)]);
    $latest = OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create(['captured_at' => now()]);

    expect(OnesiBoxLogSnapshot::query()->latestFirst()->first()->id)->toBe($latest->id);
});

it('is removed together with its OnesiBox', function (): void {
    $box = OnesiBox::factory()->create();
    OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create();

    $box->forceDelete();

    expect(OnesiBoxLogSnapshot::query()->count())->toBe(0);
});

describe('log collection scheduling', function (): void {
    it('is due when the box has never been asked', function (): void {
        $box = OnesiBox::factory()->create(['last_log_requested_at' => null]);

        expect($box->isLogCollectionDue())->toBeTrue();
    });

    it('is not due before the interval elapses', function (): void {
        $box = OnesiBox::factory()->create([
            'log_collection_interval_minutes' => 60,
            'last_log_requested_at' => now()->subMinutes(59),
        ]);

        expect($box->isLogCollectionDue())->toBeFalse();
    });

    it('is due once the interval elapses', function (): void {
        $box = OnesiBox::factory()->create([
            'log_collection_interval_minutes' => 60,
            'last_log_requested_at' => now()->subMinutes(61),
        ]);

        expect($box->isLogCollectionDue())->toBeTrue();
    });

    it('is never due when collection is disabled', function (): void {
        $box = OnesiBox::factory()->create([
            'log_collection_enabled' => false,
            'last_log_requested_at' => null,
        ]);

        expect($box->isLogCollectionDue())->toBeFalse();
    });
});
