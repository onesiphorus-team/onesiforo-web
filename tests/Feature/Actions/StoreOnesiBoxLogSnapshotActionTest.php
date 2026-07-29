<?php

declare(strict_types=1);

use App\Actions\AcknowledgeCommandAction;
use App\Enums\CommandType;
use App\Enums\LogSnapshotSource;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\OnesiBoxLogSnapshot;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->action = app(AcknowledgeCommandAction::class);
    $this->box = OnesiBox::factory()->online()->create();
});

function logCommandFor(OnesiBox $box, ?array $payload = null): Command
{
    return Command::factory()
        ->for($box, 'onesiBox')
        ->pending()
        ->ofType(CommandType::GetLogs)
        ->withPayload($payload ?? ['lines' => 50])
        ->create();
}

it('stores a snapshot when a log command succeeds', function (): void {
    $command = logCommandFor($this->box);

    ($this->action)(
        command: $command,
        status: 'success',
        executedAt: Date::now(),
        result: [
            'lines' => ['first line', 'second line'],
            'total_lines' => 420,
            'log_file' => 'onesibox-2026-07-29.log',
            'timestamp' => '2026-07-29T08:30:00.000Z',
        ],
    );

    $snapshot = OnesiBoxLogSnapshot::query()->sole();

    expect($snapshot->onesi_box_id)->toBe($this->box->id)
        ->and($snapshot->command_id)->toBe($command->id)
        ->and($snapshot->lines)->toBe(['first line', 'second line'])
        ->and($snapshot->returned_lines)->toBe(2)
        ->and($snapshot->total_lines)->toBe(420)
        ->and($snapshot->log_file)->toBe('onesibox-2026-07-29.log')
        ->and($snapshot->captured_at->toIso8601String())->toBe(Date::parse('2026-07-29T08:30:00.000Z')->toIso8601String())
        ->and($snapshot->hasError())->toBeFalse();
});

it('attributes the snapshot to the source carried by the command payload', function (): void {
    $command = logCommandFor($this->box, ['lines' => 50, 'source' => LogSnapshotSource::Scheduled->value]);

    ($this->action)($command, 'success', Date::now(), result: ['lines' => ['x']]);

    expect(OnesiBoxLogSnapshot::query()->sole()->source)->toBe(LogSnapshotSource::Scheduled);
});

it('treats an untagged command as a manual request', function (): void {
    $command = logCommandFor($this->box);

    ($this->action)($command, 'success', Date::now(), result: ['lines' => ['x']]);

    expect(OnesiBoxLogSnapshot::query()->sole()->source)->toBe(LogSnapshotSource::Manual);
});

it('falls back to the execution time when the appliance sends no timestamp', function (): void {
    $command = logCommandFor($this->box);
    $executedAt = Date::now()->subMinutes(3);

    ($this->action)($command, 'success', $executedAt, result: ['lines' => []]);

    expect(OnesiBoxLogSnapshot::query()->sole()->captured_at->toDateTimeString())
        ->toBe($executedAt->toDateTimeString());
});

it('caps the number of stored lines', function (): void {
    $command = logCommandFor($this->box);
    $lines = array_map(fn (int $i): string => "line {$i}", range(1, 700));

    ($this->action)($command, 'success', Date::now(), result: ['lines' => $lines]);

    $snapshot = OnesiBoxLogSnapshot::query()->sole();

    expect($snapshot->lines)->toHaveCount(500)
        ->and($snapshot->lines[499])->toBe('line 700');
});

it('records a failed log collection so the attempt stays visible', function (): void {
    $command = logCommandFor($this->box);

    ($this->action)(
        command: $command,
        status: 'failed',
        executedAt: Date::now(),
        errorCode: 'E010',
        errorMessage: 'Failed to retrieve logs: ENOENT',
    );

    $snapshot = OnesiBoxLogSnapshot::query()->sole();

    expect($snapshot->hasError())->toBeTrue()
        ->and($snapshot->error_message)->toBe('Failed to retrieve logs: ENOENT')
        ->and($snapshot->lines)->toBe([]);
});

it('ignores commands that are not log requests', function (): void {
    $command = Command::factory()
        ->for($this->box, 'onesiBox')
        ->pending()
        ->ofType(CommandType::Reboot)
        ->create();

    ($this->action)($command, 'success', Date::now(), result: ['lines' => ['noise']]);

    expect(OnesiBoxLogSnapshot::query()->count())->toBe(0);
});
