<?php

declare(strict_types=1);

use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('rejects mass assignment of server managed user fields', function (string $attribute, mixed $value): void {
    $user = User::factory()->unverified()->create();
    $originalAttributes = $user->refresh()->getAttributes();

    expect(fn () => $user->update([$attribute => $value]))
        ->toThrow(MassAssignmentException::class);

    expect($user->refresh()->getAttributes())->toBe($originalAttributes);
})->with([
    'primary key' => ['id', 999999],
    'verification' => ['email_verified_at', '2026-01-01 00:00:00'],
    'last login' => ['last_login_at', '2026-01-01 00:00:00'],
    'remember token' => ['remember_token', 'untrusted-token'],
    'two factor secret' => ['two_factor_secret', 'untrusted-secret'],
    'soft deletion' => ['deleted_at', '2026-01-01 00:00:00'],
]);

it('rejects mass assignment of device and command primary keys', function (string $modelClass): void {
    $record = $modelClass::factory()->create();
    $originalId = $record->id;

    expect(fn () => $record->update(['id' => 999999]))
        ->toThrow(MassAssignmentException::class);

    expect($record->refresh()->id)->toBe($originalId);
})->with([
    'device' => [OnesiBox::class],
    'command' => [Command::class],
]);

it('still allows intended user profile updates', function (): void {
    $user = User::factory()->create();

    $user->update(['name' => 'Updated Name', 'telegram_chat_id' => '12345']);

    expect($user->refresh()->name)->toBe('Updated Name')
        ->and($user->telegram_chat_id)->toBe('12345');
});
