# Quickstart: OnesiBox Management

**Branch**: `008-onesibox-management` | **Date**: 2026-01-22

## Prerequisites

- PHP 8.4+
- Composer dependencies installed
- Database migrated
- Super Admin or Admin user account

## Development Setup

```bash
# Ensure you're on the feature branch
git checkout 008-onesibox-management

# Install dependencies (if needed)
composer install

# Run migrations (if needed)
php artisan migrate

# Start development server
composer run dev
# Or: php artisan serve + npm run dev
```

## Key Files to Implement

### 1. TokensRelationManager (new)

```
app/Filament/Resources/OnesiBoxes/RelationManagers/TokensRelationManager.php
```

Create using artisan:
```bash
php artisan make:filament-relation-manager OnesiBoxResource tokens name --no-interaction
```

Then customize with:
- Table columns: name, created_at, last_used_at, expires_at
- Header action: Generate Token (with modal)
- Row action: Revoke (with confirmation)

### 2. OnesiBoxForm Enhancement

```
app/Filament/Resources/OnesiBoxes/Schemas/OnesiBoxForm.php
```

Add `createOptionForm()` to recipient Select for inline recipient creation.

### 3. OnesiBoxResource Update

```
app/Filament/Resources/OnesiBoxes/OnesiBoxResource.php
```

Register the TokensRelationManager in `getRelations()`.

### 4. OnesiBoxesTable Cleanup

```
app/Filament/Resources/OnesiBoxes/Tables/OnesiBoxesTable.php
```

Remove the `generateTokenAction()` from table row actions (moved to relation manager).

### 5. GenerateOnesiBoxToken Action (optional)

```
app/Actions/GenerateOnesiBoxToken.php
```

Extract token generation logic for testability (invokable action class).

## Testing

```bash
# Run all tests
php artisan test --compact

# Run specific feature tests
php artisan test --compact tests/Feature/Filament/OnesiBoxResourceTest.php
php artisan test --compact tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php

# Run with filter
php artisan test --compact --filter="OnesiBox"
```

## Manual Testing Checklist

1. **Create OnesiBox with existing recipient**
   - Navigate to OnesiBox → Create
   - Fill device info
   - Select existing recipient
   - Submit and verify

2. **Create OnesiBox with new recipient**
   - Navigate to OnesiBox → Create
   - Fill device info
   - Click "Create New" on recipient select
   - Fill recipient info in modal
   - Submit and verify both records created

3. **Generate token**
   - Navigate to OnesiBox → Edit (existing record)
   - Click "Authentication Tokens" tab/section
   - Click "Generate Token" action
   - Verify modal shows plain text token
   - Copy token and close modal
   - Verify token appears in list (hashed)

4. **View token details**
   - Check token list shows: name, created date, last used, expiration
   - Verify "Never" shows for unused tokens

5. **Revoke token**
   - Click revoke action on a token
   - Confirm in dialog
   - Verify token removed from list

6. **Form validation**
   - Submit with empty required fields → errors shown
   - Submit with duplicate serial number → error shown
   - Submit with invalid phone format → error shown

## Activity Log Verification

After token operations, check activity log:

```bash
php artisan tinker
>>> \Spatie\Activitylog\Models\Activity::latest()->take(5)->get()
```

Should show:
- "API token generated" events
- "API token revoked" events

## Troubleshooting

### Token not showing in modal
- Check that `plainTextToken` is being stored before modal display
- Verify modal content view is correctly bound

### Relation manager not appearing
- Ensure `TokensRelationManager` is registered in `OnesiBoxResource::getRelations()`
- Check that you're on the Edit page (not Create)

### Validation errors not showing
- Verify form field names match model fillable
- Check Laravel validation rules syntax
