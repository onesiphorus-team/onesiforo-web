<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\VerifyDeploymentQueueJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

#[\Illuminate\Console\Attributes\Description('Verify that an asynchronous worker processes a deployment probe')]
#[\Illuminate\Console\Attributes\Signature('deployment:verify-queue {--timeout=60 : Maximum seconds to wait for the worker}')]
class VerifyDeploymentQueueCommand extends Command
{
    public function handle(): int
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");
        $store = config('cache.default');
        $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT);

        if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)
            || in_array(config("cache.stores.{$store}.driver"), ['array', 'null'], true)
            || $timeout === false || $timeout < 1 || $timeout > 120) {
            $this->error('An asynchronous queue, shared cache and timeout between 1 and 120 seconds are required.');

            return self::FAILURE;
        }

        $key = 'deployment-probe:'.Str::uuid();

        try {
            Bus::dispatch(new VerifyDeploymentQueueJob($key));

            for ($attempt = 0; $attempt < $timeout; $attempt++) {
                Sleep::for(1)->second();

                if (Cache::get($key) === true) {
                    $this->info('Queue worker processed the deployment probe.');

                    return self::SUCCESS;
                }
            }

            $this->error('Queue worker did not acknowledge the deployment probe in time.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Queue deployment probe failed.');

            return self::FAILURE;
        } finally {
            Cache::forget($key);
        }
    }
}
