<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Command;
use App\Models\OnesiBox;
use Illuminate\Validation\ValidationException;

/**
 * Creates a volume command for an OnesiBox appliance.
 *
 * Volume levels are restricted to 6 preset values: 0% (mute), 60%, 70%, 80%, 90%, 100%.
 */
class CreateVolumeCommandAction
{
    /**
     * Valid volume levels (preset values).
     *
     * @var list<int>
     */
    public const array VALID_LEVELS = [0, 60, 70, 80, 90, 100];

    /**
     * Priority for volume commands (lower = higher priority).
     */
    private const int PRIORITY = 3;

    /**
     * Execute the action to create a volume command.
     *
     * @param  OnesiBox  $onesiBox  The target appliance
     * @param  int  $level  The volume level (must be 0, 60, 70, 80, 90, or 100)
     * @return Command The created command
     *
     * @throws ValidationException If the volume level is invalid
     */
    public function execute(OnesiBox $onesiBox, int $level): Command
    {
        $this->validateLevel($level);

        return Command::query()->create([
            'onesi_box_id' => $onesiBox->id,
            'type' => CommandType::SetVolume,
            'status' => CommandStatus::Pending,
            'payload' => ['level' => $level],
            'priority' => self::PRIORITY,
        ]);
    }

    /**
     * Validate that the volume level is one of the preset values.
     *
     * @throws ValidationException If the level is not valid
     */
    private function validateLevel(int $level): void
    {
        if (! in_array($level, self::VALID_LEVELS, true)) {
            throw ValidationException::withMessages([
                'level' => [__('Il livello del volume deve essere uno di: :levels.', [
                    'levels' => implode(', ', self::VALID_LEVELS),
                ])],
            ]);
        }
    }
}
