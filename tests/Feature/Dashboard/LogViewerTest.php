<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Enums\Roles;
use App\Livewire\Dashboard\Controls\LogViewer;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

it('shows access denied message for non-admin users', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->assertSee('Solo gli amministratori possono visualizzare i log di sistema');
});

it('shows log request form for admin users', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->assertSee('Richiedi Log')
        ->assertSee('Righe da recuperare');
});

it('shows offline message when device is offline', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->offline()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->assertSee('I log sono disponibili solo quando il dispositivo è online');
});

it('creates get_logs command when requesting logs', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->set('lines', 50)
        ->call('requestLogs')
        ->assertSet('isLoading', true);

    $command = Command::query()
        ->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::GetLogs)
        ->first();

    expect($command)->not->toBeNull();
    expect($command->payload)->toBe(['lines' => 50]);
    expect($command->status)->toBe(CommandStatus::Pending);
});

it('validates lines must be between 10 and 500', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->set('lines', 5)
        ->call('requestLogs')
        ->assertHasErrors(['lines' => 'min']);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->set('lines', 600)
        ->call('requestLogs')
        ->assertHasErrors(['lines' => 'max']);
});

it('clears logs when clearLogs is called', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->set('logs', 'Some log content')
        ->set('isLoading', true)
        ->set('pendingCommandId', 123)
        ->call('clearLogs')
        ->assertSet('logs', null)
        ->assertSet('isLoading', false)
        ->assertSet('pendingCommandId', null);
});

it('updates logs when command completes successfully', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $command = Command::query()->create([
        'onesi_box_id' => $onesiBox->id,
        'type' => CommandType::GetLogs,
        'payload' => ['lines' => 100],
        'status' => CommandStatus::Completed,
        'result' => ['logs' => 'Log line 1\nLog line 2'],
    ]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->set('pendingCommandId', $command->id)
        ->set('isLoading', true)
        ->call('checkCommandStatus')
        ->assertSet('isLoading', false)
        ->assertSet('logs', 'Log line 1\nLog line 2')
        ->assertSet('pendingCommandId', null);
});

it('shows error message when command fails', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $command = Command::query()->create([
        'onesi_box_id' => $onesiBox->id,
        'type' => CommandType::GetLogs,
        'payload' => ['lines' => 100],
        'status' => CommandStatus::Failed,
        'error_message' => 'Permission denied',
    ]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->set('pendingCommandId', $command->id)
        ->set('isLoading', true)
        ->call('checkCommandStatus')
        ->assertSet('isLoading', false)
        ->assertSee('Permission denied');
});

it('does not create command for non-admin user', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->call('requestLogs');

    $commandCount = Command::query()
        ->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::GetLogs)
        ->count();

    expect($commandCount)->toBe(0);
});

it('sets default lines to 100', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(LogViewer::class, ['onesiBox' => $onesiBox])
        ->assertSet('lines', 100);
});
