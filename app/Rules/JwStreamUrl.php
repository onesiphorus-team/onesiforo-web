<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL is a stream.jw.org URL.
 *
 * Accepted formats:
 * - https://stream.jw.org/NNNN-NNNN-NNNN-NNNN (share token)
 * - https://stream.jw.org/home (post-redirect)
 * - https://stream.jw.org/home?playerOpen=true
 */
class JwStreamUrl implements ValidationRule
{
    private const int MAX_URL_LENGTH = 2048;

    private const string VALID_HOST = 'stream.jw.org';

    /**
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('L\'URL è obbligatorio.');

            return;
        }

        if (strlen($value) > self::MAX_URL_LENGTH) {
            $fail('L\'URL non può superare 2048 caratteri.');

            return;
        }

        $parsed = parse_url($value);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            $fail('L\'URL non è valido.');

            return;
        }

        if ($parsed['scheme'] !== 'https') {
            $fail('L\'URL deve utilizzare HTTPS.');

            return;
        }

        if (isset($parsed['port']) && $parsed['port'] !== 443) {
            $fail('L\'URL non può usare porte non standard.');

            return;
        }

        $host = strtolower($parsed['host']);

        if ($host !== self::VALID_HOST) {
            $fail('L\'URL deve essere un link di stream.jw.org.');

            return;
        }
    }
}
