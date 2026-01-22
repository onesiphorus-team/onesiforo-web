# Quickstart: Caregiver Dashboard

**Feature**: 004-caregiver-dashboard
**Date**: 2026-01-22

## Prerequisites

- PHP 8.4+
- Node.js 18+
- SQLite database configured
- Laravel Reverb running (for real-time updates)

## Setup

```bash
# 1. Checkout feature branch
git checkout 004-caregiver-dashboard

# 2. Install dependencies
composer install
npm install

# 3. Run migrations (adds status column to onesi_boxes)
php artisan migrate

# 4. Seed test data (optional)
php artisan db:seed --class=OnesiBoxSeeder

# 5. Start development servers
composer run dev
# oppure manualmente:
# php artisan serve &
# npm run dev &
# php artisan reverb:start &
```

## Key Files

| File | Purpose |
|------|---------|
| `app/Livewire/Dashboard/OnesiBoxList.php` | Lista OnesiBox |
| `app/Livewire/Dashboard/OnesiBoxDetail.php` | Dettaglio e controlli |
| `app/Policies/OnesiBoxPolicy.php` | Autorizzazione |
| `app/Services/OnesiBoxCommandService.php` | Invio comandi |
| `app/Events/OnesiBoxStatusUpdated.php` | Real-time broadcast |

## Routes

| URL | Component | Description |
|-----|-----------|-------------|
| `/dashboard` | OnesiBoxList | Lista OnesiBox caregiver |
| `/dashboard/{onesiBox}` | OnesiBoxDetail | Dettaglio con controlli |

## Testing

```bash
# Run all feature tests
php artisan test --filter=Dashboard

# Run specific test file
php artisan test tests/Feature/Dashboard/OnesiBoxListTest.php

# Run browser tests (requires Chrome)
php artisan test tests/Browser/Dashboard/

# Run with coverage
php artisan test --coverage --filter=Dashboard
```

## Development Workflow

### 1. Creating a new Livewire component

```bash
php artisan make:livewire Dashboard/Controls/NewControl
```

### 2. Testing real-time updates

```php
// In tinker o test
use App\Events\OnesiBoxStatusUpdated;
use App\Models\OnesiBox;

$box = OnesiBox::first();
$box->update(['status' => 'playing']);
OnesiBoxStatusUpdated::dispatch($box);
```

### 3. Testing authorization

```php
// In tinker
use App\Models\User;
use App\Models\OnesiBox;

$user = User::first();
$box = OnesiBox::first();

// Check if user can view
$box->userCanView($user); // true/false

// Check if user can control
$box->userHasFullPermission($user); // true/false
```

## Common Tasks

### Assegnare OnesiBox a un caregiver

```php
$user->onesiBoxes()->attach($onesiBox->id, ['permission' => 'full']);
```

### Cambiare permessi

```php
$user->onesiBoxes()->updateExistingPivot($onesiBox->id, ['permission' => 'read-only']);
```

### Simulare OnesiBox online/offline

```php
// Online
$onesiBox->update(['last_seen_at' => now()]);

// Offline
$onesiBox->update(['last_seen_at' => now()->subMinutes(10)]);
```

## Troubleshooting

### Real-time updates not working

1. Check Reverb is running: `php artisan reverb:start`
2. Check Echo config in `resources/js/echo.js`
3. Check browser console for WebSocket errors
4. Verify channel authorization in `routes/channels.php`

### Authorization errors

1. Check user is attached to OnesiBox: `$user->onesiBoxes`
2. Check permission level: `$user->onesiBoxes()->withPivot('permission')->get()`
3. Verify policy is registered

### Commands not being sent

1. Check queue worker is running: `php artisan queue:work`
2. Check `failed_jobs` table for errors
3. Verify OnesiBox is online: `$onesiBox->isOnline()`

## Architecture Decisions

See [research.md](./research.md) for detailed rationale on:
- Real-time strategy (Echo + Polling fallback)
- Authorization pattern (Laravel Policy)
- Command dispatch (Service + Queued Jobs)
- Mobile-first UI (Flux UI stack)
