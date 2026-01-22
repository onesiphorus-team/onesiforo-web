# Research: OnesiBox Management

**Branch**: `008-onesibox-management` | **Date**: 2026-01-22

## Token Management with Sanctum

### Decision: Use Laravel Sanctum for device tokens

**Rationale**: The OnesiBox model already has the `HasApiTokens` trait from Sanctum configured. Sanctum provides a simple, lightweight token system that's perfect for device authentication.

**Alternatives considered**:
- Laravel Passport: Overkill for device-to-server authentication, adds OAuth complexity not needed
- Custom token system: Would duplicate Sanctum functionality without benefit

### Key Sanctum APIs

```php
// Create token with expiration (1 year default per spec)
$token = $onesiBox->createToken(
    'onesibox-api-token',
    ['*'],  // Full access abilities
    now()->addYear()
);

// Access plain text token (only available once)
$plainTextToken = $token->plainTextToken;

// List all tokens
$onesiBox->tokens;

// Revoke a specific token
$token->delete();

// Check last_used_at on token
$token->last_used_at;
```

### Token Table Structure (personal_access_tokens)

Already exists in the database:
- `id`: Primary key
- `tokenable_type`: Polymorphic type (App\Models\OnesiBox)
- `tokenable_id`: OnesiBox ID
- `name`: Token name/description
- `token`: Hashed token (SHA-256)
- `abilities`: JSON array of abilities
- `last_used_at`: Timestamp of last API call
- `expires_at`: Token expiration timestamp
- `created_at`, `updated_at`: Standard timestamps

## Filament Relation Manager Pattern

### Decision: Create a dedicated TokensRelationManager

**Rationale**: Filament 5 relation managers provide a clean way to manage related records with full table/action support. The `tokens` polymorphic relationship from Sanctum works with Filament's `MorphMany` relation manager pattern.

**Alternatives considered**:
- Inline action on table (current implementation): Limited UI, no list view of tokens
- Custom page: Requires more code, less integration with resource

### Relation Manager Structure

```php
// app/Filament/Resources/OnesiBoxes/RelationManagers/TokensRelationManager.php
class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    public function table(Table $table): Table
    {
        return $table
            ->columns([...])
            ->headerActions([
                // Generate token action
            ])
            ->recordActions([
                // Revoke action per token
            ]);
    }
}
```

### Token Generation Modal Pattern

Filament Actions support custom modals with success actions. The pattern for showing a one-time value:

```php
Action::make('generate_token')
    ->action(function (OnesiBox $record, Action $action) {
        $token = $record->createToken(...);

        // Show modal with token using successNotificationBody or custom modal
        $action->successNotificationBody(
            "Token generated. Copy it now - it won't be shown again: {$token->plainTextToken}"
        );
    })
    ->successNotification(
        Notification::make()
            ->persistent()
            ->success()
    );
```

For better UX with clipboard copy, use a custom modal form:

```php
Action::make('generate_token')
    ->action(function (OnesiBox $record, Action $action) {
        $token = $record->createToken('onesibox-api-token', ['*'], now()->addYear());

        $action->halt();  // Prevent closing

        // Store for modal display
        $this->generatedToken = $token->plainTextToken;
    })
    ->modalContent(fn () => view('filament.modals.token-display', [
        'token' => $this->generatedToken,
    ]));
```

## Form Enhancement for Recipient

### Decision: Use Select with createOptionForm for inline recipient creation

**Rationale**: Filament's Select component supports `createOptionForm()` which allows creating a new recipient directly from the OnesiBox form without leaving the page.

**Alternatives considered**:
- Separate recipient creation step: More clicks, worse UX
- Wizard form: Adds complexity, not needed for this simple relationship

### Implementation Pattern

```php
Select::make('recipient_id')
    ->relationship('recipient', 'first_name')
    ->getOptionLabelFromRecordUsing(fn (Recipient $record) => $record->full_name)
    ->searchable(['first_name', 'last_name'])
    ->preload()
    ->createOptionForm([
        TextInput::make('first_name')->required(),
        TextInput::make('last_name')->required(),
        TextInput::make('phone'),
        // ... other recipient fields
    ])
    ->createOptionUsing(function (array $data) {
        return Recipient::create($data)->id;
    });
```

## Activity Logging

### Decision: Use existing spatie/laravel-activitylog

**Rationale**: Already configured in the application. The `LogsActivityAllDirty` trait is used on OnesiBox model. Token events can be logged with `activity()` helper.

**Pattern**:
```php
activity()
    ->performedOn($onesiBox)
    ->causedBy(auth()->user())
    ->withProperties(['token_name' => $tokenName])
    ->log('API token generated');

activity()
    ->performedOn($onesiBox)
    ->causedBy(auth()->user())
    ->withProperties(['token_id' => $tokenId])
    ->log('API token revoked');
```

## Testing Patterns

### Decision: Follow existing Pest + Livewire testing patterns

**Rationale**: Consistent with existing tests in `tests/Feature/Filament/UserResourceTest.php`.

**Key patterns from codebase**:
```php
// Setup with roles
beforeEach(function () {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

// Test relation manager
livewire(TokensRelationManager::class, [
    'ownerRecord' => $onesiBox,
    'pageClass' => EditOnesiBox::class,
])
    ->assertOk()
    ->assertCanSeeTableRecords($onesiBox->tokens);

// Test header action
livewire(TokensRelationManager::class, [
    'ownerRecord' => $onesiBox,
    'pageClass' => EditOnesiBox::class,
])
    ->callAction('generate_token')
    ->assertNotified();
```

## Summary of Decisions

| Topic | Decision | Key Reason |
|-------|----------|------------|
| Token system | Laravel Sanctum | Already configured, lightweight |
| Token UI | Relation Manager | Clean integration, full CRUD support |
| Token display | Modal with clipboard | Better UX for one-time display |
| Recipient creation | Select with createOptionForm | Inline creation without navigation |
| Activity logging | spatie/laravel-activitylog | Already configured in app |
| Testing | Pest + Livewire | Consistent with codebase patterns |
