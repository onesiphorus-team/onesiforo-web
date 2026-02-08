<?php

declare(strict_types=1);

use App\Filament\Widgets\OnesiBoxOverviewWidget;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

it('renders the widget on the dashboard', function (): void {
    $this->get('/admin')
        ->assertSuccessful()
        ->assertSeeLivewire(OnesiBoxOverviewWidget::class);
});

it('displays active onesi boxes', function (): void {
    $box = OnesiBox::factory()->create([
        'name' => 'Test Box Alpha',
        'is_active' => true,
    ]);

    livewire(OnesiBoxOverviewWidget::class)
        ->assertSee('Test Box Alpha');
});

it('does not display inactive onesi boxes', function (): void {
    $box = OnesiBox::factory()->create([
        'name' => 'Inactive Box',
        'is_active' => false,
    ]);

    livewire(OnesiBoxOverviewWidget::class)
        ->assertDontSee('Inactive Box');
});

it('shows online status for recently seen boxes', function (): void {
    OnesiBox::factory()->create([
        'name' => 'Online Box',
        'is_active' => true,
        'last_seen_at' => now()->subMinute(),
    ]);

    livewire(OnesiBoxOverviewWidget::class)
        ->assertSee('Online Box')
        ->assertSee('Online');
});

it('shows offline status for old last seen boxes', function (): void {
    OnesiBox::factory()->create([
        'name' => 'Offline Box',
        'is_active' => true,
        'last_seen_at' => now()->subHour(),
    ]);

    livewire(OnesiBoxOverviewWidget::class)
        ->assertSee('Offline Box')
        ->assertSee('Offline');
});

it('shows recipient name when assigned', function (): void {
    $recipient = Recipient::factory()->create([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
    ]);

    OnesiBox::factory()->create([
        'name' => 'Box Con Beneficiario',
        'is_active' => true,
        'recipient_id' => $recipient->id,
    ]);

    livewire(OnesiBoxOverviewWidget::class)
        ->assertSee('Mario Rossi');
});

it('shows empty state when no boxes exist', function (): void {
    livewire(OnesiBoxOverviewWidget::class)
        ->assertSee('Nessuna OnesiBox registrata');
});
