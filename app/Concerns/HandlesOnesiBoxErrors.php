<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Exceptions\OnesiBoxOfflineException;
use Closure;
use Flux\Flux;

/**
 * Provides standardized error handling for OnesiBox command operations in Livewire components.
 *
 * This trait provides a method to execute commands and handle OnesiBoxOfflineException
 * with consistent user feedback via Flux toast notifications.
 */
trait HandlesOnesiBoxErrors
{
    /**
     * Execute a command and handle OnesiBoxOfflineException.
     *
     * @param  Closure  $callback  The command to execute
     * @param  string  $successMessage  Message to show on success
     * @param  string  $offlineMessage  Message to show when OnesiBox is offline
     * @return bool True if successful, false if offline exception occurred
     */
    protected function executeWithErrorHandling(
        Closure $callback,
        string $successMessage,
        string $offlineMessage = 'OnesiBox non raggiungibile'
    ): bool {
        try {
            $callback();
            Flux::toast($successMessage);

            return true;
        } catch (OnesiBoxOfflineException) {
            Flux::toast($offlineMessage, variant: 'danger');

            return false;
        }
    }
}
