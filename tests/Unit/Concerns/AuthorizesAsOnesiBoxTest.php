<?php

declare(strict_types=1);

use App\Concerns\AuthorizesAsOnesiBox;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->testRequest = new class extends FormRequest
    {
        use AuthorizesAsOnesiBox;

        protected function getAuthLogContext(): string
        {
            return 'TestRequest';
        }
    };
});

it('authorizes request when user is an OnesiBox', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $this->actingAs($onesiBox, 'sanctum');

    $request = $this->testRequest;
    $request->setUserResolver(fn () => $onesiBox);

    expect($request->authorize())->toBeTrue();
});

it('denies request when user is a User model instead of OnesiBox', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    $request = $this->testRequest;
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeFalse();
});

it('denies request when user is null', function (): void {
    $request = $this->testRequest;
    $request->setUserResolver(fn (): null => null);

    expect($request->authorize())->toBeFalse();
});

it('returns OnesiBox instance from onesiBox method', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $this->actingAs($onesiBox, 'sanctum');

    $request = $this->testRequest;
    $request->setUserResolver(fn () => $onesiBox);

    $result = $request->onesiBox();

    expect($result)->toBeInstanceOf(OnesiBox::class)
        ->and($result->id)->toBe($onesiBox->id);
});

it('returns custom log context', function (): void {
    $reflection = new ReflectionMethod($this->testRequest, 'getAuthLogContext');

    expect($reflection->invoke($this->testRequest))->toBe('TestRequest');
});

it('uses default class name for log context when not overridden', function (): void {
    $defaultRequest = new class extends FormRequest
    {
        use AuthorizesAsOnesiBox;
    };

    $reflection = new ReflectionMethod($defaultRequest, 'getAuthLogContext');

    // The anonymous class has a generated name, so we just check it returns a string
    expect($reflection->invoke($defaultRequest))->toBeString();
});
