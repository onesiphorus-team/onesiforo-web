<?php

declare(strict_types=1);

use App\Actions\GenerateOnesiBoxToken;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oltrematica\RoleLite\Models\Role;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);

    $this->onesiBox = OnesiBox::factory()->create();
});

it('generates a new token for an OnesiBox', function (): void {
    $action = new GenerateOnesiBoxToken;

    $newToken = $action($this->onesiBox);

    expect($newToken->plainTextToken)->toBeString()
        ->and($newToken->plainTextToken)->not->toBeEmpty()
        ->and($this->onesiBox->tokens()->count())->toBe(1);
});

it('generates token with default name', function (): void {
    $action = new GenerateOnesiBoxToken;

    $action($this->onesiBox);

    $token = $this->onesiBox->tokens()->first();
    expect($token->name)->toBe('onesibox-api-token');
});

it('generates token with custom name', function (): void {
    $action = new GenerateOnesiBoxToken;

    $action($this->onesiBox, 'custom-token-name');

    $token = $this->onesiBox->tokens()->first();
    expect($token->name)->toBe('custom-token-name');
});

it('generates token with default 1 year expiration', function (): void {
    $action = new GenerateOnesiBoxToken;

    $action($this->onesiBox);

    $token = $this->onesiBox->tokens()->first();
    $expectedDate = now()->addDays(365)->startOfDay();

    expect($token->expires_at)->not->toBeNull()
        ->and($token->expires_at->startOfDay()->equalTo($expectedDate))->toBeTrue();
});

it('generates token with custom expiration', function (): void {
    $action = new GenerateOnesiBoxToken;

    $action($this->onesiBox, 'test-token', ['*'], 30);

    $token = $this->onesiBox->tokens()->first();
    $expectedDate = now()->addDays(30)->startOfDay();

    expect($token->expires_at->startOfDay()->equalTo($expectedDate))->toBeTrue();
});

it('generates token with full abilities by default', function (): void {
    $action = new GenerateOnesiBoxToken;

    $action($this->onesiBox);

    $token = $this->onesiBox->tokens()->first();
    expect($token->abilities)->toBe(['*']);
});

it('generates token with custom abilities', function (): void {
    $action = new GenerateOnesiBoxToken;

    $action($this->onesiBox, 'test-token', ['read', 'write']);

    $token = $this->onesiBox->tokens()->first();
    expect($token->abilities)->toBe(['read', 'write']);
});

it('logs activity when token is generated', function (): void {
    $action = new GenerateOnesiBoxToken;

    $action($this->onesiBox, 'logged-token');

    $activity = Activity::query()
        ->where('subject_type', OnesiBox::class)
        ->where('subject_id', $this->onesiBox->id)
        ->where('description', 'API token generated')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->properties['token_name'])->toBe('logged-token');
});

it('can generate multiple tokens for same OnesiBox', function (): void {
    $action = new GenerateOnesiBoxToken;

    $action($this->onesiBox, 'token-1');
    $action($this->onesiBox, 'token-2');
    $action($this->onesiBox, 'token-3');

    expect($this->onesiBox->tokens()->count())->toBe(3);
});
