<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\Roles;
use App\Livewire\Dashboard\Controls\SystemControls;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Livewire\Livewire;
use Mockery\MockInterface;

it('shows reboot button for admin user', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->assertSee('Riavvia Dispositivo');
});

it('hides reboot button for non-admin user', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->assertDontSee('Riavvia Dispositivo')
        ->assertSee('Solo gli amministratori possono accedere ai controlli di sistema');
});

it('shows confirmation dialog when reboot is clicked', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->call('confirmReboot')
        ->assertSet('showRebootConfirm', true)
        ->assertSee('Conferma riavvio');
});

it('hides confirmation dialog when cancel is clicked', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->set('showRebootConfirm', true)
        ->call('cancelReboot')
        ->assertSet('showRebootConfirm', false);
});

it('sends reboot command for admin with full permission', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendRebootCommand')
            ->once()
            ->withArgs(fn ($box): bool => $box instanceof OnesiBox);
    });

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->set('showRebootConfirm', true)
        ->call('reboot')
        ->assertSet('showRebootConfirm', false);
});

it('blocks reboot for non-admin user', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('sendRebootCommand');
    });

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->call('reboot');
});

it('blocks reboot with readonly permission', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->call('reboot')
        ->assertForbidden();
});

// RestartService tests

it('shows restart service button for admin user', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->assertSee('Riavvia Servizio OnesiBox');
});

it('shows confirmation dialog when restart service is clicked', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->call('confirmRestartService')
        ->assertSet('showRestartServiceConfirm', true)
        ->assertSee('Conferma riavvio servizio');
});

it('hides confirmation dialog when cancel restart service is clicked', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->set('showRestartServiceConfirm', true)
        ->call('cancelRestartService')
        ->assertSet('showRestartServiceConfirm', false);
});

it('sends restart service command for admin with full permission', function (): void {
    $user = User::factory()->admin()->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendRestartServiceCommand')
            ->once()
            ->withArgs(fn ($box): bool => $box instanceof OnesiBox);
    });

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->set('showRestartServiceConfirm', true)
        ->call('restartService')
        ->assertSet('showRestartServiceConfirm', false);
});

it('blocks restart service for non-admin user', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();

    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('sendRestartServiceCommand');
    });

    Livewire::actingAs($user)
        ->test(SystemControls::class, ['onesiBox' => $onesiBox])
        ->call('restartService');
});
