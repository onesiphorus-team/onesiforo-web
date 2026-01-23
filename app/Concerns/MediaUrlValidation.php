<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Rules\JwOrgUrl;

/**
 * Provides standard validation rules for JW.org media URLs.
 *
 * This trait centralizes the validation rules used by AudioPlayer and VideoPlayer
 * components for validating media URLs from jw.org.
 */
trait MediaUrlValidation
{
    /**
     * Get the validation rules for a JW.org media URL field.
     *
     * @param  string  $fieldName  The name of the field to validate
     * @return array<string, array<int, mixed>>
     */
    protected function mediaUrlRules(string $fieldName): array
    {
        return [
            $fieldName => ['required', 'url', 'max:2048', new JwOrgUrl],
        ];
    }
}
