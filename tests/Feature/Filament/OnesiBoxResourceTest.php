<?php

declare(strict_types=1);

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Filament\Resources\OnesiBoxes\Pages\CreateOnesiBox;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

// ============================================================================
// User Story 1: Create OnesiBox with Recipient (T006, T007, T008)
// ============================================================================

describe('US1: Create OnesiBox with Recipient', function (): void {
    it('can render the OnesiBox create page', function (): void {
        $this->get(OnesiBoxResource::getUrl('create'))
            ->assertSuccessful();
    });

    it('can create OnesiBox with an existing recipient', function (): void {
        $recipient = Recipient::factory()->create();

        livewire(CreateOnesiBox::class)
            ->fillForm([
                'name' => 'Test OnesiBox',
                'serial_number' => 'OB-TEST-001',
                'firmware_version' => '1.0.0',
                'is_active' => true,
                'recipient_id' => $recipient->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OnesiBox::class, [
            'name' => 'Test OnesiBox',
            'serial_number' => 'OB-TEST-001',
            'recipient_id' => $recipient->id,
        ]);
    });

    it('can create OnesiBox with a new inline recipient via createOption action', function (): void {
        livewire(CreateOnesiBox::class)
            ->callFormComponentAction('recipient_id', 'createOption', data: [
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
                'phone' => '+39 02 1234567',
                'city' => 'Milano',
                'postal_code' => '20100',
                'province' => 'MI',
            ])
            ->assertHasNoFormComponentActionErrors();

        $this->assertDatabaseHas(Recipient::class, [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);
    });

    it('validates recipient fields when creating inline', function (): void {
        livewire(CreateOnesiBox::class)
            ->callFormComponentAction('recipient_id', 'createOption', data: [
                'first_name' => '',
                'last_name' => '',
            ])
            ->assertHasFormComponentActionErrors(['first_name' => 'required', 'last_name' => 'required']);
    });
});

// ============================================================================
// User Story 5: Form Validation and UX (T033, T034, T035)
// ============================================================================

describe('US5: Form Validation and UX', function (): void {
    it('validates required fields', function (): void {
        livewire(CreateOnesiBox::class)
            ->fillForm([
                'name' => '',
                'serial_number' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'serial_number' => 'required',
            ]);
    });

    it('validates unique serial number', function (): void {
        OnesiBox::factory()->create(['serial_number' => 'OB-DUPLICATE-001']);

        livewire(CreateOnesiBox::class)
            ->fillForm([
                'name' => 'Another OnesiBox',
                'serial_number' => 'OB-DUPLICATE-001',
            ])
            ->call('create')
            ->assertHasFormErrors(['serial_number' => 'unique']);
    });

    it('validates phone format for inline recipient', function (): void {
        livewire(CreateOnesiBox::class)
            ->callFormComponentAction('recipient_id', 'createOption', data: [
                'first_name' => 'Test',
                'last_name' => 'User',
                'phone' => 'not-a-valid-phone',
            ])
            ->assertHasFormComponentActionErrors(['phone' => 'regex']);
    });

    it('accepts valid Italian phone numbers for inline recipient', function (): void {
        livewire(CreateOnesiBox::class)
            ->callFormComponentAction('recipient_id', 'createOption', data: [
                'first_name' => 'Test',
                'last_name' => 'User',
                'phone' => '+39 02 12345678',
            ])
            ->assertHasNoFormComponentActionErrors();

        $this->assertDatabaseHas(Recipient::class, [
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
    });
});
