<?php

declare(strict_types=1);

use App\Actions\Sessions\StartPlaybackSessionAction;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\PlaybackSessionStatus;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Oltrematica\RoleLite\Models\Role;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

pest()->extend(Tests\TestCase::class);

beforeEach(function (): void {
    if (
        config('database.default') !== 'mariadb'
        || config('database.connections.mariadb.database') !== 'onesiforo_concurrency_testing'
        || ! in_array(config('database.connections.mariadb.host'), ['127.0.0.1', 'localhost'], true)
        || config('database.connections.mariadb.url')
        || config('database.connections.mariadb.unix_socket')
    ) {
        throw new RuntimeException('Refusing to migrate a non-test database or cached connection configuration.');
    }

    expect(DB::selectOne('SELECT DATABASE() AS name')->name)->toBe('onesiforo_concurrency_testing');
    Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
    Queue::fake();
});

/**
 * Poll slowly enough for MariaDB to refresh its lock diagnostic snapshot.
 *
 * @param  Closure(): bool  $condition
 */
function awaitConcurrencyCondition(Closure $condition, string $failure): void
{
    $deadline = microtime(true) + 10;
    do {
        if ($condition()) {
            return;
        }
        usleep(200_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException($failure);
}

/**
 * @param  array<string, mixed>  $first
 * @param  array<string, mixed>  $second
 * @return array{mixed, mixed}
 */
function contend(array $first, array $second): array
{
    $streams = [new InputStream, new InputStream];
    $processes = [];
    try {
        foreach ([$first + ['hold' => true], $second] as $index => $operation) {
            $process = new Process([PHP_BINARY, __DIR__.'/worker.php', json_encode($operation, JSON_THROW_ON_ERROR)], base_path());
            $process->setInput($streams[$index]);
            $process->setTimeout(20);
            $process->start();
            $processes[] = $process;
            awaitConcurrencyCondition(fn (): bool => str_contains($process->getOutput(), '"ready"'), 'Worker did not prepare: '.$process->getErrorOutput());
        }

        $streams[0]->write("go\n");
        awaitConcurrencyCondition(fn (): bool => str_contains($processes[0]->getOutput(), 'locked'), 'First operation failed to acquire its database lock.');
        $streams[1]->write("go\n");
        $connectionId = json_decode(explode("\n", $processes[1]->getOutput())[0], true, flags: JSON_THROW_ON_ERROR)['ready'];

        awaitConcurrencyCondition(function () use ($connectionId, $processes): bool {
            $processes[1]->getOutput();

            return DB::table('information_schema.INNODB_LOCK_WAITS AS waits')
                ->join('information_schema.INNODB_TRX AS trx', 'trx.trx_id', '=', 'waits.requesting_trx_id')
                ->where('trx.trx_mysql_thread_id', $connectionId)
                ->exists();
        }, 'Second operation did not actually contend for an InnoDB row lock.');

        $streams[0]->write("commit\n");
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $lines = explode("\n", trim($process->getOutput()));
            $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['result'];
        }

        return $results;
    } finally {
        foreach ($streams as $stream) {
            $stream->close();
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(0);
            }
        }
    }
}

/** @return array{User, User} */
function concurrencyAdmins(): array
{
    foreach (['super-admin', 'admin', 'caregiver'] as $role) {
        Role::query()->firstOrCreate(['name' => $role]);
    }
    $users = User::factory()->count(2)->create();
    foreach ($users as $user) {
        $user->assignRole('super-admin');
    }

    return [$users[0], $users[1]];
}

/** @return array{OnesiBox, Playlist} */
function concurrencyPlaylist(): array
{
    $box = OnesiBox::factory()->online()->create();
    $playlist = Playlist::factory()->forOnesiBox($box)->create();
    foreach (range(0, 2) as $position) {
        PlaylistItem::factory()->for($playlist)->atPosition($position)->create();
    }

    return [$box, $playlist];
}

it('keeps an active super admin when deletion races with demotion', function (string $firstAction, string $secondAction, bool $force = false): void {
    [$first, $second] = concurrencyAdmins();
    $results = contend(
        ['action' => $firstAction, 'actor' => $first->id, 'target' => $second->id, 'roles' => ['admin'], 'force' => $force],
        ['action' => $secondAction, 'actor' => $second->id, 'target' => $first->id, 'roles' => ['admin'], 'force' => $force],
    );

    expect($results)->toBe([true, false])
        ->and(User::query()->whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))->count())->toBe(1)
        ->and($first->refresh()->hasRole('super-admin'))->toBeTrue();
})->with([
    'cross deletion' => ['delete', 'delete'],
    'cross permanent deletion' => ['delete', 'delete', true],
    'deletion then demotion' => ['delete', 'edit'],
    'demotion then deletion' => ['edit', 'delete'],
    'cross demotion' => ['edit', 'edit'],
]);

it('allows only one of two competing self demotions', function (): void {
    [$first, $second] = concurrencyAdmins();

    $results = contend(
        ['action' => 'edit', 'actor' => $first->id, 'target' => $first->id, 'roles' => ['admin']],
        ['action' => 'edit', 'actor' => $second->id, 'target' => $second->id, 'roles' => ['admin']],
    );

    expect($results)->toBe([true, false])
        ->and($first->refresh()->hasRole('super-admin'))->toBeFalse()
        ->and($second->refresh()->hasRole('super-admin'))->toBeTrue();
});

it('rejects a stale admin edit after the target becomes a super admin', function (): void {
    [$superAdmin, $target] = concurrencyAdmins();
    $target->syncRoles('caregiver');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $originalName = $target->name;

    $results = contend(
        ['action' => 'edit', 'actor' => $superAdmin->id, 'target' => $target->id, 'roles' => ['super-admin']],
        ['action' => 'edit', 'actor' => $admin->id, 'target' => $target->id, 'name' => 'Forbidden Change'],
    );

    expect($results)->toBe([true, false])
        ->and($target->refresh()->name)->toBe($originalName)
        ->and($target->hasRole('super-admin'))->toBeTrue();
});

it('serializes simultaneous starts and persists commands only for the surviving session', function (): void {
    [$box, $playlist] = concurrencyPlaylist();
    $operation = ['action' => 'start', 'box' => $box->id, 'playlist' => $playlist->id];
    [$firstId, $secondId] = contend($operation, $operation);

    $first = PlaybackSession::findOrFail($firstId);
    $second = PlaybackSession::findOrFail($secondId);
    expect($first->status)->toBe(PlaybackSessionStatus::Stopped)
        ->and($second->status)->toBe(PlaybackSessionStatus::Active)
        ->and($box->playbackSessions()->active()->count())->toBe(1)
        ->and(Command::query()->where('type', CommandType::PlayMedia)->where('status', CommandStatus::Pending)->count())->toBe(1)
        ->and(Command::query()->where('type', CommandType::PlayMedia)->where('status', CommandStatus::Pending)->firstOrFail()->payload['session_id'])->toBe($second->uuid);
});

it('serializes stop and advance without leaving a pending play command', function (bool $stopFirst): void {
    [$box, $playlist] = concurrencyPlaylist();
    $session = app(StartPlaybackSessionAction::class)->execute($box, $playlist, 30);
    $stop = ['action' => 'stop', 'session' => $session->id];
    $advance = ['action' => 'advance', 'box' => $box->id, 'uuid' => $session->uuid, 'url' => $session->currentItem()->media_url];

    contend($stopFirst ? $stop : $advance, $stopFirst ? $advance : $stop);

    expect($session->refresh()->status)->toBe(PlaybackSessionStatus::Stopped)
        ->and($session->current_position)->toBe($stopFirst ? 0 : 1)
        ->and(Command::query()->where('type', CommandType::PlayMedia)->where('status', CommandStatus::Pending)->count())->toBe(0);
})->with([true, false]);
