<?php

declare(strict_types=1);

use App\Filament\Resources\Recipients\Pages\CreateRecipient;
use App\Filament\Resources\Recipients\Pages\EditRecipient;
use App\Filament\Resources\Recipients\Pages\ListRecipients;
use App\Filament\Resources\Recipients\RecipientResource;
use App\Models\Congregation;
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

describe('RecipientResource: List', function (): void {
    it('can render the list page', function (): void {
        $this->get(RecipientResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('can list recipients', function (): void {
        $recipients = Recipient::factory()->count(3)->create();

        livewire(ListRecipients::class)
            ->assertCanSeeTableRecords($recipients);
    });

    it('can search by name', function (): void {
        $mario = Recipient::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);
        $luigi = Recipient::factory()->create(['first_name' => 'Luigi', 'last_name' => 'Bianchi']);

        livewire(ListRecipients::class)
            ->searchTable('Mario')
            ->assertCanSeeTableRecords([$mario])
            ->assertCanNotSeeTableRecords([$luigi]);
    });

    it('displays associated OnesiBox', function (): void {
        $recipient = Recipient::factory()->create();
        OnesiBox::factory()->create([
            'name' => 'Test OnesiBox',
            'recipient_id' => $recipient->id,
        ]);

        livewire(ListRecipients::class)
            ->assertSee('Test OnesiBox');
    });
});

describe('RecipientResource: Create', function (): void {
    it('can render the create page', function (): void {
        $this->get(RecipientResource::getUrl('create'))
            ->assertSuccessful();
    });

    it('can create a recipient', function (): void {
        livewire(CreateRecipient::class)
            ->fillForm([
                'first_name' => 'Giuseppe',
                'last_name' => 'Verdi',
                'phone' => '+39 02 1234567',
                'city' => 'Roma',
                'postal_code' => '00100',
                'province' => 'RM',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Recipient::class, [
            'first_name' => 'Giuseppe',
            'last_name' => 'Verdi',
            'city' => 'Roma',
        ]);
    });

    it('can create a recipient with emergency contacts', function (): void {
        livewire(CreateRecipient::class)
            ->fillForm([
                'first_name' => 'Maria',
                'last_name' => 'Bianchi',
                'emergency_contacts' => [
                    ['name' => 'Figlio', 'phone' => '+39 333 1234567', 'relationship' => 'Figlio'],
                    ['name' => 'Nipote', 'phone' => '+39 333 7654321', 'relationship' => 'Nipote'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $recipient = Recipient::query()->where('first_name', 'Maria')->first();
        expect($recipient->emergency_contacts)->toHaveCount(2);
    });

    it('validates required fields', function (): void {
        livewire(CreateRecipient::class)
            ->fillForm([
                'first_name' => '',
                'last_name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'first_name' => 'required',
                'last_name' => 'required',
            ]);
    });

    it('validates phone format', function (): void {
        livewire(CreateRecipient::class)
            ->fillForm([
                'first_name' => 'Test',
                'last_name' => 'User',
                'phone' => 'not-a-valid-phone',
            ])
            ->call('create')
            ->assertHasFormErrors(['phone' => 'regex']);
    });
});

describe('RecipientResource: Edit', function (): void {
    it('can render the edit page', function (): void {
        $recipient = Recipient::factory()->create();

        $this->get(RecipientResource::getUrl('edit', ['record' => $recipient]))
            ->assertSuccessful();
    });

    it('can update a recipient', function (): void {
        $recipient = Recipient::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);

        livewire(EditRecipient::class, ['record' => $recipient->id])
            ->fillForm([
                'first_name' => 'Updated Mario',
                'last_name' => 'Updated Rossi',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Recipient::class, [
            'id' => $recipient->id,
            'first_name' => 'Updated Mario',
            'last_name' => 'Updated Rossi',
        ]);
    });

    it('can delete a recipient', function (): void {
        $recipient = Recipient::factory()->create();

        livewire(EditRecipient::class, ['record' => $recipient->id])
            ->callAction('delete');

        $this->assertSoftDeleted(Recipient::class, ['id' => $recipient->id]);
    });
});

describe('RecipientResource: Congregation', function (): void {
    it('can assign a congregation when creating a recipient', function (): void {
        $congregation = Congregation::factory()->create();

        livewire(CreateRecipient::class)
            ->fillForm([
                'first_name' => 'Anna',
                'last_name' => 'Conti',
                'congregation_id' => $congregation->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Recipient::class, [
            'first_name' => 'Anna',
            'congregation_id' => $congregation->id,
        ]);
    });

    it('can change the congregation of an existing recipient', function (): void {
        $oldCongregation = Congregation::factory()->create();
        $newCongregation = Congregation::factory()->create();
        $recipient = Recipient::factory()->create(['congregation_id' => $oldCongregation->id]);

        livewire(EditRecipient::class, ['record' => $recipient->id])
            ->fillForm(['congregation_id' => $newCongregation->id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($recipient->fresh()->congregation_id)->toBe($newCongregation->id);
    });

    it('can unassign a congregation from a recipient', function (): void {
        $congregation = Congregation::factory()->create();
        $recipient = Recipient::factory()->create(['congregation_id' => $congregation->id]);

        livewire(EditRecipient::class, ['record' => $recipient->id])
            ->fillForm(['congregation_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($recipient->fresh()->congregation_id)->toBeNull();
    });

    it('shows the congregation column in the list', function (): void {
        $congregation = Congregation::factory()->create(['name' => 'Congregazione Milano']);
        Recipient::factory()->create(['congregation_id' => $congregation->id]);

        livewire(ListRecipients::class)
            ->assertSee('Congregazione Milano');
    });
});
