<?php

declare(strict_types=1);

use App\Support\Version;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    Cache::flush();
    $this->originalBasePath = base_path();
});

afterEach(function (): void {
    $sandbox = base_path();
    app()->setBasePath($this->originalBasePath);

    if ($sandbox !== $this->originalBasePath) {
        File::deleteDirectory($sandbox);
    }
});

/**
 * Points the application at an empty directory, so each test decides which
 * of `.git` and `VERSION` exist.
 */
function versionSandbox(): string
{
    $path = sys_get_temp_dir().'/onesiforo-version-'.bin2hex(random_bytes(6));
    File::makeDirectory($path);
    app()->setBasePath($path);

    return $path;
}

test('returns the git tag of the checkout', function (): void {
    $path = versionSandbox();
    File::makeDirectory($path.'/.git');
    Process::fake(['*' => Process::result(output: "v1.2.3\n")]);

    expect(Version::get())->toBe('v1.2.3');

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'describe', '--tags', '--always']
        && $process->path === $path);
});

test('reads the tag in a worktree, where .git is a file', function (): void {
    $path = versionSandbox();
    File::put($path.'/.git', "gitdir: /somewhere/else/.git/worktrees/release\n");
    Process::fake(['*' => Process::result(output: 'v1.2.3')]);

    expect(Version::get())->toBe('v1.2.3');
});

test('shows how far the checkout is from its tag', function (): void {
    File::makeDirectory(versionSandbox().'/.git');
    Process::fake(['*' => Process::result(output: 'v1.2.3-4-gabc1234')]);

    expect(Version::get())->toBe('v1.2.3-4-gabc1234');
});

test('prefers the VERSION file when the build ships one', function (): void {
    $path = versionSandbox();
    File::makeDirectory($path.'/.git');
    File::put($path.'/VERSION', "v7.0.0\n");
    Process::fake();

    expect(Version::get())->toBe('v7.0.0');

    Process::assertNothingRan();
});

test('returns dev when there is neither a repository nor a VERSION file', function (): void {
    versionSandbox();
    Process::fake();

    expect(Version::get())->toBe('dev');

    Process::assertNothingRan();
});

test('returns dev when git fails', function (): void {
    File::makeDirectory(versionSandbox().'/.git');
    Process::fake(['*' => Process::result(errorOutput: 'fatal: detected dubious ownership', exitCode: 128)]);

    expect(Version::get())->toBe('dev');
});

test('returns dev instead of failing the page when the lookup throws', function (): void {
    File::makeDirectory(versionSandbox().'/.git');
    Process::fake(['*' => fn () => throw new RuntimeException('git is not installed')]);

    expect(Version::get())->toBe('dev');
});

test('caches the version so git runs once', function (): void {
    File::makeDirectory(versionSandbox().'/.git');
    Process::fake(['*' => Process::result(output: 'v1.2.3')]);

    Version::get();
    Version::get();

    Process::assertRanTimes(fn (): bool => true, 1);
});

test('picks up a new release as soon as the checkout moves', function (): void {
    $path = versionSandbox();
    File::makeDirectory($path.'/.git/logs', recursive: true);
    File::put($path.'/.git/logs/HEAD', 'checkout: moving to v1.0.0');
    touch($path.'/.git/logs/HEAD', time() - 60);

    Process::fake(['*' => Process::sequence()->push(Process::result(output: 'v1.0.0'))->push(Process::result(output: 'v1.1.0'))]);

    expect(Version::get())->toBe('v1.0.0');

    File::append($path.'/.git/logs/HEAD', "\ncheckout: moving to v1.1.0");
    touch($path.'/.git/logs/HEAD', time());
    clearstatcache();

    expect(Version::get())->toBe('v1.1.0');
});

test('clearCache forgets the cached version', function (): void {
    File::makeDirectory(versionSandbox().'/.git');
    Process::fake(['*' => Process::sequence()->push(Process::result(output: 'v1.0.0'))->push(Process::result(output: 'v2.0.0'))]);

    expect(Version::get())->toBe('v1.0.0');

    Version::clearCache();

    expect(Version::get())->toBe('v2.0.0');
});

test('retries soon after a failed lookup instead of keeping dev for the hour', function (): void {
    File::makeDirectory(versionSandbox().'/.git');
    Process::fake(['*' => Process::sequence()->push(Process::result(exitCode: 128))->push(Process::result(output: 'v1.0.0'))]);

    expect(Version::get())->toBe('dev');

    $this->travel(2)->minutes();

    expect(Version::get())->toBe('v1.0.0');

    $this->travel(30)->minutes();

    expect(Version::get())->toBe('v1.0.0');
    Process::assertRanTimes(fn (): bool => true, 2);
});
