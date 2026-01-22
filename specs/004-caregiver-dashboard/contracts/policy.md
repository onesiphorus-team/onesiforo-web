# Authorization Policy Contract

**Feature**: 004-caregiver-dashboard
**Date**: 2026-01-22

## OnesiBoxPolicy

**Purpose**: Controlla l'accesso alle OnesiBox in base all'assegnazione e ai permessi

### Policy Class

```php
namespace App\Policies;

use App\Enums\OnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\User;

class OnesiBoxPolicy
{
    /**
     * Determina se l'utente può visualizzare qualsiasi OnesiBox.
     * (Solo le sue assegnate, gestito via query scope)
     */
    public function viewAny(User $user): bool
    {
        return true; // Tutti gli utenti autenticati possono vedere la lista
    }

    /**
     * Determina se l'utente può visualizzare questa OnesiBox.
     */
    public function view(User $user, OnesiBox $onesiBox): bool
    {
        return $onesiBox->userCanView($user);
    }

    /**
     * Determina se l'utente può inviare comandi a questa OnesiBox.
     * Richiede permesso "Full".
     */
    public function control(User $user, OnesiBox $onesiBox): bool
    {
        return $onesiBox->userHasFullPermission($user);
    }

    /**
     * Determina se l'utente può creare nuove OnesiBox.
     * (Out of scope per questa feature - solo admin via Filament)
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determina se l'utente può modificare questa OnesiBox.
     * (Out of scope per questa feature - solo admin via Filament)
     */
    public function update(User $user, OnesiBox $onesiBox): bool
    {
        return false;
    }

    /**
     * Determina se l'utente può eliminare questa OnesiBox.
     * (Out of scope per questa feature - solo admin via Filament)
     */
    public function delete(User $user, OnesiBox $onesiBox): bool
    {
        return false;
    }
}
```

### Policy Registration

```php
// bootstrap/app.php o AuthServiceProvider

use App\Models\OnesiBox;
use App\Policies\OnesiBoxPolicy;

->withMiddleware(function (Middleware $middleware) {
    // ...
})
->withPolicies([
    OnesiBox::class => OnesiBoxPolicy::class,
])
```

### Usage in Livewire Components

```php
// OnesiBoxDetail.php
public function mount(OnesiBox $onesiBox): void
{
    $this->authorize('view', $onesiBox);
    $this->onesiBox = $onesiBox;
}

// AudioPlayer.php
public function playAudio(): void
{
    $this->authorize('control', $this->onesiBox);
    // ... send command
}
```

### Usage in Blade Templates

```blade
@can('control', $onesiBox)
    <livewire:dashboard.controls.audio-player :onesiBox="$onesiBox" />
@endcan
```

---

## Permission Matrix

| Action | Full Permission | ReadOnly Permission | Not Assigned |
|--------|-----------------|---------------------|--------------|
| View list | ✓ | ✓ | ✗ |
| View detail | ✓ | ✓ | ✗ |
| View recipient contacts | ✓ | ✓ | ✗ |
| Send audio command | ✓ | ✗ | ✗ |
| Send video command | ✓ | ✗ | ✗ |
| Send Zoom command | ✓ | ✗ | ✗ |
| Stop command | ✓ | ✗ | ✗ |

---

## Test Cases

```php
// tests/Feature/Dashboard/AuthorizationTest.php

it('allows caregiver to view assigned onesibox', function () {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => 'read-only']);

    $this->actingAs($user)
        ->get(route('dashboard.show', $onesiBox))
        ->assertOk();
});

it('denies caregiver access to unassigned onesibox', function () {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->create();
    // Not attached

    $this->actingAs($user)
        ->get(route('dashboard.show', $onesiBox))
        ->assertForbidden();
});

it('allows full permission caregiver to send commands', function () {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => 'full']);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->set('audioUrl', 'https://example.com/audio.mp3')
        ->call('playAudio')
        ->assertHasNoErrors();
});

it('denies readonly permission caregiver from sending commands', function () {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => 'read-only']);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class, ['onesiBox' => $onesiBox])
        ->call('playAudio')
        ->assertForbidden();
});
```
