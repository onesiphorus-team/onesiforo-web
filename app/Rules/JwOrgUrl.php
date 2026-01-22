<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL is a valid JW.org media URL.
 *
 * Accepted formats:
 * - https://www.jw.org/.../video/#it/mediaitems/.../pub-xxx_VIDEO
 * - https://www.jw.org/.../audio/#it/mediaitems/.../pub-xxx_AUDIO
 */
class JwOrgUrl implements ValidationRule
{
    /**
     * Valid JW.org domains.
     */
    private const VALID_DOMAINS = [
        'jw.org',
        'www.jw.org',
        'wol.jw.org',
    ];

    /**
     * Pattern to match JW.org media URLs.
     */
    private const MEDIA_PATTERN = '/#[a-z]{2,3}\/mediaitems\/[^\/]+\/[a-zA-Z0-9_-]+/i';

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || empty($value)) {
            $fail('L\'URL è obbligatorio.');

            return;
        }

        // Parse URL
        $parsed = parse_url($value);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            $fail('L\'URL non è valido.');

            return;
        }

        // Must be HTTPS
        if ($parsed['scheme'] !== 'https') {
            $fail('L\'URL deve utilizzare HTTPS.');

            return;
        }

        // Must be a valid JW.org domain
        $host = strtolower($parsed['host']);
        if (! $this->isValidJwDomain($host)) {
            $fail('L\'URL deve essere un link di jw.org.');

            return;
        }

        // Must contain media item pattern in fragment
        $fragment = $parsed['fragment'] ?? '';
        $fullUrl = $value;

        if (! preg_match(self::MEDIA_PATTERN, '#' . $fragment) &&
            ! preg_match(self::MEDIA_PATTERN, $fullUrl)) {
            $fail('L\'URL deve essere un link diretto a un video o audio di jw.org. Apri il video su jw.org e copia l\'URL dalla barra degli indirizzi.');

            return;
        }
    }

    /**
     * Check if the host is a valid JW.org domain.
     */
    private function isValidJwDomain(string $host): bool
    {
        // Exact match
        if (in_array($host, self::VALID_DOMAINS, true)) {
            return true;
        }

        // Subdomain of jw.org
        if (str_ends_with($host, '.jw.org')) {
            return true;
        }

        return false;
    }
}
