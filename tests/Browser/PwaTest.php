<?php

declare(strict_types=1);

use Tests\Browser\Support\Fixtures;
use Tests\Browser\Support\Shots;

/*
 * PWA: manifest, service worker, icons and the "add to home screen" hint.
 */

it('serves a valid web manifest with icons that exist', function (): void {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toMatchArray(['name' => 'Onesiforo', 'start_url' => '/dashboard', 'display' => 'standalone', 'lang' => 'it']);

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }

    $page = visit('/manifest.webmanifest');
    $page->assertSee('Onesiforo')->assertSee('standalone');
});

it('serves the service worker script', function (): void {
    $page = visit('/sw.js');

    $page->assertSee('self.addEventListener')->assertSee('Onesiforo non risponde');
});

it('links the manifest, theme colour and icons from every page', function (): void {
    $page = visit('/login');

    $page->assertPresent('link[rel="manifest"][href="/manifest.webmanifest"]')
        ->assertPresent('link[rel="apple-touch-icon"]')
        ->assertPresent('meta[name="theme-color"]')
        ->assertNoJavaScriptErrors();
});

it('shows the install hint to a phone user and remembers when it is dismissed', function (): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), 'mobile');

    $page->assertSee('Tieni Onesiforo sulla schermata Home')
        ->assertSee('Aggiungi a schermata Home')
        ->assertVisible('[data-slot="install-app-hint"]')
        ->assertNoJavaScriptErrors();
    Shots::take($page, '60-pwa-suggerimento-installazione-android', 'mobile', fullPage: false);

    $page->click('[data-slot="install-app-hint"] button:has-text("Non ora")');
    Shots::eventually(fn () => $page->assertScript("localStorage.getItem('onesiforo:install-hint-dismissed') === '1'"));
    Shots::eventually(fn () => $page->assertScript("getComputedStyle(document.querySelector('[data-slot=\"install-app-hint\"]')).display === 'none'"));

    $page->refresh();
    Shots::eventually(fn () => $page->assertScript("getComputedStyle(document.querySelector('[data-slot=\"install-app-hint\"]')).display === 'none'"));
});

it('shows iPhone instructions to an iOS user', function (): void {
    [, $box] = Fixtures::boxFor();

    $page = visit(route('dashboard.show', $box))->on()->mobile()
        ->withUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1')
        ->resize(...Shots::MOBILE);

    $page->assertSee('Aggiungi alla schermata Home')->assertSee('Condividi')->assertNoJavaScriptErrors();
    Shots::take($page, '61-pwa-suggerimento-installazione-ios', 'mobile', fullPage: false);
});

it('does not show the install hint on desktop', function (): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), 'desktop');

    $page->assertSee('Maria Rossi')->assertDontSee('Tieni Onesiforo sulla schermata Home')->assertNoJavaScriptErrors();
});
