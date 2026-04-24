<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;

test('heartbeat response includes screenshot config fields', function (): void {
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 45,
    ]);
    $token = $box->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk()
        ->assertJsonPath('data.screenshot_enabled', true)
        ->assertJsonPath('data.screenshot_interval_seconds', 45);
});

test('heartbeat response reflects disabled state', function (): void {
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => false,
        'screenshot_interval_seconds' => 120,
    ]);
    $token = $box->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk()
        ->assertJsonPath('data.screenshot_enabled', false)
        ->assertJsonPath('data.screenshot_interval_seconds', 120);
});
