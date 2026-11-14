<?php

declare(strict_types=1);

use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Models\User;
use Oltrematica\RoleLite\Models\Role;
use Tests\Browser\Support\Fixtures;
use Tests\Browser\Support\Shots;

/*
 * Filament admin: box form (Telegram alerts toggle) and the Zoom meetings / custom commands relation managers.
 */

beforeEach(function (): void {
    Fixtures::fakeJwLibrary();
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $admin = User::factory()->create(['name' => 'Admin Onesiforo']);
    $admin->assignRole('super-admin');
    $this->actingAs($admin);
});

it('lists the boxes in the admin panel', function (string $device): void {
    OnesiBox::factory()->online()->create(['name' => 'Box Salotto']);

    $page = Shots::open('/admin/onesi-boxes', $device);

    $page->assertSee('Box Salotto')->assertNoJavaScriptErrors();
    Shots::take($page, '70-admin-elenco-box', $device);
})->with(['desktop', 'mobile']);

it('shows the Telegram alerts toggle and the new relation managers on the box page', function (string $device): void {
    $box = OnesiBox::factory()->online()->create(['name' => 'Box Salotto']);
    Fixtures::customCommands($box);
    CustomCommand::factory()->forBox($box)->runsBeforeContent()->create(['name' => 'Cambia ingresso TV', 'script_name' => 'tv-input.sh']);

    $page = Shots::open("/admin/onesi-boxes/{$box->id}/edit", $device);

    $page->assertSee('Avvisi Telegram')->assertSee('Riunioni Zoom')->assertSee('Comandi personalizzati');
    Shots::take($page, '71-admin-box-modifica', $device);

    $page->click('text=Riunioni Zoom');
    Shots::see($page, 'Nuova riunione');
    Shots::take($page, '72-admin-box-riunioni-zoom', $device, fullPage: false);

    $page->click('text=Comandi personalizzati');
    Shots::see($page, 'Cambia ingresso TV');
    $page->assertSee('Copia da un altro box');
    Shots::take($page, '74-admin-box-comandi-personalizzati', $device, fullPage: false);
    $page->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('adds a Zoom meeting to a box from the admin panel', function (): void {
    $box = OnesiBox::factory()->online()->create(['name' => 'Box Salotto']);

    $page = Shots::open("/admin/onesi-boxes/{$box->id}/edit", 'desktop');
    $page->assertSee('Riunioni Zoom');
    $page->click('text=Riunioni Zoom');
    Shots::see($page, 'Nuova riunione');
    $page->click('button:has-text("Nuova riunione")');
    Shots::see($page, 'Link Zoom');

    $page->fill('input[id$="data.name"], input[id="mountedActionSchema0.name"]', 'Adunanza Loreto');
    $page->fill('input[id$="data.zoom_url"], input[id="mountedActionSchema0.zoom_url"]', 'https://us05web.zoom.us/j/1234567890?pwd=abc');
    Shots::take($page, '73-admin-nuova-riunione-zoom', 'desktop', fullPage: false);
    $page->click('.fi-modal-footer-actions button:has-text("Crea"):visible');

    Shots::eventually(fn () => expect($box->zoomMeetings()->count())->toBe(1));
    expect($box->zoomMeetings()->sole()->name)->toBe('Adunanza Loreto');
});
