<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommandType;
use App\Enums\LogSnapshotSource;
use App\Models\Command;
use App\Models\OnesiBoxLogSnapshot;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Persists the outcome of a `get_logs` command as a log snapshot.
 *
 * Both the operator-driven request (LogViewer, admin panel) and the periodic
 * collector end up here, because every appliance answer flows through the
 * command acknowledgement endpoint.
 */
class StoreOnesiBoxLogSnapshotAction
{
    /**
     * Maximum number of lines persisted per snapshot.
     *
     * The appliance already caps its own answer at 500 lines; this is a second
     * guard so a misbehaving client cannot store an unbounded JSON blob.
     */
    private const int MAX_STORED_LINES = 500;

    /**
     * Store the successful result of a log command.
     *
     * @param  array<string, mixed>|null  $result
     */
    public function fromResult(Command $command, ?array $result): ?OnesiBoxLogSnapshot
    {
        if (! $this->handles($command)) {
            return null;
        }

        $lines = $this->extractLines($result);

        return OnesiBoxLogSnapshot::query()->create([
            'onesi_box_id' => $command->onesi_box_id,
            'command_id' => $command->id,
            'source' => $this->resolveSource($command),
            'captured_at' => $this->resolveCapturedAt($command, $result),
            'log_file' => $this->stringOrNull($result['log_file'] ?? null),
            'total_lines' => $this->intOrNull($result['total_lines'] ?? null),
            'returned_lines' => count($lines),
            'lines' => $lines,
            'error_message' => null,
        ]);
    }

    /**
     * Store a failed log command so the panel can show the failed attempt.
     */
    public function fromFailure(Command $command, ?string $errorMessage): ?OnesiBoxLogSnapshot
    {
        if (! $this->handles($command)) {
            return null;
        }

        return OnesiBoxLogSnapshot::query()->create([
            'onesi_box_id' => $command->onesi_box_id,
            'command_id' => $command->id,
            'source' => $this->resolveSource($command),
            'captured_at' => $this->resolveCapturedAt($command, null),
            'log_file' => null,
            'total_lines' => null,
            'returned_lines' => null,
            'lines' => [],
            'error_message' => $errorMessage ?? __('Errore sconosciuto'),
        ]);
    }

    private function handles(Command $command): bool
    {
        return $command->type === CommandType::GetLogs;
    }

    /**
     * The collector tags its own commands so snapshots can be told apart from
     * the ones an operator triggered by hand.
     */
    private function resolveSource(Command $command): LogSnapshotSource
    {
        $source = $command->payload['source'] ?? null;

        return is_string($source)
            ? LogSnapshotSource::tryFrom($source) ?? LogSnapshotSource::Manual
            : LogSnapshotSource::Manual;
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function resolveCapturedAt(Command $command, ?array $result): \Carbon\CarbonInterface
    {
        $timestamp = $result['timestamp'] ?? null;

        if (is_string($timestamp)) {
            try {
                return Date::parse($timestamp);
            } catch (Throwable) {
                // Fall through to the server-side timestamps below.
            }
        }

        return $command->executed_at ?? Date::now();
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @return array<int, string>
     */
    private function extractLines(?array $result): array
    {
        $lines = $result['lines'] ?? null;

        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_map(
            $this->stringifyLine(...),
            array_slice($lines, -self::MAX_STORED_LINES),
        ));
    }

    /**
     * The appliance sends plain strings; anything else is re-encoded so a
     * malformed answer still lands in the snapshot instead of blowing up.
     */
    private function stringifyLine(mixed $line): string
    {
        if (is_string($line)) {
            return $line;
        }

        $encoded = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
