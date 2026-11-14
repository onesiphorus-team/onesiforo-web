<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Oltrematica\RoleLite\Models\Role;
use Tests\Browser\Support\Fixtures;
use Tests\Browser\Support\Shots;

/*
 * The running version is visible on every kind of page and never sits on top
 * of the content: on caregiver pages it lives at the foot of the menu.
 */

const VISIBLE_VERSIONS = "[...document.querySelectorAll('[data-slot=\"app-version\"]')].map(e => e.getBoundingClientRect()).filter(r => r.width > 0 && r.right > 0 && r.left < innerWidth && r.bottom > 0 && r.top < innerHeight)";

beforeEach(function (): void {
    Fixtures::fakeJwLibrary();
    cache()->flush();
    Process::fake(['*' => Process::result(output: 'v9.8.7')]);
});

it('shows the version on the login page', function (string $device): void {
    $page = Shots::open('/login', $device);

    $page->assertSee('v9.8.7')->assertNoJavaScriptErrors();
    $page->assertScript(VISIBLE_VERSIONS.'.length === 1');
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '90-versione-login', $device, fullPage: false);
})->with(['desktop', 'mobile']);

it('shows the version at the foot of the menu on desktop', function (): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), 'desktop');

    $page->assertSee('Maria Rossi')->assertSee('v9.8.7');
    $page->assertScript(VISIBLE_VERSIONS.'.length === 1');
    Shots::take($page, '91-versione-box', 'desktop', fullPage: false);
});

it('keeps the version off the page on phones and shows it in the menu', function (): void {
    [, $box] = Fixtures::boxFor();

    $page = Shots::open(route('dashboard.show', $box), 'mobile');

    $page->assertSee('Maria Rossi');
    $page->assertScript(VISIBLE_VERSIONS.'.length === 0');
    Shots::take($page, '91-versione-box', 'mobile', fullPage: false);

    $page->click('[data-flux-sidebar-toggle]');
    Shots::see($page, 'v9.8.7');
    Shots::take($page, '92-versione-menu', 'mobile', fullPage: false);
});

it('shows the version in the footer of the admin panel', function (string $device): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $admin = User::factory()->create(['name' => 'Admin Onesiforo']);
    $admin->assignRole('super-admin');
    $this->actingAs($admin);

    $page = Shots::open('/admin', $device);

    $page->assertSee('v9.8.7')->assertNoJavaScriptErrors();
    Shots::assertNoHorizontalScroll($page);
    Shots::take($page, '93-versione-admin', $device);
})->with(['desktop', 'mobile']);
