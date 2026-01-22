<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class Version
{
    /**
     * Get the application version from git tag or VERSION file.
     */
    public static function get(): string
    {
        return Cache::remember('app_version', now()->addHour(), function (): string {
            // First try to read from VERSION file (for production without .git)
            $versionFile = base_path('VERSION');
            if (file_exists($versionFile)) {
                return trim(file_get_contents($versionFile));
            }

            // Fallback to git describe
            if (is_dir(base_path('.git'))) {
                $result = Process::run('git describe --tags --abbrev=0 2>/dev/null');

                if ($result->successful() && ! empty(trim($result->output()))) {
                    return trim($result->output());
                }
            }

            return 'dev';
        });
    }

    /**
     * Clear the cached version.
     */
    public static function clearCache(): void
    {
        Cache::forget('app_version');
    }
}
