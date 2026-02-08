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
 * Volume levels must be integers between 0 and 100, in steps of 5 (0, 5, 10, ... 100).
 */
class CreateVolumeCommandAction
{
    public const int MIN_LEVEL = 0;

    public const int MAX_LEVEL = 100;

    public const int STEP = 5;

    /**
     * Priority for volume commands (lower = higher priority).
     */
    private const int PRIORITY = 3;

    /**
     * Execute the action to create a volume command.
     *
     * @param  OnesiBox  $onesiBox  The target appliance
     * @param  int  $level  The volume level (must be 0-100, multiple of 5)
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
     * Validate that the volume level is between 0 and 100, in steps of 5.
     *
     * @throws ValidationException If the level is not valid
     */
    private function validateLevel(int $level): void
    {
        if ($level < self::MIN_LEVEL || $level > self::MAX_LEVEL || $level % self::STEP !== 0) {
            throw ValidationException::withMessages([
                'level' => [__('Il livello del volume deve essere tra :min e :max, con incrementi di :step.', [
                    'min' => self::MIN_LEVEL,
                    'max' => self::MAX_LEVEL,
                    'step' => self::STEP,
                ])],
            ]);
        }
    }
}
