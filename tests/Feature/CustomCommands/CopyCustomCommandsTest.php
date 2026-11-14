<?php

declare(strict_types=1);

use App\Actions\CustomCommands\CopyCustomCommandsAction;
use App\Filament\Resources\OnesiBoxes\Pages\EditOnesiBox;
use App\Filament\Resources\OnesiBoxes\RelationManagers\CustomCommandsRelationManager;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

it('copies the commands the target box does not have yet, automatic option included', function (): void {
    $teresaBox = OnesiBox::factory()->create();
    CustomCommand::factory()->forBox($teresaBox)->runsBeforeContent()->create(['name' => 'TV sul box', 'script_name' => 'to-box.sh']);
    CustomCommand::factory()->forBox($teresaBox)->create(['name' => 'TV normale', 'script_name' => 'to-tv.sh']);
    $newBox = OnesiBox::factory()->create();
    CustomCommand::factory()->forBox($newBox)->create(['name' => 'Già presente', 'script_name' => 'to-tv.sh']);

    $copied = resolve(CopyCustomCommandsAction::class)->execute($teresaBox, $newBox);

    expect($copied)->toBe(1)
        ->and($newBox->customCommands()->orderBy('script_name')->get(['name', 'script_name', 'runs_before_content'])->toArray())->toBe([
            ['name' => 'TV sul box', 'script_name' => 'to-box.sh', 'runs_before_content' => true],
            ['name' => 'Già presente', 'script_name' => 'to-tv.sh', 'runs_before_content' => false],
        ])
        ->and($teresaBox->customCommands()->count())->toBe(2);
});

it('lets an administrator copy the commands from another box', function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $this->actingAs($admin);

    $teresaBox = OnesiBox::factory()->create();
    CustomCommand::factory()->forBox($teresaBox)->runsBeforeContent()->create(['script_name' => 'to-box.sh']);
    $newBox = OnesiBox::factory()->create();

    livewire(CustomCommandsRelationManager::class, ['ownerRecord' => $newBox, 'pageClass' => EditOnesiBox::class])
        ->callAction(TestAction::make('copy_from_box')->table(), ['source_box_id' => $teresaBox->id])
        ->assertHasNoActionErrors();

    expect($newBox->customCommands()->sole())
        ->script_name->toBe('to-box.sh')
        ->runs_before_content->toBeTrue();
});

it('saves the automatic option set by an administrator', function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $this->actingAs($admin);
    $box = OnesiBox::factory()->create();

    livewire(CustomCommandsRelationManager::class, ['ownerRecord' => $box, 'pageClass' => EditOnesiBox::class])
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'TV sul box',
            'script_name' => 'to-box.sh',
            'runs_before_content' => true,
        ])
        ->assertHasNoActionErrors();

    expect($box->customCommands()->sole()->runs_before_content)->toBeTrue();
});
