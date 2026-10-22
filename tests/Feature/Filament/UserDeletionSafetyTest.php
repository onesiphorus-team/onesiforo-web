<?php

declare(strict_types=1);

use App\Actions\DeleteUserAction;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'caregiver'] as $role) {
        Role::query()->firstOrCreate(['name' => $role]);
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

it('skips the acting super admin in a mixed bulk deletion', function (string $action): void {
    $user = User::factory()->create();

    $page = livewire(ListUsers::class);
    if ($action === ForceDeleteBulkAction::class) {
        $page->filterTable('trashed', true);
    }

    $page->callTableBulkAction($action, [$this->admin, $user]);

    expect(User::query()->find($this->admin->id))->not->toBeNull()
        ->and(User::query()->find($user->id))->toBeNull();
    if ($action === ForceDeleteBulkAction::class) {
        $this->assertModelMissing($user);
    } else {
        $this->assertSoftDeleted($user);
    }
})->with(['soft delete' => [DeleteBulkAction::class], 'force delete' => [ForceDeleteBulkAction::class]]);

it('does not allow a previously authorized actor to delete after losing super admin', function (bool $force): void {
    $target = User::factory()->create();
    $target->assignRole('super-admin');
    $this->admin->load('roles');
    User::query()->findOrFail($this->admin->id)->removeRole('super-admin');

    expect(app(DeleteUserAction::class)($target, $force))->toBeFalse()
        ->and(User::query()->find($target->id))->not->toBeNull();
})->with([false, true]);

it('does not allow a deleted actor to delete the remaining super admin', function (): void {
    $target = User::factory()->create();
    $target->assignRole('super-admin');
    $this->admin->delete();

    expect(app(DeleteUserAction::class)($target))->toBeFalse()
        ->and(User::query()->find($target->id))->not->toBeNull();
});

it('supports deleting another user from the edit page', function (): void {
    $target = User::factory()->create();

    livewire(EditUser::class, ['record' => $target->id])
        ->callAction(DeleteAction::class);

    $this->assertSoftDeleted($target);
});

it('rejects saving a user with an actor deleted after the form loads', function (): void {
    $target = User::factory()->create(['name' => 'Original Name']);
    $target->assignRole('super-admin');
    $page = livewire(EditUser::class, ['record' => $target->id])
        ->fillForm(['name' => 'Forbidden Change']);

    User::query()->findOrFail($this->admin->id)->delete();

    $page->call('save')->assertForbidden();

    expect($target->refresh()->name)->toBe('Original Name')
        ->and($target->hasRole('super-admin'))->toBeTrue();
});

it('supports permanent deletion from the edit page', function (): void {
    $target = User::factory()->create();
    $target->delete();

    livewire(EditUser::class, ['record' => $target->id])
        ->callAction(Filament\Actions\ForceDeleteAction::class);

    $this->assertModelMissing($target);
});

it('can restore bulk deleted users', function (): void {
    $users = User::factory()->count(2)->create();

    livewire(ListUsers::class)->callTableBulkAction(DeleteBulkAction::class, $users);

    livewire(ListUsers::class)
        ->filterTable('trashed', true)
        ->callTableBulkAction(Filament\Actions\RestoreBulkAction::class, $users);

    foreach ($users as $user) {
        expect($user->refresh()->trashed())->toBeFalse();
    }
});
