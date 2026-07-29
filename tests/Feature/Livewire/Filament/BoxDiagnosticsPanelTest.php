<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\LogSnapshotSource;
use App\Jobs\SendOnesiBoxCommand;
use App\Livewire\Filament\BoxDiagnosticsPanel;
use App\Models\ApplianceScreenshot;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\OnesiBoxLogSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Oltrematica\RoleLite\Models\Role;

beforeEach(function (): void {
    Queue::fake();

    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

it('shows the telemetry already exposed by the dashboard widget', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'cpu_usage' => 61,
        'memory_usage' => 42,
        'temperature' => 63.3,
        'disk_usage' => 12,
        'last_system_info_at' => now()->subMinute(),
    ]);

    Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->assertSee('61%')
        ->assertSee('42%')
        ->assertSee('63.3°C')
        ->assertSee('Telemetria');
});

it('lists the last contacts of the device', function (): void {
    $box = OnesiBox::factory()->online()->create();

    Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->assertSee('Ultimi contatti')
        ->assertSee('Ultimo heartbeat')
        ->assertSee('Ultimo log ricevuto');
});

it('shows the most recent screenshots', function (): void {
    $box = OnesiBox::factory()->online()->create();

    foreach (range(1, 8) as $i) {
        ApplianceScreenshot::query()->create([
            'onesi_box_id' => $box->id,
            'captured_at' => now()->subMinutes($i),
            'width' => 1920,
            'height' => 1080,
            'bytes' => 1024,
            'storage_path' => "fake/shot-{$i}.webp",
        ]);
    }

    $component = Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box]);

    expect($component->instance()->screenshots)->toHaveCount(6);

    $component->assertSee('Ultime schermate');
});

it('renders the collected log snapshots and selects the latest by default', function (): void {
    $box = OnesiBox::factory()->online()->create();

    OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create([
        'captured_at' => now()->subHour(),
        'lines' => ['vecchia riga di log'],
    ]);
    $latest = OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create([
        'captured_at' => now()->subMinutes(2),
        'lines' => ['riga di log recente'],
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box]);

    expect($component->instance()->selectedSnapshot?->id)->toBe($latest->id);

    $component->assertSee('riga di log recente');
});

it('can switch to an older log snapshot', function (): void {
    $box = OnesiBox::factory()->online()->create();

    $older = OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create([
        'captured_at' => now()->subHour(),
        'lines' => ['vecchia riga di log'],
    ]);
    OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create([
        'captured_at' => now()->subMinutes(2),
        'lines' => ['riga di log recente'],
    ]);

    Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->call('selectSnapshot', $older->id)
        ->assertSee('vecchia riga di log');
});

it('surfaces a failed collection instead of hiding it', function (): void {
    $box = OnesiBox::factory()->online()->create();

    OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->failed('grim non trovato')->create();

    Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->assertSee('grim non trovato');
});

it('queues a manual log request for an online box', function (): void {
    $box = OnesiBox::factory()->online()->create(['log_collection_lines' => 150]);

    Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->call('requestLogs');

    $command = Command::query()->where('onesi_box_id', $box->id)->sole();

    expect($command->type)->toBe(CommandType::GetLogs)
        ->and($command->status)->toBe(CommandStatus::Pending)
        ->and($command->payload['lines'])->toBe(150)
        ->and($command->payload['source'])->toBe(LogSnapshotSource::Manual->value);

    Queue::assertPushed(SendOnesiBoxCommand::class);
});

it('refuses to request logs from an offline box', function (): void {
    $box = OnesiBox::factory()->offline()->create();

    Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->call('requestLogs');

    expect(Command::query()->where('onesi_box_id', $box->id)->count())->toBe(0);
});

it('does not queue a second request while one is pending', function (): void {
    $box = OnesiBox::factory()->online()->create();

    Command::factory()
        ->for($box, 'onesiBox')
        ->pending()
        ->ofType(CommandType::GetLogs)
        ->create();

    Livewire::actingAs($this->admin)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->call('requestLogs');

    expect(Command::query()->where('onesi_box_id', $box->id)->count())->toBe(1);
});

it('denies log requests to users who cannot manage the box', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $caregiver = User::factory()->create();

    Livewire::actingAs($caregiver)
        ->test(BoxDiagnosticsPanel::class, ['record' => $box])
        ->call('requestLogs')
        ->assertForbidden();

    expect(Command::query()->where('onesi_box_id', $box->id)->count())->toBe(0);
});
