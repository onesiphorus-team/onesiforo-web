<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function makeSs(OnesiBox $box, string $capturedAt, string $suffix = ''): ApplianceScreenshot {
    $path = "onesi-boxes/{$box->id}/screenshots/" . str_replace([':', ' '], '-', $capturedAt) . "_{$suffix}.webp";
    Storage::disk('local')->put($path, 'p');
    return ApplianceScreenshot::create([
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

    expect(ApplianceScreenshot::find($stale->id))->toBeNull();
    expect(ApplianceScreenshot::find($fresh->id))->not->toBeNull();
    expect(Storage::disk('local')->exists($stale->storage_path))->toBeFalse();
});

test('keeps top 10 most recent verbatim', function (): void {
    $box = OnesiBox::factory()->create();
    for ($i = 0; $i < 15; $i++) {
        makeSs($box, now()->subMinutes($i)->toDateTimeString(), (string) $i);
    }

    $this->artisan('onesibox:prune-screenshots')->assertSuccessful();

    expect(ApplianceScreenshot::where('onesi_box_id', $box->id)->count())->toBe(10);
});

test('rollup keeps one per hour bucket for records beyond top 10', function (): void {
    $box = OnesiBox::factory()->create();
    // 10 recenti nell'ultimo minuto
    for ($i = 0; $i < 10; $i++) {
        makeSs($box, now()->subSeconds($i * 5)->toDateTimeString(), "top{$i}");
    }
    // 3 record nella stessa ora (2 ore fa) — dopo rollup deve restarne 1
    $two_hours_ago = now()->subHours(2);
    makeSs($box, $two_hours_ago->copy()->addMinutes(5)->toDateTimeString(), 'h2-a');
    makeSs($box, $two_hours_ago->copy()->addMinutes(25)->toDateTimeString(), 'h2-b');
    makeSs($box, $two_hours_ago->copy()->addMinutes(45)->toDateTimeString(), 'h2-c');

    $this->artisan('onesibox:prune-screenshots')->assertSuccessful();

    $beyondTop10 = ApplianceScreenshot::where('onesi_box_id', $box->id)
        ->where('captured_at', '<', now()->subMinute())
        ->count();
    expect($beyondTop10)->toBe(1);
    expect(ApplianceScreenshot::where('onesi_box_id', $box->id)->count())->toBe(11);
});
