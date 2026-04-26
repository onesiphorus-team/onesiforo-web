<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;

test('onesiBox has screenshots relation', function (): void {
    $box = OnesiBox::factory()->create();
    ApplianceScreenshot::query()->create([
        'onesi_box_id' => $box->id,
        'captured_at' => now()->subMinutes(2),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => 'p1.webp',
    ]);
    ApplianceScreenshot::query()->create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => 'p2.webp',
    ]);

    expect($box->screenshots)->toHaveCount(2);
});

test('latestScreenshot returns the most recent by captured_at', function (): void {
    $box = OnesiBox::factory()->create();
    $older = ApplianceScreenshot::query()->create([
        'onesi_box_id' => $box->id,
        'captured_at' => now()->subMinutes(10),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => 'old.webp',
    ]);
    $newer = ApplianceScreenshot::query()->create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => 'new.webp',
    ]);

    expect($box->fresh()->latestScreenshot->is($newer))->toBeTrue();
});
