<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\User;
use Filament\Facades\Filament;

describe('initials', function (): void {
    it('returns the first letter of the first two words for a multi-word name', function (): void {
        $user = User::factory()->make(['name' => 'Maika Costa']);

        expect($user->initials())->toBe('MC');
    });

    it('returns a single letter for a single-word name', function (): void {
        $user = User::factory()->make(['name' => 'Mira']);

        expect($user->initials())->toBe('M');
    });

    it('caps at two letters even for very long names', function (): void {
        $user = User::factory()->make(['name' => 'Anna Maria Beatrice Castelli']);

        expect($user->initials())->toBe('AM');
    });

    it('returns an empty string when the name is empty', function (): void {
        $user = User::factory()->make(['name' => '']);

        expect($user->initials())->toBe('');
    });
});

describe('canAccessPanel', function (): void {
    it('grants admin panel access to super-admin', function (): void {
        $user = User::factory()->superAdmin()->create();

        expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
    });

    it('grants admin panel access to admin', function (): void {
        $user = User::factory()->admin()->create();

        expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
    });

    it('denies admin panel access to caregivers', function (): void {
        $user = User::factory()->role(Roles::Caregiver)->create();

        expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
    });

    it('denies admin panel access to users with no roles', function (): void {
        $user = User::factory()->create();

        expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
    });
});
