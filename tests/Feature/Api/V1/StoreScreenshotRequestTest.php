<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\StoreScreenshotRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

function validateScreenshotPayload(array $data): \Illuminate\Contracts\Validation\Validator {
    $request = new StoreScreenshotRequest();
    return Validator::make($data, $request->rules());
}

test('valid payload passes', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->subSeconds(10)->toIso8601String(),
        'width' => 1920,
        'height' => 1080,
        'screenshot' => UploadedFile::fake()->create('s.webp', 100, 'image/webp'),
    ]);
    expect($v->passes())->toBeTrue();
});

test('stale captured_at is rejected', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->subMinutes(10)->toIso8601String(),
        'width' => 1920, 'height' => 1080,
        'screenshot' => UploadedFile::fake()->create('s.webp', 100, 'image/webp'),
    ]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('captured_at'))->toBeTrue();
});

test('non-webp mime is rejected', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->toIso8601String(),
        'width' => 1920, 'height' => 1080,
        'screenshot' => UploadedFile::fake()->image('s.png'),
    ]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('screenshot'))->toBeTrue();
});

test('oversized file is rejected', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->toIso8601String(),
        'width' => 1920, 'height' => 1080,
        'screenshot' => UploadedFile::fake()->create('s.webp', 2100, 'image/webp'),
    ]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('screenshot'))->toBeTrue();
});

test('width out of range is rejected', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->toIso8601String(),
        'width' => 100, 'height' => 1080,
        'screenshot' => UploadedFile::fake()->create('s.webp', 100, 'image/webp'),
    ]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('width'))->toBeTrue();
});
