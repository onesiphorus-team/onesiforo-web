<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Oltrematica\RoleLite\Models\Role;

beforeEach(function (): void {
    Cache::flush();
    Process::fake(['*' => Process::result(output: 'v9.8.7')]);
});

it('shows the version on the login page', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeInOrder(['data-slot="app-version"', 'v9.8.7'], escape: false);
});

it('shows the version to a signed-in caregiver at the foot of the menu', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('dashboard'))->assertOk();

    expect(substr_count($response->getContent(), 'data-slot="app-version"'))->toBe(1);
    $response->assertSee('v9.8.7');
});

it('shows the version in the footer of the admin panel', function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSeeInOrder(['data-slot="app-version"', 'v9.8.7'], escape: false);
});

it('still renders pages when the version cannot be resolved', function (): void {
    Process::fake(['*' => Process::result(exitCode: 128)]);

    $response = $this->get(route('login'))->assertOk();

    expect(preg_match('/data-slot="app-version"[^>]*>\s*([^<\s]+)\s*</', $response->getContent(), $matches))->toBe(1)
        ->and($matches[1])->toBe('dev');
});
