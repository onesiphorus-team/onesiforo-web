<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    // Ensure roles exist
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);

    // Create and login as super-admin
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

it('can render the user list page', function (): void {
    $this->get(UserResource::getUrl('index'))
        ->assertSuccessful();
});

it('can list users', function (): void {
    $users = User::factory()->count(3)->create();

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

it('displays name column', function (): void {
    $user = User::factory()->create(['name' => 'Test User Name']);

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertTableColumnExists('name');
});

it('displays email column', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertTableColumnExists('email');
});

it('displays roles column', function (): void {
    $user = User::factory()->create();
    $user->assignRole('admin');

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertTableColumnExists('roles.name');
});

it('displays last_login_at column', function (): void {
    $user = User::factory()->create(['last_login_at' => now()]);

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertTableColumnExists('last_login_at');
});

it('can search users by name', function (): void {
    $searchUser = User::factory()->create(['name' => 'Searchable User']);
    $otherUser = User::factory()->create(['name' => 'Other User']);

    livewire(ListUsers::class)
        ->searchTable('Searchable')
        ->assertCanSeeTableRecords([$searchUser])
        ->assertCanNotSeeTableRecords([$otherUser]);
});

it('can search users by email', function (): void {
    $searchUser = User::factory()->create(['email' => 'searchable@example.com']);
    $otherUser = User::factory()->create(['email' => 'other@example.com']);

    livewire(ListUsers::class)
        ->searchTable('searchable@example')
        ->assertCanSeeTableRecords([$searchUser])
        ->assertCanNotSeeTableRecords([$otherUser]);
});

it('can filter trashed users', function (): void {
    $activeUser = User::factory()->create();
    $trashedUser = User::factory()->create();
    $trashedUser->delete();

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords([$activeUser])
        ->assertCanNotSeeTableRecords([$trashedUser])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$trashedUser]);
});

// Filter Tests
describe('table filters', function (): void {
    it('can filter users by role', function (): void {
        $adminRole = Role::query()->where('name', 'admin')->first();

        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');

        $caregiverUser = User::factory()->create();
        $caregiverUser->assignRole('caregiver');

        livewire(ListUsers::class)
            ->assertCanSeeTableRecords([$adminUser, $caregiverUser])
            ->filterTable('roles', [$adminRole->id])
            ->assertCanSeeTableRecords([$adminUser])
            ->assertCanNotSeeTableRecords([$caregiverUser]);
    });

    it('can filter users by email verification status', function (): void {
        $verifiedUser = User::factory()->create(['email_verified_at' => now()]);
        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);

        livewire(ListUsers::class)
            ->assertCanSeeTableRecords([$verifiedUser, $unverifiedUser])
            ->filterTable('email_verified_at', true)
            ->assertCanSeeTableRecords([$verifiedUser])
            ->assertCanNotSeeTableRecords([$unverifiedUser]);
    });

    it('can filter users by online status', function (): void {
        $onlineUser = User::factory()->create(['last_login_at' => now()]);
        $offlineUser = User::factory()->create(['last_login_at' => now()->subHour()]);
        $neverUser = User::factory()->create(['last_login_at' => null]);

        livewire(ListUsers::class)
            ->filterTable('online_status', 'online')
            ->assertCanSeeTableRecords([$onlineUser])
            ->assertCanNotSeeTableRecords([$offlineUser, $neverUser]);
    });
});

// Edit User Tests (US5)
describe('edit user form', function (): void {
    it('can render the edit page', function (): void {
        $user = User::factory()->create();

        $this->get(UserResource::getUrl('edit', ['record' => $user]))
            ->assertSuccessful();
    });

    it('can retrieve user data in edit form', function (): void {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        livewire(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $user->id])
            ->assertFormSet([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);
    });

    it('can edit user name', function (): void {
        $user = User::factory()->create(['name' => 'Original Name']);

        livewire(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'Updated Name',
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();
        expect($user->name)->toBe('Updated Name');
    });

    it('can edit user email', function (): void {
        $user = User::factory()->create(['email' => 'original@example.com']);

        livewire(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $user->id])
            ->fillForm([
                'email' => 'updated@example.com',
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();
        expect($user->email)->toBe('updated@example.com');
    });

    it('validates email uniqueness excluding current user', function (): void {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        $userToEdit = User::factory()->create(['email' => 'editable@example.com']);

        livewire(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $userToEdit->id])
            ->fillForm([
                'email' => 'existing@example.com',
            ])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);
    });

    it('allows saving with same email (unchanged)', function (): void {
        $user = User::factory()->create(['email' => 'same@example.com']);

        livewire(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'Updated Name',
                'email' => 'same@example.com',
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $user->refresh();
        expect($user->name)->toBe('Updated Name')
            ->and($user->email)->toBe('same@example.com');
    });

    it('validates required fields', function (): void {
        $user = User::factory()->create();

        livewire(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => '',
                'email' => '',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'name' => 'required',
                'email' => 'required',
            ]);
    });

    it('validates email format', function (): void {
        $user = User::factory()->create();

        livewire(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $user->id])
            ->fillForm([
                'email' => 'invalid-email',
            ])
            ->call('save')
            ->assertHasFormErrors(['email' => 'email']);
    });
});

// Email Verification Tests (US7)
describe('resend email verification action', function (): void {
    it('can see resend verification action for unverified user', function (): void {
        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);

        livewire(ListUsers::class)
            ->assertTableActionVisible('resend_verification', $unverifiedUser);
    });

    it('cannot see resend verification action for verified user', function (): void {
        $verifiedUser = User::factory()->create(['email_verified_at' => now()]);

        livewire(ListUsers::class)
            ->assertTableActionHidden('resend_verification', $verifiedUser);
    });

    it('can resend verification email to unverified user', function (): void {
        Illuminate\Support\Facades\Notification::fake();

        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);

        livewire(ListUsers::class)
            ->callTableAction('resend_verification', $unverifiedUser)
            ->assertNotified();

        Illuminate\Support\Facades\Notification::assertSentTo(
            $unverifiedUser,
            Illuminate\Auth\Notifications\VerifyEmail::class
        );
    });
});

// Password Reset Tests (US8)
describe('send password reset action', function (): void {
    it('can see password reset action for users', function (): void {
        $user = User::factory()->create();

        livewire(ListUsers::class)
            ->assertTableActionVisible('send_password_reset', $user);
    });

    it('can send password reset email', function (): void {
        Illuminate\Support\Facades\Notification::fake();

        $user = User::factory()->create();

        livewire(ListUsers::class)
            ->callTableAction('send_password_reset', $user)
            ->assertNotified();

        Illuminate\Support\Facades\Notification::assertSentTo(
            $user,
            Illuminate\Auth\Notifications\ResetPassword::class
        );
    });
});
