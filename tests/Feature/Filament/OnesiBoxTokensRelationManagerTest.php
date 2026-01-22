<?php

declare(strict_types=1);

use App\Filament\Resources\OnesiBoxes\Pages\EditOnesiBox;
use App\Filament\Resources\OnesiBoxes\RelationManagers\TokensRelationManager;
use App\Models\OnesiBox;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oltrematica\RoleLite\Models\Role;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);

    $this->onesiBox = OnesiBox::factory()->create();
});

// ============================================================================
// User Story 2: Generate Authentication Token (T013, T014, T015)
// ============================================================================

describe('US2: Generate Authentication Token', function (): void {
    it('can render the TokensRelationManager', function (): void {
        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertOk();
    });

    it('displays empty state when no tokens exist', function (): void {
        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([]);
    });

    it('can generate a new token via header action', function (): void {
        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callAction(TestAction::make('generate_token')->table())
            ->assertHasNoActionErrors();

        expect($this->onesiBox->tokens()->count())->toBe(1);
    });

    it('displays generated token in the table', function (): void {
        $token = $this->onesiBox->createToken('test-token', ['*'], now()->addYear());

        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertCanSeeTableRecords([$token->accessToken]);
    });

    it('logs activity when token is generated', function (): void {
        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callAction(TestAction::make('generate_token')->table())
            ->assertHasNoActionErrors();

        $activity = Activity::query()
            ->where('subject_type', OnesiBox::class)
            ->where('subject_id', $this->onesiBox->id)
            ->where('description', 'API token generated')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($this->admin->id);
    });
});

// ============================================================================
// User Story 3: View Token Usage History (T022, T023)
// ============================================================================

describe('US3: View Token Usage History', function (): void {
    it('displays token last_used_at timestamp', function (): void {
        $token = $this->onesiBox->createToken('test-token', ['*'], now()->addYear());
        $token->accessToken->update(['last_used_at' => now()->subHour()]);

        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertCanSeeTableRecords([$token->accessToken])
            ->assertTableColumnExists('last_used_at');
    });

    it('displays "Never" placeholder when token has not been used', function (): void {
        $token = $this->onesiBox->createToken('test-token', ['*'], now()->addYear());

        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertCanSeeTableRecords([$token->accessToken])
            ->assertTableColumnExists('last_used_at');

        expect($token->accessToken->last_used_at)->toBeNull();
    });
});

// ============================================================================
// User Story 4: Revoke Authentication Token (T027, T028, T029)
// ============================================================================

describe('US4: Revoke Authentication Token', function (): void {
    it('can see revoke action on token row', function (): void {
        $token = $this->onesiBox->createToken('test-token', ['*'], now()->addYear());

        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->assertTableActionVisible('delete', $token->accessToken);
    });

    it('can revoke a token with confirmation', function (): void {
        $token = $this->onesiBox->createToken('test-token', ['*'], now()->addYear());
        $tokenId = $token->accessToken->id;

        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('delete', $token->accessToken)
            ->assertHasNoTableActionErrors();

        expect($this->onesiBox->tokens()->count())->toBe(0);
    });

    it('logs activity when token is revoked', function (): void {
        $token = $this->onesiBox->createToken('test-token', ['*'], now()->addYear());
        $tokenId = $token->accessToken->id;

        livewire(TokensRelationManager::class, [
            'ownerRecord' => $this->onesiBox,
            'pageClass' => EditOnesiBox::class,
        ])
            ->callTableAction('delete', $token->accessToken);

        $activity = Activity::query()
            ->where('subject_type', OnesiBox::class)
            ->where('subject_id', $this->onesiBox->id)
            ->where('description', 'API token revoked')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($this->admin->id);
    });
});
