<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;

it('reports the exact live revision and tag without the cached display version', function (): void {
    cache()->put('app_version', 'v0.0.1');
    Process::fake(fn (PendingProcess $process) => Process::result(
        output: $process->command === ['git', 'rev-parse', 'HEAD'] ? str_repeat('a', 40) : 'v1.2.3',
    ));

    $this->getJson(route('deployment.health'))
        ->assertSuccessful()
        ->assertExactJson(['status' => 'ok', 'tags' => ['v1.2.3'], 'revision' => str_repeat('a', 40)])
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('fails readiness when the deployed checkout has no exact tag', function (): void {
    Process::fake(fn (PendingProcess $process) => $process->command === ['git', 'rev-parse', 'HEAD']
        ? Process::result(output: str_repeat('a', 40))
        : Process::result(exitCode: 128));

    $this->getJson(route('deployment.health'))->assertServiceUnavailable()
        ->assertExactJson(['status' => 'unavailable']);
});

it('fails readiness without exposing database errors', function (): void {
    Exceptions::fake();
    DB::shouldReceive('select')->with('SELECT 1')->once()->andThrow(new RuntimeException('private database details'));

    $this->getJson(route('deployment.health'))->assertServiceUnavailable()
        ->assertExactJson(['status' => 'unavailable']);
});

it('reports every tag pointing to the served revision', function (): void {
    Process::fake(fn (PendingProcess $process) => Process::result(
        output: $process->command === ['git', 'rev-parse', 'HEAD'] ? str_repeat('a', 40) : "v1.2.3\nv1.2.4\n",
    ));

    $this->getJson(route('deployment.health'))
        ->assertSuccessful()
        ->assertJsonPath('tags', ['v1.2.3', 'v1.2.4']);
});

it('fails readiness when HEAD has no release tags', function (): void {
    Process::fake(fn (PendingProcess $process) => Process::result(
        output: $process->command === ['git', 'rev-parse', 'HEAD'] ? str_repeat('a', 40) : '',
    ));

    $this->getJson(route('deployment.health'))->assertServiceUnavailable();
});
