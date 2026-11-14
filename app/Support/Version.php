<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * The version shown to users, taken from the git tag of the deployed checkout.
 *
 * A VERSION file wins when present, for builds shipped without a repository
 * (the Docker image). Anything that cannot be resolved reads as "dev".
 */
class Version
{
    private const string FALLBACK = 'dev';

    public static function get(): string
    {
        try {
            $key = self::cacheKey();
            $cached = Cache::get($key);

            if (is_string($cached)) {
                return $cached;
            }

            $version = self::resolve();

            // A failed lookup is retried soon, so one bad moment right after
            // a deploy does not label the release "dev" for an hour.
            Cache::put($key, $version, $version === self::FALLBACK ? now()->addMinute() : now()->addHour());

            return $version;
        } catch (Throwable $exception) {
            // The version is decoration: it must never take a page down.
            report($exception);

            return self::FALLBACK;
        }
    }

    public static function clearCache(): void
    {
        Cache::forget(self::cacheKey());
    }

    private static function resolve(): string
    {
        $versionFile = base_path('VERSION');

        if (is_file($versionFile)) {
            $content = trim((string) file_get_contents($versionFile));

            if ($content !== '') {
                return $content;
            }
        }

        // `.git` is a directory in a clone and a plain file in a worktree, so
        // only its existence is checked. On a tagged commit this prints the tag
        // alone; anywhere else it adds the distance and the short hash, so a
        // checkout that is ahead of its tag does not pass for the release.
        if (file_exists(base_path('.git'))) {
            $result = Process::path(base_path())->run(['git', 'describe', '--tags', '--always']);
            $described = trim($result->output());

            if ($result->successful() && $described !== '') {
                return $described;
            }
        }

        return self::FALLBACK;
    }

    /**
     * The key changes whenever the checkout moves to another commit, so a
     * deploy shows its own version at once instead of the previous one until
     * the cache expires. Superseded keys simply expire.
     */
    private static function cacheKey(): string
    {
        $fingerprint = collect([base_path('VERSION'), self::gitHeadFile()])
            ->filter(fn (?string $path): bool => $path !== null && is_file($path))
            ->map(fn (string $path): string => $path.'@'.filemtime($path))
            ->implode('|');

        return 'app_version:'.hash('xxh128', base_path().'|'.$fingerprint);
    }

    /**
     * The reflog of HEAD, which git appends to on every checkout, commit and
     * pull (the HEAD file itself where reflogs are off). Resolved through the
     * `gitdir:` pointer when `.git` is a worktree file.
     */
    private static function gitHeadFile(): ?string
    {
        $git = base_path('.git');

        if (is_file($git) && preg_match('/^gitdir:\s*(.+)$/m', (string) file_get_contents($git), $matches) === 1) {
            $gitDir = trim($matches[1]);
            $git = str_starts_with($gitDir, '/') ? $gitDir : base_path($gitDir);
        }

        if (! is_dir($git)) {
            return null;
        }

        return is_file($git.'/logs/HEAD') ? $git.'/logs/HEAD' : $git.'/HEAD';
    }
}
