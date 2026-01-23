<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommandStatus;
use App\Models\Command;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Handles the acknowledgment of a command execution.
 *
 * This action encapsulates the logic for processing command acknowledgments
 * from OnesiBox appliances, handling success, failure, and skipped statuses.
 */
class AcknowledgeCommandAction
{
    /**
     * Acknowledge a command with the given status and metadata.
     *
     * This operation is idempotent - acknowledging an already processed command
     * returns true without modifying the command.
     *
     * @param  Command  $command  The command to acknowledge
     * @param  string  $status  The acknowledgment status: 'success', 'failed', or 'skipped'
     * @param  CarbonInterface|string  $executedAt  When the command was executed
     * @param  string|null  $errorCode  Error code (for failed status)
     * @param  string|null  $errorMessage  Error message (for failed status)
     * @return bool True if acknowledgment was processed (or already processed)
     */
    public function __invoke(
        Command $command,
        string $status,
        CarbonInterface|string $executedAt,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): bool {
        // Idempotent: if already processed, return success without modifying
        if ($command->status !== CommandStatus::Pending) {
            return true;
        }

        $executedAtCarbon = $executedAt instanceof CarbonInterface
            ? $executedAt
            : Date::parse($executedAt);

        return match ($status) {
            'success', 'skipped' => $this->handleSuccess($command, $executedAtCarbon),
            'failed' => $this->handleFailure($command, $executedAtCarbon, $errorCode, $errorMessage),
            default => false,
        };
    }

    /**
     * Check if a command has already been processed.
     */
    public function isAlreadyProcessed(Command $command): bool
    {
        return $command->status !== CommandStatus::Pending;
    }

    /**
     * Get the current status of a command.
     */
    public function getCommandStatus(Command $command): CommandStatus
    {
        return $command->status;
    }

    /**
     * Handle successful or skipped command acknowledgment.
     */
    private function handleSuccess(Command $command, CarbonInterface $executedAt): bool
    {
        $command->markAsCompleted($executedAt);

        return true;
    }

    /**
     * Handle failed command acknowledgment.
     */
    private function handleFailure(
        Command $command,
        CarbonInterface $executedAt,
        ?string $errorCode,
        ?string $errorMessage
    ): bool {
        $command->markAsFailed($errorCode, $errorMessage, $executedAt);

        return true;
    }
}
