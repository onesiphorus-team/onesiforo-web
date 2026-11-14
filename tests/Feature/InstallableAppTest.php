<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('answers failed page loads with a 503 that tells apart "no connection" from "Onesiforo is not answering"', function (): void {
    $serviceWorker = (string) file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('status: 503')
        ->toContain('navigator.onLine === false')
        ->toContain('Il telefono non è connesso a Internet')
        ->toContain('Onesiforo non risponde')
        ->toContain('Riprova tra poco')
        ->toContain('<button onclick="location.reload()">Riprova</button>');
});

it('captures the browser install prompt once per page load, in the page head', function (): void {
    $head = (string) $this->get(route('login'))->getContent();

    expect(substr_count($head, "addEventListener('beforeinstallprompt'"))->toBe(1)
        ->and($head)->toContain('window.__installPromptListening')
        ->toContain('window.__installPrompt = event');
});

it('reads the captured install prompt in the hint, without adding listeners to window on every navigation', function (): void {
    $hint = Blade::render('<x-install-app-hint />');

    expect($hint)
        ->toContain('window.__installPrompt')
        ->toContain('x-on:install-prompt-available.window="syncPlatform()"')
        ->toContain('x-on:install-prompt-used.window="visible = false"')
        ->not->toContain('window.addEventListener');
});
