<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
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

    public function __construct(
        private readonly OnesiBoxCommandServiceInterface $commandService,
    ) {}

    /**
     * Execute the action to create a volume command.
     *
     * @param  OnesiBox  $onesiBox  The target appliance
     * @param  int  $level  The volume level (must be 0-100, multiple of 5)
     *
     * @throws ValidationException If the volume level is invalid
     * @throws \App\Exceptions\OnesiBoxOfflineException If the appliance is offline
     */
    public function execute(OnesiBox $onesiBox, int $level): void
    {
        $this->validateLevel($level);

        $this->commandService->sendVolumeCommand($onesiBox, $level);
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
