<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Browser\Support\Fixtures;
use Tests\Browser\Support\Shots;

/*
 * Login, box list and redirect, and the settings pages (profile, password, appearance, 2FA, Telegram).
 */

beforeEach(function (): void {
    Fixtures::fakeJwLibrary();
});

it('shows the Italian login page with "remember me" ticked and no registration', function (string $device): void {
    $page = Shots::open('/login', $device);

    $page->assertSee('Accedi')
        ->assertSee('Resta collegato su questo dispositivo')
        ->assertSee('Password dimenticata?')
        ->assertDontSee('Registrati')
        ->assertDontSee('Log in')
        ->assertDontSee('Remember me')
        ->assertChecked('remember')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '01-login', $device);
})->with(['desktop', 'mobile']);

it('does not expose a registration page', function (): void {
    $page = visit('/register');

    $page->assertSee('404')
        ->assertDontSee('Crea un account')
        ->assertDontSee('Registrati');
});

it('rejects wrong credentials with a message in Italian', function (string $device): void {
    User::factory()->role(App\Enums\Roles::Caregiver)->create(['email' => 'giulia.rossi@example.com']);

    $page = Shots::open('/login', $device);
    $page->fill('email', 'giulia.rossi@example.com')
        ->fill('password', 'password-sbagliata')
        ->click('button[data-test="login-button"]');

    Shots::see($page, 'Credenziali non valide.');
    $page->assertPathIs('/login')->assertNoJavaScriptErrors();
    Shots::take($page, '02-login-credenziali-errate', $device);
})->with(['desktop', 'mobile']);

it('logs a family member with a single box straight into that box', function (string $device): void {
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create(['email' => 'giulia.rossi@example.com']);
    [, $box] = Fixtures::boxFor(user: $user);
    auth()->logout();

    $page = Shots::open('/login', $device);
    $page->fill('email', 'giulia.rossi@example.com')
        ->fill('password', 'password')
        ->click('button[data-test="login-button"]');

    Shots::eventually(fn () => $page->assertPathIs('/dashboard/'.$box->id));
    $page->assertSee('Maria Rossi')->assertNoJavaScriptErrors();
    // With a single box there is no list to go back to
    $page->assertNotPresent('[aria-label="Torna ai miei box"]');
    Shots::assertNoHorizontalScroll($page);
})->with(['desktop', 'mobile']);

it('shows the box list when the family member follows several boxes and opens one', function (string $device): void {
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create(['email' => 'giulia.rossi@example.com']);
    [, $mariaBox] = Fixtures::boxFor(user: $user);
    [, $antonioBox] = Fixtures::boxFor('Antonio', 'Ferrari', 'Box Cucina', OnesiBoxPermission::ReadOnly, true, ['current_media_url' => Fixtures::VIDEO_URL, 'current_media_type' => 'video', 'current_media_title' => 'Programma mensile di novembre 2026'], $user);
    [, $giuseppinaBox] = Fixtures::boxFor('Giuseppina', 'Bianchi', 'Box Camera', online: false, user: $user);
    Fixtures::inZoomCall($mariaBox);
    Fixtures::screenshot($antonioBox);

    $page = Shots::open('/dashboard', $device);

    $page->assertPathIs('/dashboard')
        ->assertSee('Tocca un box per vedere cosa sta facendo')
        ->assertSee('Maria Rossi')
        ->assertSee('Antonio Ferrari')
        ->assertSee('Giuseppina Bianchi')
        ->assertSee('in riunione Zoom')
        ->assertSee('sta mostrando un video')
        ->assertSee('spento o non connesso')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '03-lista-box', $device);

    $page->click('[data-slot="box-status"]:has-text("sta mostrando un video")');
    Shots::eventually(fn () => $page->assertPathIs('/dashboard/'.$antonioBox->id));
    $page->assertSee('Antonio Ferrari')->assertPresent('[aria-label="Torna ai miei box"]');

    $page->click('[aria-label="Torna ai miei box"]');
    Shots::eventually(fn () => $page->assertPathIs('/dashboard'));
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('shows an empty list to a user with no boxes', function (string $device): void {
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create();
    $this->actingAs($user);

    $page = Shots::open('/dashboard', $device);

    $page->assertSee('Nessun box assegnato')->assertNoJavaScriptErrors();
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '04-lista-box-vuota', $device);
})->with(['desktop', 'mobile']);

it('shows the forgot-password page in Italian', function (string $device): void {
    $page = Shots::open('/forgot-password', $device);

    $page->assertSee('Password dimenticata')->assertNoJavaScriptErrors();
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '05-password-dimenticata', $device);
})->with(['desktop', 'mobile']);

it('shows and updates the profile', function (string $device): void {
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create(['name' => 'Giulia Rossi', 'email' => 'giulia.rossi@example.com']);
    $this->actingAs($user);

    $page = Shots::open('/settings/profile', $device);

    $page->assertSee('Profilo')->assertValue('input[wire\\:model="name"]', 'Giulia Rossi')->assertNoJavaScriptErrors();
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '06-impostazioni-profilo', $device);

    $page->fill('input[wire\\:model="name"]', 'Giulia Rossi Ferrari');
    $page->click('button[type="submit"]:has-text("Salva")');

    Shots::see($page, 'Salvato.');
    expect($user->refresh()->name)->toBe('Giulia Rossi Ferrari');
})->with(['desktop', 'mobile']);

it('changes the password and rejects a wrong current password', function (string $device): void {
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create();
    $this->actingAs($user);

    $page = Shots::open('/settings/password', $device);
    $page->assertSee('Password')->assertNoJavaScriptErrors();
    Shots::take($page, '07-impostazioni-password', $device);

    $page->fill('input[wire\\:model="current_password"]', 'non-e-questa')
        ->fill('input[wire\\:model="password"]', 'NuovaPassword!2026')
        ->fill('input[wire\\:model="password_confirmation"]', 'NuovaPassword!2026');
    $page->click('button[type="submit"]:has-text("Salva")');
    Shots::see($page, 'La password non è corretta.');
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
    Shots::take($page, '08-impostazioni-password-errore', $device);

    // The component clears the password fields after a failed attempt: fill them all again
    $page->fill('input[wire\\:model="current_password"]', 'password')
        ->fill('input[wire\\:model="password"]', 'NuovaPassword!2026')
        ->fill('input[wire\\:model="password_confirmation"]', 'NuovaPassword!2026');
    $page->click('button[type="submit"]:has-text("Salva")');
    Shots::see($page, 'Salvato.');
    expect(Hash::check('NuovaPassword!2026', $user->refresh()->password))->toBeTrue();
})->with(['desktop', 'mobile']);

it('switches the appearance to dark', function (string $device): void {
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create();
    $this->actingAs($user);

    $page = Shots::open('/settings/appearance', $device);
    $page->assertSee('Aspetto')->assertSee('Chiaro')->assertSee('Scuro')->assertSee('Sistema')->assertNoJavaScriptErrors();
    Shots::take($page, '09-impostazioni-aspetto', $device);

    $page->click('[value="dark"]:visible');
    Shots::eventually(fn () => $page->assertScript("document.documentElement.classList.contains('dark')"));
    Shots::take($page, '10-impostazioni-aspetto-scuro', $device);
})->with(['desktop', 'mobile']);

it('shows the two-factor settings page', function (string $device): void {
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create();
    $this->actingAs($user);

    $page = Shots::open('/settings/two-factor', $device);

    // The page may first ask to confirm the password
    $page->assertNoJavaScriptErrors();
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '11-impostazioni-due-passaggi', $device);
})->with(['desktop', 'mobile']);

it('hides Telegram linking unless it is enabled', function (string $device): void {
    config(['services.telegram-bot-api.linking_enabled' => false]);
    $this->actingAs(User::factory()->role(App\Enums\Roles::Caregiver)->create());

    $page = Shots::open('/settings/profile', $device);
    $page->assertSee('Profilo');

    if ($device === 'desktop') {
        $page->assertDontSee('Telegram');
    }

    $missing = Shots::open('/settings/telegram', $device);
    $missing->assertSee('404')->assertDontSee('Collega Telegram');
})->with(['desktop', 'mobile']);

it('links Telegram when it is enabled', function (string $device): void {
    config([
        'services.telegram-bot-api.linking_enabled' => true,
        'services.telegram-bot-api.bot_username' => 'OnesiforoBot',
        'services.telegram-bot-api.token' => '123456:TEST-TOKEN',
    ]);
    $this->actingAs(User::factory()->role(App\Enums\Roles::Caregiver)->create());

    $page = Shots::open('/settings/telegram', $device);

    $page->assertSee('Ricevi su Telegram gli avvisi')->assertSee('Collega Telegram')->assertNoJavaScriptErrors();
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '12-impostazioni-telegram', $device);

    $page->click('button:has-text("Collega Telegram")');
    Shots::see($page, 'Apri Telegram');
    $page->assertSee('In attesa che tu prema Avvia nel bot')
        ->assertAttributeContains('a:has-text("Apri Telegram")', 'href', 'https://t.me/OnesiforoBot?start=');
    Shots::take($page, '13-impostazioni-telegram-in-attesa', $device);
})->with(['desktop', 'mobile']);

it('shows Telegram as connected and lets the user disconnect', function (string $device): void {
    config([
        'services.telegram-bot-api.linking_enabled' => true,
        'services.telegram-bot-api.bot_username' => 'OnesiforoBot',
        'services.telegram-bot-api.token' => '123456:TEST-TOKEN',
    ]);
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create(['telegram_chat_id' => '987654321']);
    $this->actingAs($user);

    $page = Shots::open('/settings/telegram', $device);
    $page->assertSee('Telegram è collegato')->assertNoJavaScriptErrors();
    Shots::take($page, '14-impostazioni-telegram-collegato', $device);

    $page->script('window.confirm = () => true');
    $page->click('button:has-text("Scollega Telegram")');
    Shots::eventually(fn () => expect($user->refresh()->telegram_chat_id)->toBeNull());
})->with(['desktop', 'mobile']);

it('does not leak other families boxes into the list', function (): void {
    $user = User::factory()->role(App\Enums\Roles::Caregiver)->create();
    Fixtures::boxFor('Maria', 'Rossi', 'Box Salotto', user: $user);
    Fixtures::boxFor('Pietro', 'Altrui', 'Box Altrui', user: User::factory()->role(App\Enums\Roles::Caregiver)->create());
    test()->actingAs($user);

    $page = visit('/dashboard');
    // A single box redirects straight to it, and the other family's box is never reachable
    Shots::eventually(fn () => $page->assertSee('Maria Rossi'));
    $page->assertDontSee('Pietro Altrui');

    $foreign = OnesiBox::query()->where('name', 'Box Altrui')->sole();
    $forbidden = visit('/dashboard/'.$foreign->id);
    $forbidden->assertDontSee('Pietro Altrui');
});

it('tells a caregiver without a role that the account is waiting for activation', function (string $device): void {
    $this->actingAs(User::factory()->create());

    $page = Shots::open('/dashboard', $device);

    $page->assertSee('Account in attesa di attivazione')->assertNoJavaScriptErrors();
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '04b-lista-box-account-in-attesa', $device);
})->with(['desktop', 'mobile']);
