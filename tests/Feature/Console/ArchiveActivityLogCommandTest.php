<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Storage::fake('local');
    Activity::query()->delete();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function activityLoggedAt(DateTimeInterface $when, array $attributes = []): int
{
    return (int) DB::table('activity_log')->insertGetId([
        'log_name' => 'default',
        'description' => 'updated',
        'event' => 'updated',
        'created_at' => $when,
        'updated_at' => $when,
        ...$attributes,
    ]);
}

/**
 * @return array<int, array<string, string|null>>
 */
function archivedRows(string $directory = 'activity-log-archive'): array
{
    $files = Storage::disk('local')->files($directory);

    expect($files)->toHaveCount(1);

    $handle = fopen(Storage::disk('local')->path($files[0]), 'r');
    $header = fgetcsv($handle, escape: '');
    $rows = [];

    while (($row = fgetcsv($handle, escape: '')) !== false) {
        $rows[] = array_combine($header, $row);
    }

    fclose($handle);

    return $rows;
}

it('exports entries older than six months to CSV and removes them from the table', function (): void {
    $old = activityLoggedAt(now()->subMonths(7));
    $older = activityLoggedAt(now()->subYears(2));
    $recent = activityLoggedAt(now()->subMonths(5));

    $this->artisan('activity-log:archive')->assertSuccessful();

    expect(Activity::query()->pluck('id')->all())->toBe([$recent])
        ->and(array_column(archivedRows(), 'id'))->toBe([(string) $old, (string) $older]);
});

it('keeps every column intact in the CSV, including JSON, quotes and line breaks', function (): void {
    $properties = json_encode(['note' => "He said \"ciao\",\nthen left", 'ids' => [1, 2]]);
    $changes = json_encode(['attributes' => ['name' => 'Nuovo, nome'], 'old' => ['name' => 'Vecchio']]);

    $id = activityLoggedAt(now()->subYear(), [
        'description' => "=cmd \"quoted\", with comma\nand newline",
        'subject_type' => 'App\\Models\\OnesiBox',
        'subject_id' => 42,
        'causer_type' => 'App\\Models\\User',
        'causer_id' => 7,
        'properties' => $properties,
        'attribute_changes' => $changes,
    ]);

    $this->artisan('activity-log:archive')->assertSuccessful();

    $row = archivedRows()[0];

    expect($row['id'])->toBe((string) $id)
        ->and($row['description'])->toBe("=cmd \"quoted\", with comma\nand newline")
        ->and($row['subject_type'])->toBe('App\\Models\\OnesiBox')
        ->and($row['subject_id'])->toBe('42')
        ->and($row['causer_id'])->toBe('7')
        ->and(json_decode((string) $row['properties'], true))->toBe(json_decode($properties, true))
        ->and(json_decode((string) $row['attribute_changes'], true))->toBe(json_decode($changes, true))
        ->and(array_keys($row))->toBe(Schema::getColumnListing('activity_log'));
});

it('writes no file when nothing is old enough', function (): void {
    activityLoggedAt(now()->subMonths(5));

    $this->artisan('activity-log:archive')->assertSuccessful();

    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Activity::query()->count())->toBe(1);
});

it('reads the retention, disk and directory from configuration', function (): void {
    Storage::fake('archive');
    config([
        'onesiforo.activity_log.retention_months' => 1,
        'onesiforo.activity_log.archive_disk' => 'archive',
        'onesiforo.activity_log.archive_path' => 'registro/vecchio',
    ]);

    activityLoggedAt(now()->subMonths(2));
    $recent = activityLoggedAt(now()->subWeeks(2));

    $this->artisan('activity-log:archive')->assertSuccessful();

    expect(Storage::disk('archive')->files('registro/vecchio'))->toHaveCount(1)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Activity::query()->pluck('id')->all())->toBe([$recent]);
});

it('lets the options override the configuration', function (): void {
    activityLoggedAt(now()->subMonths(7));
    activityLoggedAt(now()->subMonths(13));

    $this->artisan('activity-log:archive', ['--months' => 12, '--path' => 'altrove'])->assertSuccessful();

    expect(archivedRows('altrove'))->toHaveCount(1)
        ->and(Activity::query()->count())->toBe(1);
});

it('changes nothing on a dry run', function (): void {
    activityLoggedAt(now()->subYear());

    $this->artisan('activity-log:archive', ['--dry-run' => true])
        ->expectsOutputToContain('1 entries')
        ->assertSuccessful();

    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Activity::query()->count())->toBe(1);
});

it('refuses a retention below one month instead of emptying the table', function (int|string $months): void {
    activityLoggedAt(now()->subDay());
    config(['onesiforo.activity_log.retention_months' => $months]);

    $this->artisan('activity-log:archive')->assertFailed();

    expect(Activity::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
})->with([0, -3, 'sei']);

it('deletes nothing when the CSV cannot be written', function (): void {
    activityLoggedAt(now()->subYear());

    Storage::shouldReceive('disk')->with('local')->andReturn($disk = Mockery::mock());
    $disk->shouldReceive('exists')->andReturnFalse();
    $disk->shouldReceive('writeStream')->once()->andReturnFalse();

    $this->artisan('activity-log:archive')->assertFailed();

    expect(Activity::query()->count())->toBe(1);
});

it('deletes nothing when the stored CSV is shorter than what was exported', function (): void {
    activityLoggedAt(now()->subYear());

    Storage::shouldReceive('disk')->with('local')->andReturn($disk = Mockery::mock());
    $disk->shouldReceive('exists')->andReturnFalse();
    $disk->shouldReceive('writeStream')->once()->andReturnTrue();
    $disk->shouldReceive('size')->once()->andReturn(3);

    $this->artisan('activity-log:archive')->assertFailed();

    expect(Activity::query()->count())->toBe(1);
});

it('archives more entries than fit in one chunk', function (): void {
    $when = now()->subYear();
    DB::table('activity_log')->insert(array_map(fn (int $i): array => [
        'log_name' => 'default', 'description' => "entry {$i}", 'created_at' => $when, 'updated_at' => $when,
    ], range(1, 2300)));
    $recent = activityLoggedAt(now());

    $this->artisan('activity-log:archive')->assertSuccessful();

    expect(archivedRows())->toHaveCount(2300)
        ->and(Activity::query()->pluck('id')->all())->toBe([$recent]);
});

it('never overwrites an earlier archive', function (): void {
    $this->freezeSecond();

    activityLoggedAt(now()->subYear());
    $this->artisan('activity-log:archive')->assertSuccessful();

    activityLoggedAt(now()->subYear());
    $this->artisan('activity-log:archive')->assertFailed();

    expect(Storage::disk('local')->files('activity-log-archive'))->toHaveCount(1)
        ->and(Activity::query()->count())->toBe(1);
});

it('is scheduled once a day and can be switched off from the environment', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'activity-log:archive'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('45 3 * * *')
        ->and($event->filtersPass(app()))->toBeTrue();

    config(['onesiforo.activity_log.archive_enabled' => false]);

    expect($event->filtersPass(app()))->toBeFalse();
});
