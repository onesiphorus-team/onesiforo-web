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
 * - Optional query string (?pwd=..., ?omn=...&pwd=...) and fragment.
 *
 * The value must be the bare link: whitespace, newlines, control characters, quotes,
 * backslashes, credentials and non-ASCII characters are refused, because the URL is sent
 * as-is to the box. Callers taking pasted input should trim it first.
 */
class ZoomUrl implements ValidationRule
{
    /**
     * Error message shown when the URL is not a valid Zoom URL.
     */
    public const string ERROR_MESSAGE = 'Questo non sembra un link Zoom. Controlla di averlo copiato per intero: deve somigliare a https://us05web.zoom.us/j/1234567890';

    public const int MAX_LENGTH = 2048;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || strlen($value) > self::MAX_LENGTH) {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        // Only printable ASCII that can appear in a URL: no spaces, newlines or control characters.
        if (preg_match('/[^\x21-\x7E]|["<>\\\\^`{|}]/', $value) === 1) {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        $parsed = parse_url($value);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'], $parsed['path'])) {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        if ($parsed['scheme'] !== 'https' || isset($parsed['user']) || isset($parsed['pass'])) {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        $host = strtolower($parsed['host']);
        if ($host !== 'zoom.us' && ! str_ends_with($host, '.zoom.us')) {
            $fail(self::ERROR_MESSAGE);

            return;
        }

        if (preg_match('#\A/(j|w|s)/\d+/?\z#', $parsed['path']) !== 1) {
            $fail(self::ERROR_MESSAGE);

            return;
        }
    }
}
