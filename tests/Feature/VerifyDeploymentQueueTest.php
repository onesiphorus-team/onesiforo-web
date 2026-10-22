<?php

declare(strict_types=1);

use App\Jobs\VerifyDeploymentQueueJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config(['queue.default' => 'database', 'cache.default' => 'database']);
    Sleep::fake();
});

it('requires an asynchronous worker and shared cache', function (string $queue, string $cache): void {
    config(['queue.default' => $queue, 'cache.default' => $cache]);
    Bus::fake();

    $this->artisan('deployment:verify-queue')->assertFailed();

    Bus::assertNothingDispatched();
})->with([['sync', 'database'], ['database', 'array']]);

it('fails when no worker acknowledges the unique probe', function (): void {
    Bus::fake();

    $this->artisan('deployment:verify-queue', ['--timeout' => 1])->assertFailed();

    Bus::assertDispatched(VerifyDeploymentQueueJob::class);
});

it('succeeds only after the dispatched probe is acknowledged and removes its marker', function (): void {
    $probeKey = null;
    Bus::shouldReceive('dispatch')->once()->withArgs(function (VerifyDeploymentQueueJob $job) use (&$probeKey): bool {
        $probeKey = $job->key;
        $job->handle();

        return true;
    });

    $this->artisan('deployment:verify-queue', ['--timeout' => 1])->assertSuccessful();

    expect(Cache::has($probeKey))->toBeFalse();
});

it('expires markers if a delayed worker processes a probe after the command exits', function (): void {
    $job = new VerifyDeploymentQueueJob('deployment-probe:test-expiration');
    $job->handle();
    expect(Cache::get($job->key))->toBeTrue();

    $this->travel(121)->seconds();

    expect(Cache::has($job->key))->toBeFalse();
});

it('roundtrips through the database queue and the Laravel worker', function (): void {
    Sleep::whenFakingSleep(function (): void {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--force' => true]);
    });

    $this->artisan('deployment:verify-queue', ['--timeout' => 1])->assertSuccessful();

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});
