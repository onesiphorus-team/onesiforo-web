<?php

declare(strict_types=1);

use App\Filament\Resources\Congregations\Pages\CreateCongregation;
use App\Filament\Resources\Congregations\Pages\EditCongregation;
use App\Filament\Resources\Congregations\Pages\ListCongregations;
use App\Models\Congregation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

it('can list congregations', function (): void {
    $congregations = Congregation::factory()->count(3)->create();

    livewire(ListCongregations::class)
        ->assertCanSeeTableRecords($congregations);
});

it('can create a congregation', function (): void {
    livewire(CreateCongregation::class)
        ->fillForm([
            'name' => 'Congregazione Test',
            'zoom_url' => 'https://us05web.zoom.us/j/1234567890?pwd=abc123',
            'midweek_day' => 3,
            'midweek_time' => '19:00',
            'weekend_day' => 0,
            'weekend_time' => '10:00',
            'timezone' => 'Europe/Rome',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Congregation::query()->count())->toBe(1);
    expect(Congregation::query()->first()->name)->toBe('Congregazione Test');
});

it('validates zoom url format', function (): void {
    livewire(CreateCongregation::class)
        ->fillForm([
            'name' => 'Test',
            'zoom_url' => 'https://example.com/not-a-zoom-url',
            'midweek_day' => 3,
            'midweek_time' => '19:00',
            'weekend_day' => 0,
            'weekend_time' => '10:00',
        ])
        ->call('create')
        ->assertHasFormErrors(['zoom_url']);
});

it('can edit a congregation', function (): void {
    $congregation = Congregation::factory()->create();

    livewire(EditCongregation::class, ['record' => $congregation->getRouteKey()])
        ->fillForm(['name' => 'Updated Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($congregation->fresh()->name)->toBe('Updated Name');
});

it('can deactivate a congregation', function (): void {
    $congregation = Congregation::factory()->create(['is_active' => true]);

    livewire(EditCongregation::class, ['record' => $congregation->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($congregation->fresh()->is_active)->toBeFalse();
});
