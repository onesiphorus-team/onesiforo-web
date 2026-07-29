<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Livewire\Filament\BoxDiagnosticsPanel;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;
use Oltrematica\RoleLite\Models\Role;

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
});

it('lists the most recent commands sent to the box', function (): void {
    $box = OnesiBox::factory()->online()->create();

    Command::factory()
        ->for($box, 'onesiBox')
        ->completed()
        ->ofType(CommandType::Reboot)
        ->create();

    Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->assertSee('Ultimi comandi')
        ->assertSee(CommandType::Reboot->getLabel());
});

it('caps the command list to the last five', function (): void {
    $box = OnesiBox::factory()->online()->create();

    Command::factory()->count(9)->for($box, 'onesiBox')->completed()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box]);

    expect($component->instance()->recentCommands)->toHaveCount(5);
});
