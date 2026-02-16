<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcknowledgeCommandAction;
use App\Enums\ApiErrorCode;
use App\Enums\CommandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AckCommandRequest;
use App\Http\Requests\Api\V1\GetCommandsRequest;
use App\Http\Resources\Api\V1\AckCommandResource;
use App\Http\Resources\Api\V1\CommandCollection;
use App\Models\Command;
use Illuminate\Http\JsonResponse;

/**
 * Handles command operations for OnesiBox appliances.
 *
 * @tags Commands
 */
class CommandController extends Controller
{
    /**
     * Retrieve pending commands for the authenticated appliance.
     *
     * Returns a list of commands ordered by priority (1=highest) and creation date.
     * Expired commands are automatically filtered out and marked as expired.
     *
     * GET /api/v1/appliances/commands
     *
     * @response array{data: array<CommandResource>, meta: array{total: int, pending: int}}
     * @response 401 array{message: string}
     * @response 403 array{message: string, error_code: string}
     */
    public function index(GetCommandsRequest $request): CommandCollection
    {
        $onesiBox = $request->onesiBox();

        // Mark expired pending commands with a single bulk update
        $onesiBox->commands()
            ->expiredPending()
            ->update([
                'status' => CommandStatus::Expired,
                'executed_at' => now(),
            ]);

        // Get total and pending counts in a single query
        $now = now()->toDateTimeString();
        /** @var object{total: int, pending_count: int} $counts */
        $counts = $onesiBox->commands()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' AND expires_at > ? THEN 1 ELSE 0 END) as pending_count", [$now])
            ->first();

        $totalCommands = (int) $counts->total;
        $pendingCommands = (int) $counts->pending_count;

        // Build query based on filters
        $query = $request->getStatusFilter() === 'all'
            ? $onesiBox->commands()
            : $onesiBox->pendingCommands();

        $commands = $query
            ->orderByPriority()
            ->limit($request->getLimit())
            ->get();

        return new CommandCollection($commands)
            ->withMeta($totalCommands, $pendingCommands);
    }

    /**
     * Acknowledge command execution.
     *
     * Confirms the execution of a command with success, failure, or skipped status.
     * This operation is idempotent - acknowledging an already processed command returns success.
     *
     * POST /api/v1/commands/{command}/ack
     *
     * @response array{data: array{acknowledged: bool, command_id: string, status: string}}
     * @response 401 array{message: string}
     * @response 403 array{message: string, error_code: string}
     * @response 404 array{message: string, error_code: string}
     * @response 422 array{message: string, errors: array}
     */
    public function acknowledge(AckCommandRequest $request, Command $command, AcknowledgeCommandAction $action): AckCommandResource|JsonResponse
    {
        $onesiBox = $request->onesiBox();

        // Verify command belongs to this appliance
        if ($command->onesi_box_id !== $onesiBox->id) {
            return response()->json([
                'message' => 'Comando non autorizzato per questa appliance.',
                'error_code' => ApiErrorCode::Unauthorized->value,
            ], ApiErrorCode::Unauthorized->httpStatus());
        }

        // Idempotent: if already processed, return success without modifying
        if ($action->isAlreadyProcessed($command)) {
            return new AckCommandResource([
                'acknowledged' => true,
                'command_id' => $command->uuid,
                'status' => $action->getCommandStatus($command)->value,
            ]);
        }

        $action(
            command: $command,
            status: $request->input('status'),
            executedAt: $request->input('executed_at'),
            errorCode: $request->input('error_code'),
            errorMessage: $request->input('error_message'),
            result: $request->input('result'),
        );

        return new AckCommandResource([
            'acknowledged' => true,
            'command_id' => $command->uuid,
            'status' => $command->fresh()->status->value,
        ]);
    }
}
