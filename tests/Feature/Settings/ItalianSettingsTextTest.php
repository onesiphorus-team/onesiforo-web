<?php

declare(strict_types=1);

use App\Livewire\Settings\TwoFactor;
use App\Models\User;

it('shows the settings heading in Italian', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Il tuo profilo e le impostazioni del tuo account')
        ->assertDontSee('Manage your profile and account settings');
});

it('shows the two-factor setup dialog in Italian at every step', function (): void {
    $page = new TwoFactor;
    $page->twoFactorEnabled = false;

    expect($page->getModalConfigProperty())->toBe([
        'title' => 'Attiva la verifica in due passaggi',
        'description' => "Per completare l'attivazione, inquadra il codice QR con l'app di autenticazione sul tuo telefono, oppure scrivi nell'app il codice qui sotto.",
        'buttonText' => 'Continua',
    ]);

    $page->showVerificationStep = true;

    expect($page->getModalConfigProperty())->toBe([
        'title' => 'Scrivi il codice di verifica',
        'description' => "Scrivi il codice di 6 cifre che vedi nell'app di autenticazione.",
        'buttonText' => 'Continua',
    ]);

    $page->showVerificationStep = false;
    $page->twoFactorEnabled = true;

    expect($page->getModalConfigProperty())->toBe([
        'title' => 'Verifica in due passaggi attivata',
        'description' => "La verifica in due passaggi ora è attiva. Inquadra il codice QR con l'app di autenticazione, oppure scrivi nell'app il codice qui sotto.",
        'buttonText' => 'Chiudi',
    ]);
});
