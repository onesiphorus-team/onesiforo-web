<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Models\PlaybackSession;
use Tests\Browser\Support\Fixtures;
use Tests\Browser\Support\Shots;

/*
 * The redesigned box page in each of its states, at desktop (1440x900) and mobile (390x844).
 */

beforeEach(function (): void {
    Fixtures::fakeJwLibrary();
});

it('shows an offline box and disables the commands', function (string $device): void {
    [, $box] = Fixtures::boxFor(online: false);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('Maria Rossi')
        ->assertSee('Offline')
        ->assertSee('Il box non è raggiungibile')
        ->assertAttribute('[data-hero-state]', 'data-hero-state', 'offline')
        ->assertNoJavaScriptErrors();

    // The command bar is rendered but cannot be pressed while the box is unreachable
    $page->assertScript("[...document.querySelectorAll('[data-slot=\"stop\"]')].every(b => getComputedStyle(b.parentElement).pointerEvents === 'none')");

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '10-box-offline', $device);
})->with(['desktop', 'mobile']);

it('shows an idle online box with shortcuts, next meeting and contacts', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('Maria Rossi')
        ->assertSee('Box Salotto')
        ->assertSee('Online')
        ->assertSee('Acceso e in attesa')
        ->assertSee('Nessun contenuto in riproduzione')
        ->assertSee('Prossima adunanza')
        ->assertSee('Chiama Maria')
        ->assertSee('Paolo Rossi')
        ->assertAttribute('[data-hero-state]', 'data-hero-state', 'idle')
        ->assertPresent('[data-slot="idle-shortcuts"]')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '11-box-online-in-attesa', $device);
})->with(['desktop', 'mobile']);

it('shows a video playing with title, progress and time left', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::playVideo($box);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('In riproduzione')
        ->assertSee('Programma mensile di novembre 2026')
        ->assertSee('jw.org')
        ->assertSee('mancano')
        ->assertSee('Pausa')
        ->assertAttribute('[data-hero-state]', 'data-hero-state', 'media')
        ->assertPresent('[role="progressbar"]')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '12-box-video-in-riproduzione', $device);
})->with(['desktop', 'mobile']);

it('shows a paused video with the resume button', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::playVideo($box, paused: true);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('In pausa')
        ->assertSee('Riprendi')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '13-box-video-in-pausa', $device);
})->with(['desktop', 'mobile']);

it('shows audio playing', function (string $device): void {
    [, $box] = Fixtures::boxFor(firstName: 'Giuseppina', lastName: 'Bianchi', boxName: 'Box Camera');
    Fixtures::playAudio($box);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('Giuseppina Bianchi')
        ->assertSee('In Geova io confiderò')
        ->assertSee('Audio')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '14-box-audio-in-riproduzione', $device);
})->with(['desktop', 'mobile']);

it('shows a Zoom call in progress and ends it', function (string $device): void {
    [, $box] = Fixtures::boxFor(firstName: 'Antonio', lastName: 'Ferrari', boxName: 'Box Cucina');
    Fixtures::inZoomCall($box);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('Riunione in corso')
        ->assertSee('Riunione Zoom in corso')
        ->assertDontSee('81234567890')
        ->assertSee('Termina chiamata')
        ->assertAttribute('[data-hero-state]', 'data-hero-state', 'call')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '15-box-in-chiamata-zoom', $device);

    $page->click('[data-hero-state] button:has-text("Termina chiamata")');

    Shots::eventually(fn () => expect($box->commands()->where('type', CommandType::LeaveZoom)->count())->toBe(1));
})->with(['desktop', 'mobile']);

it('shows a running playlist session with what comes next', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::startSession($box);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('Video casuali · 1 di 5')
        ->assertSee('Il valore della preghiera')
        ->assertSee('Una famiglia unita nella fede')
        ->assertSee('e altri 1 contenuti')
        ->assertSee('Ferma')
        ->assertPresent('[data-slot="session"]')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '16-box-sessione-playlist', $device);

    $page->click('[data-hero-state] button:has-text("Ferma")');

    Shots::eventually(fn () => expect(PlaybackSession::query()->active()->count())->toBe(0));
    expect($box->commands()->where('type', CommandType::StopMedia)->count())->toBeGreaterThanOrEqual(1);
})->with(['desktop', 'mobile']);

it('shows the error callout when the box reports an error', function (string $device): void {
    [, $box] = Fixtures::boxFor(attributes: ['status' => OnesiBoxStatus::Error]);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('Il box ha segnalato un errore')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '17-box-con-errore', $device);
})->with(['desktop', 'mobile']);

it('hides every control from a read-only caregiver', function (string $device): void {
    [, $box] = Fixtures::boxFor(permission: OnesiBoxPermission::ReadOnly);
    Fixtures::playVideo($box);
    Fixtures::customCommands($box);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('Programma mensile di novembre 2026')
        ->assertSee('Puoi solo guardare questo box.')
        ->assertDontSee('Pausa')
        ->assertDontSee('Scegli cosa mostrare')
        ->assertDontSee('Accendi la TV')
        ->assertNotPresent('[data-slot="stop"]')
        ->assertNotPresent('[data-slot="new"]')
        ->assertNotPresent('[data-slot="volume"]')
        ->assertNotPresent('[data-slot="call"]')
        ->assertNoJavaScriptErrors();

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '18-box-solo-lettura', $device);
})->with(['desktop', 'mobile']);

it('shows the latest screen picture, custom commands and a command on its way', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::screenshot($box);
    Fixtures::customCommands($box);
    Fixtures::pendingCommand($box);

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->assertSee('Un comando sta arrivando al box')
        ->assertSee('Comandi personalizzati')
        ->assertSee('Accendi la TV')
        ->assertSee('Spegni la TV')
        ->assertPresent('[data-slot="screen-preview"]')
        ->assertNoJavaScriptErrors();

    Shots::eventually(fn () => $page->assertScript("document.querySelector('[data-slot=\"screen-preview\"]').naturalWidth > 0"));
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '19-box-schermo-comandi-personalizzati', $device);
})->with(['desktop', 'mobile']);
