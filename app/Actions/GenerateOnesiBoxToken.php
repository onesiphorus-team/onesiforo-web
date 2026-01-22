<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\OnesiBox;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;

class GenerateOnesiBoxToken
{
    /**
     * Default token expiration in days.
     */
    public const int DEFAULT_EXPIRATION_DAYS = 365;

    /**
     * Generate a new API token for an OnesiBox.
     *
     * @param  array<string>  $abilities
     */
    public function __invoke(
        OnesiBox $onesiBox,
        string $name = 'onesibox-api-token',
        array $abilities = ['*'],
        ?int $expirationDays = null
    ): NewAccessToken {
        $expirationDays ??= self::DEFAULT_EXPIRATION_DAYS;

        $token = $onesiBox->createToken(
            $name,
            $abilities,
            now()->addDays($expirationDays)
        );

        $this->logTokenGeneration($onesiBox, $name);

        return $token;
    }

    /**
     * Log token generation activity.
     */
    private function logTokenGeneration(OnesiBox $onesiBox, string $tokenName): void
    {
        activity()
            ->performedOn($onesiBox)
            ->causedBy(Auth::user())
            ->withProperties(['token_name' => $tokenName])
            ->log('API token generated');
    }
}
