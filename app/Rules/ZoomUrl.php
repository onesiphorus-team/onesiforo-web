<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL is a valid Zoom meeting/webinar URL.
 *
 * Accepted formats:
 * - https://*.zoom.us/j/<digits>
 * - https://*.zoom.us/w/<digits>
 * - https://*.zoom.us/s/<digits>
 * - Optional ?pwd=... query string.
 */
class ZoomUrl implements ValidationRule
{
    /**
     * Error message shown when the URL is not a valid Zoom URL.
     */
    private const string ERROR_MESSAGE = 'URL Zoom non valido. Es: https://us05web.zoom.us/j/1234567890';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        $parsed = parse_url($value);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'], $parsed['path'])) {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        if ($parsed['scheme'] !== 'https') {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        $host = strtolower($parsed['host']);
        if ($host !== 'zoom.us' && ! str_ends_with($host, '.zoom.us')) {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        if (preg_match('#^/(j|w|s)/\d+$#', $parsed['path']) !== 1) {
            $fail(self::ERROR_MESSAGE);

            return;
        }
    }
}
