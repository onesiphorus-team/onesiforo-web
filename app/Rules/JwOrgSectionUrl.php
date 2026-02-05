<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL is a valid JW.org section/category URL.
 *
 * Accepted format:
 * - https://www.jw.org/{lang}/biblioteca/video/#XX/categories/{CategoryKey}
 */
class JwOrgSectionUrl implements ValidationRule
{
    /**
     * Pattern to match JW.org section/category URLs.
     */
    private const string SECTION_PATTERN = '/#[a-z]{2,3}\/categories\/[a-zA-Z0-9_-]+/i';

    /**
     * Valid JW.org domains.
     */
    private const array VALID_DOMAINS = [
        'jw.org',
        'www.jw.org',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ($value === '' || $value === '0')) {
            $fail('L\'URL è obbligatorio.');

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

        $host = strtolower($parsed['host']);
        if (! in_array($host, self::VALID_DOMAINS, true) && ! str_ends_with($host, '.jw.org')) {
            $fail('L\'URL deve essere un link di jw.org.');

            return;
        }

        $fragment = $parsed['fragment'] ?? '';

        if (! preg_match(self::SECTION_PATTERN, '#'.$fragment)) {
            $fail('L\'URL deve essere un link a una sezione video di jw.org (es. .../video/#it/categories/VODBible).');

            return;
        }
    }
}
