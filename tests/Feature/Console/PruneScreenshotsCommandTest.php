<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function makeSs(OnesiBox $box, string $capturedAt, string $suffix = ''): ApplianceScreenshot
{
    $path = "onesi-boxes/{$box->id}/screenshots/".str_replace([':', ' '], '-', $capturedAt)."_{$suffix}.webp";
    Storage::disk('local')->put($path, 'p');

    return ApplianceScreenshot::query()->create([
        'onesi_box_id' => $box->id,
        'captured_at' => $capturedAt,
        'width' => 1920, 'height' => 1080, 'bytes' => 1,
        'storage_path' => $path,
    ]);
}

test('records older than 24h are deleted (record + file)', function (): void {
    $box = OnesiBox::factory()->create();
    $stale = makeSs($box, now()->subHours(25)->toDateTimeString(), 'stale');
    $fresh = makeSs($box, now()->toDateTimeString(), 'fresh');

    $this->artisan('onesibox:prune-screenshots')->assertSuccessful();

    expect(ApplianceScreenshot::query()->find($stale->id))->toBeNull();
    expect(ApplianceScreenshot::query()->find($fresh->id))->not->toBeNull();
    expect(Storage::disk('local')->exists($stale->storage_path))->toBeFalse();
});

test('keeps top 10 most recent verbatim', function (): void {
    $box = OnesiBox::factory()->create();
    for ($i = 0; $i < 15; $i++) {
        makeSs($box, now()->subMinutes($i)->toDateTimeString(), (string) $i);
    }

    $this->artisan('onesibox:prune-screenshots')->assertSuccessful();

    expect(ApplianceScreenshot::query()->where('onesi_box_id', $box->id)->count())->toBe(10);
});

test('rollup keeps one per hour bucket for records beyond top 10', function (): void {
    $box = OnesiBox::factory()->create();
    // 10 recenti nell'ultimo minuto
    for ($i = 0; $i < 10; $i++) {
        makeSs($box, now()->subSeconds($i * 5)->toDateTimeString(), "top{$i}");
    }
    // 3 record tutti nella stessa ora (2 ore fa, aligned allo startOfHour per evitare
    // di attraversare il confine di ora quando l'attuale minute-of-hour > 14).
    $sameHour = now()->subHours(2)->startOfHour();
    makeSs($box, $sameHour->copy()->addMinutes(5)->toDateTimeString(), 'h2-a');
    makeSs($box, $sameHour->copy()->addMinutes(25)->toDateTimeString(), 'h2-b');
    makeSs($box, $sameHour->copy()->addMinutes(45)->toDateTimeString(), 'h2-c');

    $this->artisan('onesibox:prune-screenshots')->assertSuccessful();

    $beyondTop10 = ApplianceScreenshot::query()->where('onesi_box_id', $box->id)
        ->where('captured_at', '<', now()->subMinute())
        ->count();
    expect($beyondTop10)->toBe(1);
    expect(ApplianceScreenshot::query()->where('onesi_box_id', $box->id)->count())->toBe(11);
});

test('orphan sweep removes untracked files and keeps tracked ones', function (): void {
    $box = OnesiBox::factory()->create();

    $tracked = makeSs($box, now()->toDateTimeString(), 'tracked');
    $orphanPath = "onesi-boxes/{$box->id}/screenshots/orphan.webp";
    Storage::disk('local')->put($orphanPath, 'orphan');

    $this->artisan('onesibox:prune-screenshots --sweep-orphans')->assertSuccessful();

    expect(Storage::disk('local')->exists($tracked->storage_path))->toBeTrue();
    expect(Storage::disk('local')->exists($orphanPath))->toBeFalse();
});
