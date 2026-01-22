<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\OnesiBoxDetail;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

it('displays the OnesiBox detail page', function (): void {
    $user = User::factory()->create();
    $recipient = Recipient::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->forRecipient($recipient)->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSee($onesiBox->name)
        ->assertSee('Online');
});

it('displays recipient contact information', function (): void {
    $user = User::factory()->create();
    $recipient = Recipient::factory()->create([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'phone' => '+39 123 456 7890',
    ]);
    $onesiBox = OnesiBox::factory()->online()->forRecipient($recipient)->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSee('Mario Rossi')
        ->assertSee('+39 123 456 7890');
});

it('shows warning when OnesiBox has no recipient', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['recipient_id' => null]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSee('Nessun destinatario associato');
});

it('displays emergency contacts when available', function (): void {
    $user = User::factory()->create();
    $recipient = Recipient::factory()->create([
        'emergency_contacts' => [
            ['name' => 'Dr. Bianchi', 'phone' => '800 123 456', 'relationship' => 'Doctor'],
        ],
    ]);
    $onesiBox = OnesiBox::factory()->online()->forRecipient($recipient)->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSee('Dr. Bianchi')
        ->assertSee('800 123 456');
});

it('shows offline status when OnesiBox is not online', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->offline()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->assertSee('Offline');
});

it('navigates back to the list when goBack is called', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $onesiBox])
        ->call('goBack')
        ->assertRedirect(route('dashboard'));
});
