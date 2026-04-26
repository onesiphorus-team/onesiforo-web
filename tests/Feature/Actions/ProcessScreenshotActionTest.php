<?php

declare(strict_types=1);

use App\Actions\ProcessScreenshotAction;
use App\Events\ApplianceScreenshotReceived;
use App\Models\OnesiBox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('action persists file and record and dispatches event', function (): void {
    Storage::fake('local');
    Event::fake([ApplianceScreenshotReceived::class]);

    $box = OnesiBox::factory()->create();
    $file = UploadedFile::fake()->create('s.webp', 120, 'image/webp');
    $capturedAt = now()->subSeconds(5);

    $action = resolve(ProcessScreenshotAction::class);
    $screenshot = $action->execute($box, $capturedAt, 1920, 1080, $file);

    expect($screenshot->onesi_box_id)->toBe($box->id)
        ->and($screenshot->width)->toBe(1920)
        ->and($screenshot->height)->toBe(1080)
        ->and($screenshot->storage_path)->toStartWith("onesi-boxes/{$box->id}/screenshots/")
        ->and($screenshot->storage_path)->toEndWith('.webp');

    Storage::disk('local')->assertExists($screenshot->storage_path);
    Event::assertDispatched(ApplianceScreenshotReceived::class, fn ($e) => $e->screenshot->is($screenshot));
});
