<?php

declare(strict_types=1);

use App\Models\User;

describe('pages load without errors', function (): void {
    it('redirects home to login', function (): void {
        $page = visit('/');

        $page->assertPathIs('/login')
            ->assertNoJavaScriptErrors();
    });

    it('loads login page', function (): void {
        $page = visit('/login');

        $page->assertPathIs('/login')
            ->assertSee('Log in')
            ->assertNoJavaScriptErrors();
    });

    it('loads register page', function (): void {
        $page = visit('/register');

        $page->assertPathIs('/register')
            ->assertSee('Create an account')
            ->assertNoJavaScriptErrors();
    });

    it('loads forgot password page', function (): void {
        $page = visit('/forgot-password');

        $page->assertPathIs('/forgot-password')
            ->assertNoJavaScriptErrors();
    });

    it('redirects to login when accessing admin as guest', function (): void {
        $page = visit('/admin');

        $page->assertPathIs('/login')
            ->assertNoJavaScriptErrors();
    });
});

describe('authenticated pages', function (): void {
    it('loads dashboard when authenticated', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertPathIs('/dashboard')
            ->assertNoJavaScriptErrors();

        $this->assertAuthenticated();
    });

    it('redirects to login when accessing dashboard as guest', function (): void {
        $page = visit('/dashboard');

        $page->assertPathIs('/login')
            ->assertNoJavaScriptErrors();
    });
});

describe('login functionality', function (): void {
    it('can login through fortify login page', function (): void {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $page = visit('/login');

        $page->fill('email', 'test@example.com')
            ->fill('password', 'password')
            ->click('Log in')
            ->assertPathIs('/dashboard')
            ->assertNoJavaScriptErrors();

        $this->assertAuthenticated();
    });

    it('can access filament admin when authenticated with admin role', function (): void {
        Oltrematica\RoleLite\Models\Role::query()->firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user);

        $page = visit('/admin');

        $page->assertPathIs('/admin')
            ->assertNoJavaScriptErrors();

        $this->assertAuthenticated();
    });
});
