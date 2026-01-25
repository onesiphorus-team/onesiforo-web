<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Enums\CommandStatus;
use App\Models\Command;

/**
 * Cancels a pending command.
 *
 * Only pending commands can be cancelled. Already processed commands
 * (completed, failed, expired, cancelled) cannot be cancelled.
 */
class CancelCommandAction
{
    /**
     * Execute the action to cancel a command.
     *
     * @param  Command  $command  The command to cancel
     * @return bool True if the command was cancelled, false if it couldn't be cancelled
     */
    public function execute(Command $command): bool
    {
        if (! $command->status->isCancellable()) {
            return false;
        }

        $command->update([
            'status' => CommandStatus::Cancelled,
            'executed_at' => now(),
        ]);

        return true;
    }
}
