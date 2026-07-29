<?php

declare(strict_types=1);

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Filament\Resources\OnesiBoxes\Pages\EditOnesiBox;
use App\Livewire\Filament\BoxDiagnosticsPanel;
use App\Models\OnesiBox;
use App\Models\User;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

it('serves the diagnostics tab on its own full-width page', function (): void {
    $box = OnesiBox::factory()->online()->create();

    $this->get(OnesiBoxResource::getUrl('diagnostics', ['record' => $box]))
        ->assertSuccessful()
        ->assertSeeLivewire(BoxDiagnosticsPanel::class)
        ->assertSee('Diagnostica');
});

it('does not embed the diagnostics panel in the edit form', function (): void {
    $box = OnesiBox::factory()->online()->create();

    $this->get(OnesiBoxResource::getUrl('edit', ['record' => $box]))
        ->assertSuccessful()
        ->assertDontSeeLivewire(BoxDiagnosticsPanel::class);
});

it('links the record pages together through the sub navigation', function (): void {
    $box = OnesiBox::factory()->online()->create();

    $this->get(OnesiBoxResource::getUrl('edit', ['record' => $box]))
        ->assertSuccessful()
        ->assertSee(OnesiBoxResource::getUrl('diagnostics', ['record' => $box]))
        ->assertSee(OnesiBoxResource::getUrl('screenshots', ['record' => $box]))
        ->assertSee('Configurazione');
});

it('exposes the periodic log collection settings on the edit form', function (): void {
    $box = OnesiBox::factory()->create([
        'log_collection_enabled' => true,
        'log_collection_interval_minutes' => 60,
        'log_collection_lines' => 200,
    ]);

    livewire(EditOnesiBox::class, ['record' => $box->id])
        ->assertFormSet([
            'log_collection_enabled' => true,
            'log_collection_interval_minutes' => 60,
            'log_collection_lines' => 200,
        ])
        ->fillForm([
            'log_collection_enabled' => false,
            'log_collection_interval_minutes' => 15,
            'log_collection_lines' => 300,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($box->fresh())
        ->log_collection_enabled->toBeFalse()
        ->log_collection_interval_minutes->toBe(15)
        ->log_collection_lines->toBe(300);
});
