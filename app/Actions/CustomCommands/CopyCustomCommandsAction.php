<?php

declare(strict_types=1);

namespace App\Actions\CustomCommands;

use App\Models\OnesiBox;

/**
 * Copies the custom commands of a box to another one, e.g. to set up a new box connected
 * to the same kind of TV. Commands for a script the target already has are skipped.
 *
 * The scripts themselves still have to be installed on the target box.
 */
class CopyCustomCommandsAction
{
    /**
     * @return int How many commands were copied
     */
    public function execute(OnesiBox $source, OnesiBox $target): int
    {
        $existingScripts = $target->customCommands()->pluck('script_name')->all();

        $commandsToCopy = $source->customCommands()
            ->whereNotIn('script_name', $existingScripts)
            ->ordered()
            ->get();

        foreach ($commandsToCopy as $customCommand) {
            $target->customCommands()->create($customCommand->only([
                'name',
                'description',
                'script_name',
                'static_args',
                'icon',
                'sort_order',
                'is_enabled',
                'runs_before_content',
            ]));
        }

        return $commandsToCopy->count();
    }
}
