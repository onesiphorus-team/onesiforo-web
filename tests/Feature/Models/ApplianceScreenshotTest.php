<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('deleting a screenshot removes the file from disk', function (): void {
    Storage::fake('local');
    $box = OnesiBox::factory()->create();

    $file = UploadedFile::fake()->create('s.webp', 50, 'image/webp');
    $path = "onesi-boxes/{$box->id}/screenshots/test.webp";
    Storage::disk('local')->put($path, $file->getContent());

    $screenshot = ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920,
        'height' => 1080,
        'bytes' => 1234,
        'storage_path' => $path,
    ]);

    expect(Storage::disk('local')->exists($path))->toBeTrue();

    $screenshot->delete();

    expect(Storage::disk('local')->exists($path))->toBeFalse();
});

test('screenshot belongs to onesiBox', function (): void {
    $box = OnesiBox::factory()->create();
    $screenshot = ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920,
        'height' => 1080,
        'bytes' => 100,
        'storage_path' => 'fake/path.webp',
    ]);

    expect($screenshot->onesiBox->is($box))->toBeTrue();
});
