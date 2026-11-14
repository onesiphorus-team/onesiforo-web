<?php

declare(strict_types=1);

use App\Support\LastShownLabel;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    config(['app.display_timezone' => 'Europe/Rome']);
    // 14 Nov 2026, 11:00 in Rome
    $this->travelTo(Date::parse('2026-11-14 10:00:00', 'UTC'));
});

it('describes when an item was last shown with decreasing granularity', function (?string $lastShownAtUtc, string $expectedLabel): void {
    $lastShownAt = $lastShownAtUtc === null ? null : Date::parse($lastShownAtUtc, 'UTC');

    expect(LastShownLabel::for($lastShownAt))->toBe($expectedLabel);
})->with([
    'never' => [null, 'Mai mostrato'],
    'seconds ago' => ['2026-11-14 09:59:30', 'Proprio ora'],
    'one minute ago' => ['2026-11-14 09:59:00', '1 minuto fa'],
    'minutes ago' => ['2026-11-14 09:15:00', '45 minuti fa'],
    'earlier today, in Rome time' => ['2026-11-14 06:32:00', 'Oggi alle 07:32'],
    'yesterday' => ['2026-11-13 17:05:00', 'Ieri alle 18:05'],
    'a few days ago' => ['2026-11-11 08:00:00', '3 giorni fa'],
    'this year' => ['2026-10-03 08:00:00', 'Il 3 ottobre'],
    'previous year' => ['2025-12-24 08:00:00', 'Il 24 dicembre 2025'],
]);

it('flags as recent only items shown in the last seven days', function (?string $lastShownAtUtc, bool $expectedRecent): void {
    $lastShownAt = $lastShownAtUtc === null ? null : Date::parse($lastShownAtUtc, 'UTC');

    expect(LastShownLabel::isRecent($lastShownAt))->toBe($expectedRecent);
})->with([
    'never shown' => [null, false],
    'six days ago' => ['2026-11-08 10:00:00', true],
    'eight days ago' => ['2026-11-06 10:00:00', false],
]);
