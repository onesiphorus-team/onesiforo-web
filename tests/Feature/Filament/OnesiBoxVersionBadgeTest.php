<?php

declare(strict_types=1);

use App\Filament\Resources\OnesiBoxes\Pages\ListOnesiBoxes;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

it('displays app_version column in table', function () {
    $box = OnesiBox::factory()->create(['app_version' => '1.2.0']);

    livewire(ListOnesiBoxes::class)
        ->assertCanSeeTableRecords([$box]);
});
