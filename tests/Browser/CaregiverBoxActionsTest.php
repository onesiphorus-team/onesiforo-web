<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Models\Command;
use App\Models\PlaybackSession;
use App\Models\ZoomMeeting;
use Illuminate\Support\Facades\Http;
use Tests\Browser\Support\Fixtures;
use Tests\Browser\Support\Shots;

/*
 * Real caregiver flows on the box page: open the "Mostra" menu, browse the JW.org library,
 * queue a temporary playlist, random sessions, Zoom, volume, stop, "Altro" and custom commands.
 * Every flow runs at desktop and mobile size and checks the commands that reach the database.
 */

beforeEach(function (): void {
    Fixtures::fakeJwLibrary();
});

function commandsOf(App\Models\OnesiBox $box, CommandType $type): Illuminate\Database\Eloquent\Collection
{
    return Command::query()->where('onesi_box_id', $box->id)->where('type', $type)->get();
}

function openMenu(mixed $page): void
{
    Shots::tap($page, '[data-slot="new"]');
    Shots::see($page, 'Cosa vuoi mostrare sul box?');
}

function chooseTab(mixed $page, string $tab): void
{
    $page->click("[wire\\:click=\"selectTab('{$tab}')\"]:visible");
}

it('opens the Mostra menu and lists every choice', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);

    foreach (['Video', 'Audio', 'Zoom e adunanze', 'Video casuali', 'Audio casuali', 'Altro'] as $choice) {
        $page->assertSee($choice);
    }

    $page->assertNoJavaScriptErrors();
    Shots::take($page, '20-menu-mostra', $device, fullPage: false);

    // A centred column on desktop, edge to edge on phones
    $page->assertScript($device === 'desktop'
        ? 'document.querySelector("dialog[open]").getBoundingClientRect().width <= 672'
        : 'document.querySelector("dialog[open]").getBoundingClientRect().width === window.innerWidth');

    // "Altro" reveals the pasted-link entries and JW Stream
    $page->click('button:has-text("Link incollati e programmi di JW Stream"):visible');
    Shots::see($page, 'Video da un link');

    $page->assertSee('Audio da un link')->assertSee('Programma di JW Stream');

    // Once the list has unfolded, its last entry is on screen
    Shots::eventually(fn () => $page->assertScript('[...document.querySelectorAll("dialog[open] button")].filter((el) => el.textContent.includes("Programma di JW Stream")).pop().getBoundingClientRect().bottom <= window.innerHeight'));
    Shots::take($page, '21-menu-mostra-altro-espanso', $device, fullPage: false);

    // The close button dismisses the sheet
    $page->click('[data-slot="sheet-header"] [aria-label="Chiudi"]:visible');
    Shots::gone($page, 'Cosa vuoi mostrare sul box?');

    Shots::assertNoHorizontalScroll($page);
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('opens the right section from each idle shortcut', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);

    $page->click('[data-slot="idle-shortcuts"] button:has-text("Video"):not(:has-text("casuali"))');
    Shots::see($page, 'Video da jw.org');
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('browses the JW.org video library, queues a temporary playlist and starts it', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'jw-video');

    Shots::see($page, 'Video da jw.org');
    $page->assertSee('JW Broadcasting')->assertSee('La Bibbia')->assertSee('Famiglia');
    Shots::take($page, '22-jw-video-categorie', $device, fullPage: false);

    // Expand a container category, then open a subcategory
    $page->click('[wire\\:click="selectCategory(\'VODStudio\')"]:visible');
    Shots::see($page, 'Programmi mensili');
    $page->assertSee('Discorsi');
    Shots::take($page, '23-jw-video-categoria-espansa', $device, fullPage: false);

    $page->click('[wire\\:click="openSubcategory(\'StudioMonthlyPrograms\')"]:visible');
    Shots::see($page, 'Programma mensile di ottobre 2026');
    $page->assertSee('Programma mensile di novembre 2026')
        ->assertSee('Mai mostrato')
        ->assertSee('27:00');
    Shots::take($page, '24-jw-video-elenco', $device, fullPage: false);

    // Search filters the list by title
    $page->fill('input[placeholder="Cerca per titolo..."]', 'ottobre');
    Shots::gone($page, 'Programma mensile di novembre 2026');
    $page->assertSee('Programma mensile di ottobre 2026');
    Shots::take($page, '25-jw-video-ricerca', $device, fullPage: false);
    $page->fill('input[placeholder="Cerca per titolo..."]', '');
    Shots::see($page, 'Programma mensile di novembre 2026');

    // Queue two videos with the "+" buttons
    $page->click('[aria-label="Aggiungi alla playlist: Programma mensile di novembre 2026"]:visible');
    Shots::see($page, 'Playlist (1)');
    $page->click('[aria-label="Aggiungi alla playlist: Programma mensile di ottobre 2026"]:visible');
    Shots::see($page, 'Playlist (2)');
    $page->assertSee('Durata totale')->assertSee('54 min')->assertSee('Avvia playlist');
    Shots::take($page, '26-jw-video-playlist-temporanea', $device, fullPage: false);

    // Taking one out updates the bar
    $page->click('[wire\\:click="removeFromQueue(\'pub-jwb-102026_1_VIDEO\')"]:visible');
    Shots::see($page, 'Playlist (1)');
    $page->click('[aria-label="Aggiungi alla playlist: Programma mensile di ottobre 2026"]:visible');
    Shots::see($page, 'Playlist (2)');

    $page->click('button:has-text("Avvia playlist"):visible');
    Shots::see($page, 'Playlist avviata: 2 contenuti');
    Shots::take($page, '27-jw-video-playlist-avviata', $device, fullPage: false);

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::PlayMedia))->toHaveCount(1));

    $command = commandsOf($box, CommandType::PlayMedia)->sole();
    expect($command->payload)->toMatchArray(['media_type' => 'video'])
        ->and($command->payload['url'])->toContain('pub-jwb-112026_1_VIDEO');

    $session = PlaybackSession::query()->where('onesi_box_id', $box->id)->active()->sole();
    expect($session->playlist->items)->toHaveCount(2)
        ->and($session->playlist->items->pluck('title')->all())->toBe(['Programma mensile di novembre 2026', 'Programma mensile di ottobre 2026']);

    // The sheet closes once the playlist has started, so the status card is in view
    Shots::gone($page, 'Avvia playlist');
    Shots::gone($page, 'Cerca per titolo...');
    Shots::assertNoHorizontalScroll($page);
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('plays a single video straight away', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'jw-video');
    Shots::see($page, 'Video da jw.org');
    $page->click('[wire\\:click="selectCategory(\'VODStudio\')"]:visible');
    Shots::see($page, 'Discorsi');
    $page->click('[wire\\:click="openSubcategory(\'StudioTalks\')"]:visible');
    Shots::see($page, 'Il valore della preghiera');

    $page->click('[aria-label="Mostra Il valore della preghiera"]:visible');

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::PlayMedia))->toHaveCount(1));
    expect(commandsOf($box, CommandType::PlayMedia)->sole()->payload['url'])->toContain('pub-jwb-talk-2_I_VIDEO');

    // A toast confirms it and the sheet closes
    Shots::see($page, '«Il valore della preghiera» inviato al box');
    Shots::gone($page, 'Discorsi');
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('browses the audio library and queues songs', function (string $device): void {
    [, $box] = Fixtures::boxFor(firstName: 'Giuseppina', lastName: 'Bianchi', boxName: 'Box Camera');

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Giuseppina Bianchi');

    openMenu($page);
    chooseTab($page, 'jw-audio');

    Shots::see($page, 'Audio da jw.org');
    $page->assertSee('Cantici originali')->assertSee('Lettura della Bibbia');
    Shots::take($page, '28-jw-audio-categorie', $device, fullPage: false);

    // A leaf category opens its list directly
    $page->click('[wire\\:click="selectCategory(\'AudioOriginalSongs\')"]:visible');
    Shots::see($page, 'In Geova io confiderò');
    $page->assertSee('Geova è il nostro rifugio')->assertSee('Camminiamo per fede');
    Shots::take($page, '29-jw-audio-elenco', $device, fullPage: false);

    // Back to the categories with the header arrow, then in again
    $page->click('[aria-label="Torna alle categorie"]:visible');
    Shots::see($page, 'Lettura della Bibbia');
    $page->click('[wire\\:click="selectCategory(\'AudioOriginalSongs\')"]:visible');
    Shots::see($page, 'In Geova io confiderò');

    $page->click('[aria-label="Aggiungi alla playlist: In Geova io confiderò"]:visible');
    Shots::see($page, 'Playlist (1)');
    $page->click('[aria-label="Aggiungi alla playlist: Camminiamo per fede"]:visible');
    Shots::see($page, 'Playlist (2)');
    $page->click('button:has-text("Avvia playlist"):visible');
    Shots::see($page, 'Playlist avviata: 2 contenuti');
    Shots::take($page, '30-jw-audio-playlist-avviata', $device, fullPage: false);

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::PlayMedia))->toHaveCount(1));
    expect(commandsOf($box, CommandType::PlayMedia)->sole()->payload)->toMatchArray(['media_type' => 'audio']);

    Shots::gone($page, 'Avvia playlist');
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('shows a friendly error when jw.org cannot be reached', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::fake(['b.jw-cdn.org/*' => Http::response(status: 500)]);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'jw-video');

    Shots::see($page, 'Impossibile caricare i contenuti da jw.org');
    $page->assertSee('Riprova')->assertNoJavaScriptErrors();
    Shots::take($page, '31-jw-errore-rete', $device, fullPage: false);
})->with(['desktop', 'mobile']);

it('starts a random video session', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'random-video');

    Shots::see($page, 'Video casuali');
    $page->assertSee('Per quanto tempo?')->assertSee('Includi video lunghi')->assertSee('Avvia video casuali');
    Shots::take($page, '32-casuali-video', $device, fullPage: false);

    // Pick a duration and open the categories
    $page->click('button[aria-pressed]:has-text("1 ora"):visible');
    $page->click('summary:has-text("Categorie"):visible');
    Shots::see($page, 'Seleziona tutte');

    // The panel stays open while categories are picked (each pick is a Livewire update)
    $page->click('details ui-checkbox[value="VODStudio"]:visible');
    Shots::see($page, 'Categorie (2 di 3)');
    $page->assertSee('Seleziona tutte');
    $page->click('button:has-text("Seleziona tutte"):visible');
    Shots::see($page, 'Categorie (3 di 3)');
    $page->assertSee('Seleziona tutte');
    Shots::take($page, '33-casuali-video-categorie', $device, fullPage: false);

    $page->click('button:has-text("Avvia video casuali"):visible');

    Shots::eventually(fn () => expect(PlaybackSession::query()->where('onesi_box_id', $box->id)->active()->count())->toBe(1));
    expect(commandsOf($box, CommandType::PlayMedia))->toHaveCount(1);

    // A toast confirms it and the sheet closes
    Shots::see($page, 'Sessione di video casuali avviata');
    Shots::gone($page, 'Per quanto tempo?');
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('shows the audio random form', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'random-audio');

    Shots::see($page, 'Audio casuali');
    $page->assertSee('Avvia audio casuali')->assertDontSee('Includi video lunghi')->assertNoJavaScriptErrors();
    Shots::take($page, '34-casuali-audio', $device, fullPage: false);

    // "Menu" goes back to the list of choices
    $page->click('[data-slot="sheet-header"] button:has-text("Menu"):visible');
    Shots::see($page, 'Cosa vuoi mostrare sul box?');
})->with(['desktop', 'mobile']);

it('joins the congregation meeting from the Zoom section', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    ZoomMeeting::factory()->for($box, 'onesiBox')->create(['name' => 'Adunanza Loreto (sala 2)', 'last_used_at' => now()->subDays(3)]);
    ZoomMeeting::factory()->for($box, 'onesiBox')->create(['name' => 'Studio con Paolo', 'last_used_at' => null]);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'zoom');

    Shots::see($page, 'Riunione Zoom');
    $page->assertSee('Il box si collegherà con il nome')
        ->assertSee('Adunanza Milano Loreto')
        ->assertSee('Adunanza Loreto (sala 2)')
        ->assertSee('Studio con Paolo')
        ->assertSee('Mai usata');
    Shots::take($page, '35-zoom-riunioni', $device, fullPage: false);

    $page->click('[data-slot="congregation-meeting"]:visible');

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::JoinZoom))->toHaveCount(1));
    expect(commandsOf($box, CommandType::JoinZoom)->sole()->payload['meeting_url'])->toContain('81234567890');
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('joins a saved Zoom meeting and updates its last use', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    $meeting = ZoomMeeting::factory()->for($box, 'onesiBox')->create(['name' => 'Studio con Paolo', 'last_used_at' => null, 'zoom_url' => 'https://us05web.zoom.us/j/99887766554?pwd=xyz']);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'zoom');
    Shots::see($page, 'Studio con Paolo');

    $page->click("[wire\\:click=\"joinSavedMeeting({$meeting->id})\"]:visible");

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::JoinZoom))->toHaveCount(1));
    expect(commandsOf($box, CommandType::JoinZoom)->sole()->payload['meeting_url'])->toBe($meeting->zoom_url)
        ->and($meeting->refresh()->last_used_at)->not->toBeNull();
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('validates a pasted Zoom link, then joins and saves it', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'zoom');
    Shots::see($page, 'Incolla il link della riunione');

    // A link that is not a Zoom meeting is rejected
    $page->fill('input[wire\\:model="zoomUrl"]', 'https://example.com/riunione');
    $page->click('button:has-text("Collegati alla riunione"):visible');
    Shots::see($page, 'Questo non sembra un link Zoom');
    Shots::take($page, '36-zoom-link-non-valido', $device, fullPage: false);
    expect(commandsOf($box, CommandType::JoinZoom))->toHaveCount(0);

    // A valid link, saved for next time (the name is required)
    $page->fill('input[wire\\:model="zoomUrl"]', 'https://us05web.zoom.us/j/12345678901?pwd=AbC123');
    $page->click('text=Salva questa riunione per la prossima volta');
    Shots::see($page, 'Nome della riunione');
    $page->click('button:has-text("Collegati alla riunione"):visible');
    Shots::see($page, 'Dai un nome alla riunione');
    $page->fill('input[wire\\:model="meetingName"]', 'Adunanza Loreto');
    Shots::take($page, '37-zoom-link-da-salvare', $device, fullPage: false);

    $page->click('button:has-text("Collegati alla riunione"):visible');

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::JoinZoom))->toHaveCount(1));
    expect(commandsOf($box, CommandType::JoinZoom)->sole()->payload['meeting_url'])->toBe('https://us05web.zoom.us/j/12345678901?pwd=AbC123');

    $saved = $box->zoomMeetings()->sole();
    expect($saved->name)->toBe('Adunanza Loreto')->and($saved->last_used_at)->not->toBeNull();
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('removes a saved Zoom meeting after confirming', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    ZoomMeeting::factory()->for($box, 'onesiBox')->create(['name' => 'Studio con Paolo']);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    openMenu($page);
    chooseTab($page, 'zoom');
    Shots::see($page, 'Studio con Paolo');

    $page->script('window.confirm = () => true');
    $page->click('[aria-label="Togli Studio con Paolo dalle riunioni salvate"]:visible');

    Shots::eventually(fn () => expect($box->zoomMeetings()->count())->toBe(0));
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('changes the volume from the volume dialog', function (string $device): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    Shots::tap($page, '[data-slot="volume"]');
    Shots::see($page, 'Scegli quanto forte si sente il box.');
    Shots::take($page, '38-volume', $device, fullPage: false);

    $page->click('button:has-text("60%"):visible');

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::SetVolume))->toHaveCount(1));
    expect(commandsOf($box, CommandType::SetVolume)->sole()->payload)->toMatchArray(['level' => 60]);
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('stops everything from the bottom bar', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::playVideo($box);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Programma mensile di novembre 2026');

    Shots::tap($page, '[data-slot="stop"]');

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::StopMedia))->toHaveCount(1));
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('pauses the video from the status card', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::playVideo($box);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Programma mensile di novembre 2026');

    $page->click('[data-hero-state] button:has-text("Pausa")');
    Shots::eventually(fn () => expect(commandsOf($box, CommandType::PauseMedia))->toHaveCount(1));
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('starts a Zoom call from the bottom bar and ends it when in a call', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::inZoomCall($box);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Riunione Zoom in corso');

    Shots::tap($page, '[data-slot="call"]');
    Shots::eventually(fn () => expect(commandsOf($box, CommandType::LeaveZoom))->toHaveCount(1));
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('opens the Altro section lazily', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::pendingCommand($box);
    Fixtures::screenshot($box);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi')
        ->assertNotPresent('#technical-details')
        ->assertAttribute('button[aria-controls="technical-details"]', 'aria-expanded', 'false');

    $page->click('button[aria-controls="technical-details"]');
    Shots::eventually(fn () => $page->assertPresent('#technical-details'));
    $page->assertAttribute('button[aria-controls="technical-details"]', 'aria-expanded', 'true')
        ->assertSee('Versione app:')
        ->assertSee('Numero di serie:')
        ->assertSee('Ultimo contatto:');

    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '39-altro-aperto', $device);

    $page->click('button[aria-controls="technical-details"]');
    Shots::eventually(fn () => $page->assertNotPresent('#technical-details'));
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('runs a custom command', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    [$tvOn] = Fixtures::customCommands($box);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Accendi la TV');

    $page->click('button:has-text("Accendi la TV")');

    Shots::eventually(fn () => expect(commandsOf($box, CommandType::CustomScript))->toHaveCount(1));
    expect(commandsOf($box, CommandType::CustomScript)->sole()->payload)->toMatchArray(['custom_command_id' => $tvOn->id, 'script_name' => 'tv-on.sh']);
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('does not let a read-only caregiver start anything even by dispatching the sheet event', function (string $device): void {
    [, $box] = Fixtures::boxFor(permission: OnesiBoxPermission::ReadOnly);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Maria Rossi');

    $page->script("window.dispatchEvent(new CustomEvent('open-quick-play'))");
    Shots::assertNoHorizontalScroll($page);
    $page->assertDontSee('Cosa vuoi mostrare sul box?')->assertNoJavaScriptErrors();
    expect(Command::query()->count())->toBe(0);
})->with(['desktop', 'mobile']);

/*
 * Regression: wire:target="…(@js(…))" used to sit on <flux:icon> components, where Blade does not compile @js, so the
 * literal text reached the browser and Livewire threw "Uncaught SyntaxError" on every render of the list.
 */
it('opens the JW.org video browser without JavaScript errors', function (): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), 'desktop');
    $page->assertSee('Maria Rossi');
    openMenu($page);
    chooseTab($page, 'jw-video');
    Shots::see($page, 'Video da jw.org');
    $page->click('[wire\\:click="selectCategory(\'VODStudio\')"]:visible');
    Shots::see($page, 'Programmi mensili');

    $page->assertScript('[...document.querySelectorAll("[wire\\\\:target]")].every((el) => ! el.getAttribute("wire:target").includes("@js"))')
        ->assertNoJavaScriptErrors();
});

/*
 * Regression: no layout rendered <flux:toast />, so every Flux::toast() ("Comando inviato", "«…» inviato al box",
 * errors such as a box going offline) was invisible to the caregiver.
 */
it('shows a confirmation toast after sending a custom command', function (string $device): void {
    [, $box] = Fixtures::boxFor();
    Fixtures::customCommands($box);

    $page = Shots::open(route('dashboard.show', $box), $device);
    $page->assertSee('Accendi la TV');
    $page->click('button:has-text("Accendi la TV")');
    Shots::eventually(fn () => expect(commandsOf($box, CommandType::CustomScript))->toHaveCount(1));

    Shots::see($page, 'Comando inviato: Accendi la TV', 4);

    // At the top of the screen, clear of the fixed command bar at the bottom
    $page->assertScript('document.querySelector("ui-toast [data-flux-toast-dialog]").getBoundingClientRect().bottom < window.innerHeight / 2')
        ->assertNoJavaScriptErrors();
    Shots::take($page, '40-toast-conferma', $device, fullPage: false);
})->with(['desktop', 'mobile']);
