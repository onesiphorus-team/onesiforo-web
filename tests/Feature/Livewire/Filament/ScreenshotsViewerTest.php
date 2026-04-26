<?php

declare(strict_types=1);

use App\Livewire\Filament\ScreenshotsViewer;
use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function (): void {
    // Pin "now" to a UTC moment that is firmly inside Europe/Rome DST (UTC+2).
    // 18:00:00 UTC == 20:00:00 Europe/Rome on 2026-04-25.
    Carbon::setTestNow(Carbon::parse('2026-04-25 18:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeScreenshotAt(OnesiBox $box, Carbon $capturedAt, ?int $seq = null): ApplianceScreenshot
{
    static $counter = 0;
    $counter++;
    $token = $seq ?? $counter;

    return ApplianceScreenshot::query()->create([
        'onesi_box_id' => $box->id,
        'captured_at' => $capturedAt,
        'width' => 1920,
        'height' => 1080,
        'bytes' => 1024,
        'storage_path' => "fake/path-{$box->id}-{$token}.webp",
    ]);
}

it('collapses screenshots beyond the top 10 to one per local hour', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);

    // 70 screenshots spaced 60 seconds apart, going backwards from "now".
    for ($i = 0; $i < 70; $i++) {
        makeScreenshotAt($box, Carbon::now()->subSeconds(60 * $i), $i);
    }

    $component = Livewire::actingAs($user)
        ->test(ScreenshotsViewer::class, ['record' => $box])
        ->instance();

    // Top10 spans Rome hours 19 (i=1..9) and 20 (i=0). Beyond top10, screenshots
    // continue down through Rome hours 18 and 19, then into 18 only — but hour 19
    // is excluded because it overlaps top10. So exactly one bucket survives: 18.
    expect($component->top10)->toHaveCount(10)
        ->and($component->hourlyBeyondTop10)->toHaveCount(1);
});

it('returns one entry per distinct local hour for the hourly section', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);

    // Top10 noise: 10 screenshots spanning Rome hours 20:00 (i=0) and 19:55:30..19:59:30 (i=1..9).
    for ($i = 0; $i < 10; $i++) {
        makeScreenshotAt($box, Carbon::now()->subSeconds(30 * $i));
    }

    // Pick 4 historical UTC hours that map to distinct, top-of-hour Rome hours
    // (UTC 14, 13, 12, 11 == Rome 16, 15, 14, 13 during DST).
    foreach ([4, 5, 6, 7] as $hoursAgo) {
        $hourTop = Carbon::now()->subHours($hoursAgo);
        for ($minute = 0; $minute < 8; $minute++) {
            makeScreenshotAt($box, $hourTop->copy()->addMinutes($minute * 5));
        }
    }

    $component = Livewire::actingAs($user)
        ->test(ScreenshotsViewer::class, ['record' => $box])
        ->instance();

    expect($component->top10)->toHaveCount(10)
        ->and($component->hourlyBeyondTop10)->toHaveCount(4);
});

it('renders without errors when there are fewer than 10 screenshots', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);

    for ($i = 0; $i < 3; $i++) {
        makeScreenshotAt($box, Carbon::now()->subSeconds(30 * $i));
    }

    $component = Livewire::actingAs($user)
        ->test(ScreenshotsViewer::class, ['record' => $box]);

    $component->assertSuccessful();

    expect($component->instance()->top10)->toHaveCount(3)
        ->and($component->instance()->hourlyBeyondTop10)->toHaveCount(0);
});

it('returns an empty hourly section when nothing is older than the top 10', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);

    // 10 screenshots all packed into the same minute — top10 only.
    for ($i = 0; $i < 10; $i++) {
        makeScreenshotAt($box, Carbon::now()->subSeconds(5 * $i));
    }

    $component = Livewire::actingAs($user)
        ->test(ScreenshotsViewer::class, ['record' => $box])
        ->instance();

    expect($component->top10)->toHaveCount(10)
        ->and($component->hourlyBeyondTop10)->toHaveCount(0);
});

it('excludes screenshots older than 24 hours from recent24h and hourly', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);

    // Top10 placeholder so the page renders.
    makeScreenshotAt($box, Carbon::now());

    // One screenshot inside the window, one beyond.
    makeScreenshotAt($box, Carbon::now()->subHours(23));
    $stale = makeScreenshotAt($box, Carbon::now()->subHours(25));

    $component = Livewire::actingAs($user)
        ->test(ScreenshotsViewer::class, ['record' => $box])
        ->instance();

    expect($component->recent24h->pluck('id')->all())->not->toContain($stale->id)
        ->and($component->hourlyBeyondTop10->pluck('id')->all())->not->toContain($stale->id);
});

it('resolves selected from the DB when the id is no longer in top10 or hourly', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);

    // Top10 fills with recent captures.
    for ($i = 0; $i < 10; $i++) {
        makeScreenshotAt($box, Carbon::now()->subSeconds(5 * $i));
    }

    // The user clicked an older screenshot. Then another, newer screenshot
    // arrived in the same hour bucket and replaced it as the bucket
    // representative — the orphan is now in the DB but in neither strip.
    $orphan = makeScreenshotAt($box, Carbon::parse('2026-04-25 12:00:00', 'UTC'));
    makeScreenshotAt($box, Carbon::parse('2026-04-25 12:30:00', 'UTC'));

    $component = Livewire::actingAs($user)
        ->test(ScreenshotsViewer::class, ['record' => $box])
        ->call('select', $orphan->id);

    expect($component->instance()->top10->pluck('id')->all())->not->toContain($orphan->id)
        ->and($component->instance()->hourlyBeyondTop10->pluck('id')->all())->not->toContain($orphan->id)
        ->and($component->instance()->selected?->id)->toBe($orphan->id);
});

it('does not leak selected lookups across boxes', function (): void {
    $user = User::factory()->create();
    $boxA = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);
    $boxB = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);

    makeScreenshotAt($boxA, Carbon::now());
    $foreign = makeScreenshotAt($boxB, Carbon::now());

    $component = Livewire::actingAs($user)
        ->test(ScreenshotsViewer::class, ['record' => $boxA])
        ->call('select', $foreign->id);

    expect($component->instance()->selected)->toBeNull();
});

it('renders the hourly bucket label using the Europe/Rome timezone, not UTC', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 60,
    ]);

    // 10 recent screenshots so the top10 section is full and the hourly section
    // surfaces older entries.
    for ($i = 0; $i < 10; $i++) {
        makeScreenshotAt($box, Carbon::now()->subSeconds(30 * $i));
    }

    // 14:00:00 UTC == 16:00:00 Europe/Rome (DST). The blade label must show 16:00,
    // not the underlying UTC 14:00.
    makeScreenshotAt($box, Carbon::parse('2026-04-25 14:00:00', 'UTC'));

    Livewire::actingAs($user)
        ->test(ScreenshotsViewer::class, ['record' => $box])
        ->assertSee('16:00')
        ->assertDontSee('14:00');
});
