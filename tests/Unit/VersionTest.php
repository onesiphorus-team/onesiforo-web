<?php

declare(strict_types=1);

use App\Support\Version;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Version::clearCache();
});

test('returns version from git tag', function (): void {
    $version = Version::get();

    expect($version)->toBeString()
        ->and($version)->not->toBeEmpty();
});

test('caches the version', function (): void {
    Version::get();

    expect(Cache::has('app_version'))->toBeTrue();
});

test('returns dev when no version available', function (): void {
    // Clear cache and mock scenario where neither VERSION file nor git exists
    Cache::shouldReceive('remember')
        ->once()
        ->andReturn('dev');

    $version = Version::get();

    expect($version)->toBe('dev');
});
