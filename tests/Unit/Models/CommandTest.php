<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Command;
use App\Models\OnesiBox;
use Illuminate\Support\Carbon;

beforeEach(fn () => freezeTestTime('2026-04-26 14:00:00'));
afterEach(fn () => releaseTestTime());

function makeCommand(array $attrs = []): Command
{
    /** @var Command $command */
    $command = Command::query()->create(array_merge([
        'onesi_box_id' => OnesiBox::factory()->create()->id,
        'type' => CommandType::SetVolume,
        'payload' => ['level' => 50],
        'status' => CommandStatus::Pending,
        'priority' => 3,
    ], $attrs));

    return $command;
}

describe('boot creating hooks', function (): void {
    it('auto-generates a uuid when one is not provided', function (): void {
        $command = makeCommand();

        expect($command->uuid)->toBeString()->and(strlen($command->uuid))->toBe(36);
    });

    it('auto-fills expires_at from the command type default when not provided', function (): void {
        $command = makeCommand(['type' => CommandType::SetVolume]);

        $expected = Carbon::now()->addMinutes(CommandType::SetVolume->defaultExpiresInMinutes());

        expect($command->expires_at?->toDateTimeString())->toBe($expected->toDateTimeString());
    });

    it('keeps an explicit expires_at if the caller provides one', function (): void {
        $command = makeCommand([
            'expires_at' => Carbon::parse('2027-01-01 00:00:00'),
        ]);

        expect($command->expires_at->toDateTimeString())->toBe('2027-01-01 00:00:00');
    });
});

describe('isExpired', function (): void {
    it('is false when expires_at is still in the future', function (): void {
        $command = makeCommand(['expires_at' => Carbon::now()->addMinute()]);

        expect($command->isExpired())->toBeFalse();
    });

    it('is true when expires_at is in the past', function (): void {
        $command = makeCommand(['expires_at' => Carbon::now()->subSecond()]);

        expect($command->isExpired())->toBeTrue();
    });
});

describe('canBeAcknowledged', function (): void {
    it('returns true only when status is Pending', function (): void {
        expect(makeCommand(['status' => CommandStatus::Pending])->canBeAcknowledged())->toBeTrue();
    });

    it('returns false for terminal statuses', function (): void {
        expect(makeCommand(['status' => CommandStatus::Completed])->canBeAcknowledged())->toBeFalse()
            ->and(makeCommand(['status' => CommandStatus::Failed])->canBeAcknowledged())->toBeFalse()
            ->and(makeCommand(['status' => CommandStatus::Expired])->canBeAcknowledged())->toBeFalse();
    });
});

describe('markAsExpired', function (): void {
    it('flips a pending command to expired and stamps executed_at', function (): void {
        $command = makeCommand();

        $command->markAsExpired();

        expect($command->fresh())
            ->status->toBe(CommandStatus::Expired)
            ->and($command->fresh()->executed_at?->toDateTimeString())->toBe('2026-04-26 14:00:00');
    });

    it('is idempotent: a non-pending command is not mutated', function (): void {
        $command = makeCommand([
            'status' => CommandStatus::Completed,
            'executed_at' => Carbon::parse('2026-04-26 13:00:00'),
        ]);

        $command->markAsExpired();

        expect($command->fresh())
            ->status->toBe(CommandStatus::Completed)
            ->and($command->fresh()->executed_at->toDateTimeString())->toBe('2026-04-26 13:00:00');
    });
});

describe('markAsCompleted', function (): void {
    it('persists status, executed_at, and result payload', function (): void {
        $command = makeCommand();

        $command->markAsCompleted(null, ['lines' => ['line1']]);

        expect($command->fresh())
            ->status->toBe(CommandStatus::Completed)
            ->and($command->fresh()->result)->toBe(['lines' => ['line1']])
            ->and($command->fresh()->executed_at?->toDateTimeString())->toBe('2026-04-26 14:00:00');
    });

    it('honours an explicit executed_at when provided', function (): void {
        $command = makeCommand();

        $command->markAsCompleted(Carbon::parse('2026-04-26 13:30:00'));

        expect($command->fresh()->executed_at->toDateTimeString())->toBe('2026-04-26 13:30:00');
    });
});

describe('markAsFailed', function (): void {
    it('persists status, error code, error message, and executed_at', function (): void {
        $command = makeCommand();

        $command->markAsFailed('E110', 'JW Stream unreachable');

        expect($command->fresh())
            ->status->toBe(CommandStatus::Failed)
            ->and($command->fresh()->error_code)->toBe('E110')
            ->and($command->fresh()->error_message)->toBe('JW Stream unreachable')
            ->and($command->fresh()->executed_at?->toDateTimeString())->toBe('2026-04-26 14:00:00');
    });
});

describe('scopes', function (): void {
    it('expiredPending matches only pending commands whose expires_at has elapsed', function (): void {
        $box = OnesiBox::factory()->create();
        $expired = makeCommand(['onesi_box_id' => $box->id, 'expires_at' => Carbon::now()->subMinute()]);
        $stillPending = makeCommand(['onesi_box_id' => $box->id, 'expires_at' => Carbon::now()->addMinute()]);
        $completed = makeCommand([
            'onesi_box_id' => $box->id,
            'status' => CommandStatus::Completed,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $matches = Command::query()->expiredPending()->pluck('id')->all();

        expect($matches)->toContain($expired->id)
            ->and($matches)->not->toContain($stillPending->id)
            ->and($matches)->not->toContain($completed->id);
    });

    it('orderByPriority sorts ascending by priority then oldest first', function (): void {
        $box = OnesiBox::factory()->create();
        $low = makeCommand(['onesi_box_id' => $box->id, 'priority' => 5]);
        $high = makeCommand(['onesi_box_id' => $box->id, 'priority' => 1]);
        $mid = makeCommand(['onesi_box_id' => $box->id, 'priority' => 3]);

        $ids = Command::query()->orderByPriority()->pluck('id')->all();

        expect($ids)->toBe([$high->id, $mid->id, $low->id]);
    });
});
