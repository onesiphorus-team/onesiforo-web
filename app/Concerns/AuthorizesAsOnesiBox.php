<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\OnesiBox;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Log;

/**
 * Provides authorization logic for Form Requests from OnesiBox appliances.
 *
 * This trait centralizes the authorize() and onesiBox() methods used by
 * API Form Requests that authenticate via Sanctum tokens issued to OnesiBox models.
 */
trait AuthorizesAsOnesiBox
{
    /**
     * Determine if the appliance is authorized to make this request.
     * The token must belong to an OnesiBox instance.
     */
    public function authorize(): bool
    {
        /** @var AuthenticatableContract|null $tokenable */
        $tokenable = $this->user();

        if ($tokenable instanceof OnesiBox) {
            Log::debug("{$this->getAuthLogContext()} authorized", ['onesibox_id' => $tokenable->id]);

            return true;
        }

        Log::warning("{$this->getAuthLogContext()} unauthorized - not an OnesiBox", [
            'tokenable_type' => $tokenable !== null ? $tokenable::class : 'null',
        ]);

        return false;
    }

    /**
     * Get the authenticated OnesiBox instance.
     */
    public function onesiBox(): OnesiBox
    {
        /** @var OnesiBox $onesiBox */
        $onesiBox = $this->user();

        return $onesiBox;
    }

    /**
     * Get the log context identifier for debugging.
     *
     * Override this method in the Form Request to customize the log prefix.
     */
    protected function getAuthLogContext(): string
    {
        return class_basename(static::class);
    }
}
