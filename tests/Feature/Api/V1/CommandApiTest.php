<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;

// ============================================
// User Story 1: GET /api/v1/appliances/commands
// ============================================

test('authenticated appliance retrieves pending commands ordered by priority', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    // Create commands with different priorities (1=high, 5=low)
    $lowPriority = Command::factory()
        ->for($onesiBox)
        ->pending()
        ->withPriority(5)
        ->create(['created_at' => now()->subMinutes(10)]);

    $highPriority = Command::factory()
        ->for($onesiBox)
        ->pending()
        ->withPriority(1)
        ->create(['created_at' => now()->subMinutes(5)]);

    $mediumPriority = Command::factory()
        ->for($onesiBox)
        ->pending()
        ->withPriority(3)
        ->create(['created_at' => now()]);

    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'payload',
                    'priority',
                    'status',
                    'created_at',
                    'expires_at',
                ],
            ],
            'meta' => [
                'total',
                'pending',
            ],
        ]);

    // Verify ordering: high priority (1) first, then medium (3), then low (5)
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
    expect($data[0]['id'])->toBe($highPriority->uuid);
    expect($data[1]['id'])->toBe($mediumPriority->uuid);
    expect($data[2]['id'])->toBe($lowPriority->uuid);
});

test('authenticated appliance with no pending commands receives empty list', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    // Create only completed commands
    Command::factory()
        ->for($onesiBox)
        ->completed()
        ->count(3)
        ->create();

    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.pending', 0);
});

test('appliance with invalid token receives 401 Unauthorized with error_code E001', function (): void {
    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => 'Bearer invalid-token-12345']
    );

    $response->assertUnauthorized();
});

test('request without token receives 401 Unauthorized', function (): void {
    $response = $this->getJson(route('api.v1.appliances.commands'));

    $response->assertUnauthorized();
});

test('disabled appliance receives 403 Forbidden with error_code E003', function (): void {
    $onesiBox = OnesiBox::factory()->inactive()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Appliance disabilitata.',
            'error_code' => 'E003',
        ]);
});

test('user token cannot access appliance commands endpoint', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('user-token');

    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertForbidden();
});

test('expired commands are automatically filtered and marked as expired', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    // Create a pending command that has expired
    $expiredCommand = Command::factory()
        ->for($onesiBox)
        ->expiredButPending()
        ->create();

    // Create a valid pending command
    $validCommand = Command::factory()
        ->for($onesiBox)
        ->pending()
        ->create();

    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $validCommand->uuid);

    // Verify the expired command was marked as expired
    expect($expiredCommand->fresh()->status)->toBe(CommandStatus::Expired);
});

test('status query parameter filters commands', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    Command::factory()->for($onesiBox)->pending()->create();
    Command::factory()->for($onesiBox)->completed()->create();
    Command::factory()->for($onesiBox)->failed()->create();

    // Default: only pending
    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk()->assertJsonCount(1, 'data');

    // status=all: all commands
    $response = $this->getJson(
        route('api.v1.appliances.commands', ['status' => 'all']),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('limit query parameter limits number of commands returned', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    Command::factory()->for($onesiBox)->pending()->count(5)->create();

    $response = $this->getJson(
        route('api.v1.appliances.commands', ['limit' => 2]),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.pending', 5);
});

test('limit query parameter validates minimum and maximum values', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    // Test limit below minimum
    $response = $this->getJson(
        route('api.v1.appliances.commands', ['limit' => 0]),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertUnprocessable()->assertJsonValidationErrors(['limit']);

    // Test limit above maximum
    $response = $this->getJson(
        route('api.v1.appliances.commands', ['limit' => 100]),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertUnprocessable()->assertJsonValidationErrors(['limit']);
});

test('appliance can only see its own commands', function (): void {
    $onesiBox1 = OnesiBox::factory()->create();
    $onesiBox2 = OnesiBox::factory()->create();
    $token1 = $onesiBox1->createToken('onesibox-api-token');

    // Create commands for both boxes
    $command1 = Command::factory()->for($onesiBox1)->pending()->create();
    Command::factory()->for($onesiBox2)->pending()->create();

    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token1->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $command1->uuid);
});

test('commands are ordered by priority then by created_at', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    // Two commands with same priority, different creation times
    $older = Command::factory()
        ->for($onesiBox)
        ->pending()
        ->withPriority(2)
        ->create(['created_at' => now()->subMinutes(10)]);

    $newer = Command::factory()
        ->for($onesiBox)
        ->pending()
        ->withPriority(2)
        ->create(['created_at' => now()]);

    $response = $this->getJson(
        route('api.v1.appliances.commands'),
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $data = $response->json('data');
    expect($data[0]['id'])->toBe($older->uuid); // Older command first (FIFO within same priority)
    expect($data[1]['id'])->toBe($newer->uuid);
});

// ============================================
// User Story 2: POST /api/v1/commands/{uuid}/ack
// ============================================

test('authenticated appliance acknowledges command with success updates status to completed', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');
    $command = Command::factory()->for($onesiBox)->pending()->create();

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [
            'status' => 'success',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJson([
            'data' => [
                'acknowledged' => true,
                'command_id' => $command->uuid,
                'status' => 'completed',
            ],
        ]);

    expect($command->fresh()->status)->toBe(CommandStatus::Completed);
    expect($command->fresh()->executed_at)->not->toBeNull();
});

test('authenticated appliance acknowledges command with failure updates status to failed', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');
    $command = Command::factory()->for($onesiBox)->pending()->create();

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [
            'status' => 'failed',
            'error_code' => 'E005',
            'error_message' => 'URL non raggiungibile',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJson([
            'data' => [
                'acknowledged' => true,
                'command_id' => $command->uuid,
                'status' => 'failed',
            ],
        ]);

    $freshCommand = $command->fresh();
    expect($freshCommand->status)->toBe(CommandStatus::Failed);
    expect($freshCommand->error_code)->toBe('E005');
    expect($freshCommand->error_message)->toBe('URL non raggiungibile');
});

test('acknowledging non-existent command returns 404 Not Found with error_code E002', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.commands.ack', ['command' => 'non-existent-uuid']),
        [
            'status' => 'success',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertNotFound()
        ->assertJson([
            'message' => 'Comando non trovato.',
            'error_code' => 'E002',
        ]);
});

test('acknowledging command belonging to another appliance returns 403 Forbidden with error_code E003', function (): void {
    $onesiBox1 = OnesiBox::factory()->create();
    $onesiBox2 = OnesiBox::factory()->create();
    $token1 = $onesiBox1->createToken('onesibox-api-token');

    // Command belongs to box2
    $command = Command::factory()->for($onesiBox2)->pending()->create();

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [
            'status' => 'success',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token1->plainTextToken}"]
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Comando non autorizzato per questa appliance.',
            'error_code' => 'E003',
        ]);
});

test('idempotent acknowledgment - already processed command returns 200 OK without state change', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    // Create an already completed command
    $command = Command::factory()->for($onesiBox)->completed()->create();
    $originalExecutedAt = $command->executed_at;

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [
            'status' => 'success',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJson([
            'data' => [
                'acknowledged' => true,
                'command_id' => $command->uuid,
                'status' => 'completed',
            ],
        ]);

    // State should not have changed
    $freshCommand = $command->fresh();
    expect($freshCommand->status)->toBe(CommandStatus::Completed);
    expect($freshCommand->executed_at->toIso8601String())->toBe($originalExecutedAt->toIso8601String());
});

test('acknowledging expired command returns success with status expired', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    // Create an expired command
    $command = Command::factory()->for($onesiBox)->expired()->create();

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [
            'status' => 'success',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJson([
            'data' => [
                'acknowledged' => true,
                'command_id' => $command->uuid,
                'status' => 'expired',
            ],
        ]);
});

test('ack endpoint validates required fields', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');
    $command = Command::factory()->for($onesiBox)->pending()->create();

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'executed_at']);
});

test('ack endpoint validates status must be valid enum', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');
    $command = Command::factory()->for($onesiBox)->pending()->create();

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [
            'status' => 'invalid-status',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('ack endpoint validates error_code max length', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');
    $command = Command::factory()->for($onesiBox)->pending()->create();

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [
            'status' => 'failed',
            'error_code' => 'TOOLONGERRORCODE',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['error_code']);
});

test('disabled appliance cannot acknowledge commands', function (): void {
    $onesiBox = OnesiBox::factory()->inactive()->create();
    $token = $onesiBox->createToken('onesibox-api-token');
    $command = Command::factory()->for($onesiBox)->pending()->create();

    $response = $this->postJson(
        route('api.v1.commands.ack', $command),
        [
            'status' => 'success',
            'executed_at' => now()->toIso8601String(),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Appliance disabilitata.',
            'error_code' => 'E003',
        ]);
});
