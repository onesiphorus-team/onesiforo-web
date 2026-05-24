<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\Roles;
use App\Livewire\Dashboard\Controls\CustomCommandsList;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Livewire\Livewire;
use Mockery\MockInterface;

it('renders enabled custom commands for caregiver with full permission', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    CustomCommand::factory()->forBox($onesiBox)->create(['name' => 'Box TV', 'script_name' => 'to-box.sh']);
    CustomCommand::factory()->forBox($onesiBox)->create(['name' => 'Live TV', 'script_name' => 'to-tv.sh']);

    Livewire::actingAs($user)
        ->test(CustomCommandsList::class, ['onesiBox' => $onesiBox])
        ->assertSee('Comandi personalizzati')
        ->assertSee('Box TV')
        ->assertSee('Live TV');
});

it('hides section for caregiver with read-only permission', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    CustomCommand::factory()->forBox($onesiBox)->create(['name' => 'Box TV']);

    Livewire::actingAs($user)
        ->test(CustomCommandsList::class, ['onesiBox' => $onesiBox])
        ->assertDontSee('Comandi personalizzati')
        ->assertDontSee('Box TV');
});

it('hides disabled commands', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    CustomCommand::factory()->forBox($onesiBox)->disabled()->create(['name' => 'Spento']);

    Livewire::actingAs($user)
        ->test(CustomCommandsList::class, ['onesiBox' => $onesiBox])
        ->assertDontSee('Spento');
});

it('hides the section entirely when the box has no enabled commands', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(CustomCommandsList::class, ['onesiBox' => $onesiBox])
        ->assertDontSee('Comandi personalizzati');
});

it('runs the command via the service when Full caregiver clicks', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $cmd = CustomCommand::factory()->forBox($onesiBox)->create(['name' => 'Box TV', 'script_name' => 'to-box.sh']);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock) use ($onesiBox, $cmd): void {
        $mock->shouldReceive('sendCustomScriptCommand')
            ->once()
            ->withArgs(fn (OnesiBox $box, CustomCommand $custom): bool => $box->is($onesiBox) && $custom->is($cmd));
    });

    Livewire::actingAs($user)
        ->test(CustomCommandsList::class, ['onesiBox' => $onesiBox])
        ->call('run', $cmd->id);
});

it('blocks run for caregiver with read-only permission', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    $cmd = CustomCommand::factory()->forBox($onesiBox)->create();

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('sendCustomScriptCommand');
    });

    Livewire::actingAs($user)
        ->test(CustomCommandsList::class, ['onesiBox' => $onesiBox])
        ->call('run', $cmd->id)
        ->assertForbidden();
});

it('ignores commands from another box', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $otherBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $otherCmd = CustomCommand::factory()->forBox($otherBox)->create();

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('sendCustomScriptCommand');
    });

    Livewire::actingAs($user)
        ->test(CustomCommandsList::class, ['onesiBox' => $onesiBox])
        ->call('run', $otherCmd->id);
});

it('ignores disabled commands at run time', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $cmd = CustomCommand::factory()->forBox($onesiBox)->disabled()->create();

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('sendCustomScriptCommand');
    });

    Livewire::actingAs($user)
        ->test(CustomCommandsList::class, ['onesiBox' => $onesiBox])
        ->call('run', $cmd->id);
});
