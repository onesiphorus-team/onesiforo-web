<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;

test('heartbeat persists current_media info to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'url' => 'https://example.com/video.mp4',
                'type' => 'video',
                'title' => 'Test Video',
                'position' => 120,
                'duration' => 3600,
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_media_url)->toBe('https://example.com/video.mp4');
    expect($onesiBox->current_media_type)->toBe('video');
    expect($onesiBox->current_media_title)->toBe('Test Video');
});

test('heartbeat persists current_meeting info to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Calling->value,
            'current_meeting' => [
                'meeting_id' => '123456789',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_meeting_id)->toBe('123456789');
});

test('heartbeat persists volume to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create(['volume' => 80]);
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'volume' => 60,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->volume)->toBe(60);
});

test('heartbeat clears media info when not playing', function (): void {
    $onesiBox = OnesiBox::factory()->create([
        'current_media_url' => 'https://example.com/old.mp4',
        'current_media_type' => 'video',
        'current_media_title' => 'Old Video',
    ]);
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_media_url)->toBeNull();
    expect($onesiBox->current_media_type)->toBeNull();
    expect($onesiBox->current_media_title)->toBeNull();
});

test('heartbeat clears meeting info when not calling', function (): void {
    $onesiBox = OnesiBox::factory()->create([
        'current_meeting_id' => '123456789',
    ]);
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_meeting_id)->toBeNull();
});

test('heartbeat validates volume must be between 0 and 100', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'volume' => 150,
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['volume']);
});

test('heartbeat accepts current_meeting without meeting_id', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Calling->value,
            'current_meeting' => [
                'meeting_url' => 'https://zoom.us/j/123456',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();
});

test('heartbeat accepts audio type for current_media', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'url' => 'https://example.com/audio.mp3',
                'type' => 'audio',
                'title' => 'Test Audio',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->current_media_type)->toBe('audio');
});

test('heartbeat updates status field on onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create(['status' => OnesiBoxStatus::Idle]);
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Playing->value,
            'current_media' => [
                'url' => 'https://example.com/video.mp4',
                'type' => 'video',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->status)->toBe(OnesiBoxStatus::Playing);
});

// Extended diagnostics tests

test('heartbeat persists app_version to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'app_version' => '1.2.3',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->app_version)->toBe('1.2.3');
});

test('heartbeat persists network info to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'network' => [
                'type' => 'wifi',
                'interface' => 'wlan0',
                'ip' => '192.168.1.100',
                'netmask' => '255.255.255.0',
                'gateway' => '192.168.1.1',
                'mac' => 'aa:bb:cc:dd:ee:ff',
                'dns' => ['8.8.8.8', '8.8.4.4'],
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->network_type)->toBe('wifi');
    expect($onesiBox->network_interface)->toBe('wlan0');
    expect($onesiBox->ip_address)->toBe('192.168.1.100');
    expect($onesiBox->netmask)->toBe('255.255.255.0');
    expect($onesiBox->gateway)->toBe('192.168.1.1');
    expect($onesiBox->mac_address)->toBe('aa:bb:cc:dd:ee:ff');
    expect($onesiBox->dns_servers)->toBe(['8.8.8.8', '8.8.4.4']);
});

test('heartbeat persists wifi info to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'wifi' => [
                'ssid' => 'MyNetwork',
                'signal_dbm' => -65,
                'signal_percent' => 70,
                'channel' => 6,
                'frequency' => 2437,
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->wifi_ssid)->toBe('MyNetwork');
    expect($onesiBox->wifi_signal_dbm)->toBe(-65);
    expect($onesiBox->wifi_signal_percent)->toBe(70);
    expect($onesiBox->wifi_channel)->toBe(6);
    expect($onesiBox->wifi_frequency)->toBe(2437);
});

test('heartbeat persists detailed memory info to onesibox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'memory' => [
                'total' => 4294967296,  // 4GB
                'used' => 2147483648,    // 2GB
                'free' => 1073741824,    // 1GB
                'available' => 2147483648,
                'buffers' => 268435456,  // 256MB
                'cached' => 536870912,   // 512MB
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk();

    $onesiBox->refresh();
    expect($onesiBox->memory_total)->toBe(4294967296);
    expect($onesiBox->memory_used)->toBe(2147483648);
    expect($onesiBox->memory_free)->toBe(1073741824);
    expect($onesiBox->memory_available)->toBe(2147483648);
    expect($onesiBox->memory_buffers)->toBe(268435456);
    expect($onesiBox->memory_cached)->toBe(536870912);
});

test('heartbeat validates network type must be wifi or ethernet', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'network' => [
                'type' => 'invalid',
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['network.type']);
});

test('heartbeat validates wifi signal_dbm must be negative', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'wifi' => [
                'signal_dbm' => 50,  // Should be negative
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['wifi.signal_dbm']);
});

test('heartbeat validates wifi signal_percent must be 0-100', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        [
            'status' => OnesiBoxStatus::Idle->value,
            'wifi' => [
                'signal_percent' => 150,
            ],
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['wifi.signal_percent']);
});
