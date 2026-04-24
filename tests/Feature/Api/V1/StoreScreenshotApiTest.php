<?php

declare(strict_types=1);

use App\Events\ApplianceScreenshotReceived;
use App\Models\OnesiBox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('valid screenshot upload creates record and file', function (): void {
    Storage::fake('local');
    Event::fake([ApplianceScreenshotReceived::class]);

    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        [
            'captured_at' => now()->subSeconds(5)->toIso8601String(),
            'width' => 1920,
            'height' => 1080,
            'screenshot' => UploadedFile::fake()->create('s.webp', 120, 'image/webp'),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertCreated()
        ->assertJsonStructure(['id']);

    $this->assertDatabaseHas('appliance_screenshots', [
        'onesi_box_id' => $box->id,
        'width' => 1920,
    ]);

    Event::assertDispatched(ApplianceScreenshotReceived::class);
});

test('unauthenticated request is rejected', function (): void {
    $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        ['captured_at' => now()->toIso8601String(), 'width' => 1920, 'height' => 1080]
    )->assertUnauthorized();
});

test('non-webp upload returns 422', function (): void {
    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        [
            'captured_at' => now()->toIso8601String(),
            'width' => 1920, 'height' => 1080,
            'screenshot' => UploadedFile::fake()->image('s.png'),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    )->assertStatus(422);
});

test('oversized file returns 422', function (): void {
    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        [
            'captured_at' => now()->toIso8601String(),
            'width' => 1920, 'height' => 1080,
            'screenshot' => UploadedFile::fake()->create('s.webp', 2100, 'image/webp'),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    )->assertStatus(422);
});

test('rate limit enforces 12 per minute', function (): void {
    Storage::fake('local');

    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $make = fn () => $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        [
            'captured_at' => now()->subSeconds(1)->toIso8601String(),
            'width' => 1920, 'height' => 1080,
            'screenshot' => UploadedFile::fake()->create('s.webp', 50, 'image/webp'),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    for ($i = 0; $i < 12; $i++) {
        $make()->assertCreated();
    }
    // 13th request in the same minute must be throttled
    $make()->assertStatus(429);
});
