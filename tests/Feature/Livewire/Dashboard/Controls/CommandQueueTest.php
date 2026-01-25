<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\CommandQueue;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

test('command queue shows pending commands for onesibox', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    $command1 = Command::factory()->for($onesiBox)->pending()->create([
        'type' => CommandType::SetVolume,
        'payload' => ['level' => 60],
    ]);
    $command2 = Command::factory()->for($onesiBox)->pending()->create([
        'type' => CommandType::PlayMedia,
    ]);

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox])
        ->assertSee($command1->type->getLabel())
        ->assertSee($command2->type->getLabel())
        ->assertStatus(200);
});

test('command queue shows empty state when no pending commands', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox])
        ->assertSee('Nessun comando in coda')
        ->assertStatus(200);
});

test('command queue does not show completed commands', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    $pendingCommand = Command::factory()->for($onesiBox)->pending()->create([
        'type' => CommandType::SetVolume,
    ]);
    $completedCommand = Command::factory()->for($onesiBox)->completed()->create([
        'type' => CommandType::PlayMedia,
    ]);

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox])
        ->assertSee($pendingCommand->type->getLabel())
        ->assertSet('pendingCommands', fn ($commands) => $commands->where('id', $completedCommand->id)->isEmpty())
        ->assertStatus(200);
});

test('full permission user can cancel a command', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    $command = Command::factory()->for($onesiBox)->pending()->create();

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox])
        ->call('cancelCommand', $command->uuid)
        ->assertDispatched('notify');

    expect($command->fresh()->status)->toBe(CommandStatus::Cancelled);
});

test('readonly user cannot cancel commands', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly]);

    $command = Command::factory()->for($onesiBox)->pending()->create();

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox])
        ->assertSet('canControl', false)
        ->call('cancelCommand', $command->uuid);

    // Command should still be pending
    expect($command->fresh()->status)->toBe(CommandStatus::Pending);
});

test('full permission user can cancel all pending commands', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    $commands = Command::factory()->for($onesiBox)->pending()->count(3)->create();

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox])
        ->call('cancelAll')
        ->assertDispatched('notify');

    foreach ($commands as $command) {
        expect($command->fresh()->status)->toBe(CommandStatus::Cancelled);
    }
});

test('readonly user cannot cancel all commands', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly]);

    $commands = Command::factory()->for($onesiBox)->pending()->count(3)->create();

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox])
        ->assertSet('canControl', false)
        ->call('cancelAll');

    // Commands should still be pending
    foreach ($commands as $command) {
        expect($command->fresh()->status)->toBe(CommandStatus::Pending);
    }
});

test('command queue shows command priority', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    $highPriority = Command::factory()->for($onesiBox)->pending()->withPriority(1)->create();
    $lowPriority = Command::factory()->for($onesiBox)->pending()->withPriority(5)->create();

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox])
        ->assertSet('pendingCommands',
            // High priority should be first
            fn ($commands): bool => $commands->first()->id === $highPriority->id
            && $commands->last()->id === $lowPriority->id)
        ->assertStatus(200);
});

test('cannot cancel command from another onesibox', function (): void {
    $user = User::factory()->create();
    $onesiBox1 = OnesiBox::factory()->online()->create();
    $onesiBox2 = OnesiBox::factory()->online()->create();
    $onesiBox1->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    $command = Command::factory()->for($onesiBox2)->pending()->create();

    Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox1])
        ->call('cancelCommand', $command->uuid);

    // Command should still be pending (not cancelled)
    expect($command->fresh()->status)->toBe(CommandStatus::Pending);
});

test('command queue refreshes after cancellation', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    $command1 = Command::factory()->for($onesiBox)->pending()->create();
    $command2 = Command::factory()->for($onesiBox)->pending()->create();

    $component = Livewire::actingAs($user)
        ->test(CommandQueue::class, ['onesiBox' => $onesiBox]);

    // Initially both commands are visible
    $component->assertSet('pendingCommands', fn ($commands): bool => $commands->count() === 2);

    // Cancel one command
    $component->call('cancelCommand', $command1->uuid);

    // After refresh, only one command should be visible
    $component->assertSet('pendingCommands', fn ($commands): bool => $commands->count() === 1);
});
