# Research: Gestione Utenti e Ruoli

**Feature**: 001-user-management
**Date**: 2026-01-21

## 1. Filament UserResource con Soft Deletes

### Decision
Utilizzare `php artisan make:filament-resource User --soft-deletes` per generare una risorsa con supporto soft-delete integrato.

### Rationale
Filament v5 ha supporto nativo per soft-deletes che include:
- `TrashedFilter` per filtrare record eliminati
- `DeleteAction`, `ForceDeleteAction`, `RestoreAction` per le azioni singole
- `DeleteBulkAction`, `ForceDeleteBulkAction`, `RestoreBulkAction` per azioni bulk
- Override di `getRecordRouteBindingEloquentQuery()` per escludere `SoftDeletingScope`

### Alternatives Considered
1. **Implementazione manuale**: Scartata perché Filament fornisce già tutto il necessario
2. **Package esterno per soft-delete UI**: Non necessario, Filament lo gestisce nativamente

### Implementation Notes
```php
// Nel Resource
public static function getRecordRouteBindingEloquentQuery(): Builder
{
    return parent::getRecordRouteBindingEloquentQuery()
        ->withoutGlobalScopes([SoftDeletingScope::class]);
}

// Nella table
->filters([TrashedFilter::make()])
->recordActions([
    DeleteAction::make(),
    ForceDeleteAction::make(),
    RestoreAction::make(),
])
```

---

## 2. Autorizzazione con Policy

### Decision
Creare `UserPolicy` con metodi: `viewAny`, `view`, `create`, `update`, `delete`, `forceDelete`, `restore`.

### Rationale
Filament legge automaticamente le policy per autorizzare le azioni. La policy centralizza la logica di autorizzazione basata sui ruoli:
- `forceDelete()` e `restore()` solo per super-admin
- `delete()` solo per super-admin
- Admin non può modificare ruoli admin/super-admin

### Alternatives Considered
1. **Gates Laravel**: Meno strutturato, preferibile Policy per model-specific authorization
2. **Middleware personalizzato**: Troppo granulare, la Policy è più appropriata

### Implementation Notes
```php
public function delete(User $user, User $model): bool
{
    // Solo super-admin può eliminare
    if (! $user->hasRole('super-admin')) {
        return false;
    }
    // Non può eliminare sé stesso
    return $user->id !== $model->id;
}

public function forceDelete(User $user, User $model): bool
{
    return $user->hasRole('super-admin') && $user->id !== $model->id;
}
```

---

## 3. Restrizione Accesso Panel per Ruolo

### Decision
Utilizzare il metodo `->authMiddleware()` con un middleware custom che verifica i ruoli, oppure `->canAccess()` nel PanelProvider.

### Rationale
Filament v5 supporta `->canAccess()` callback nel PanelProvider per controllare chi può accedere al panel.

### Alternatives Considered
1. **Gate nel middleware**: Funziona ma meno elegante
2. **Policy su Panel**: Non esiste questo concetto in Filament

### Implementation Notes
```php
// In AdminPanelServiceProvider
->canAccess(fn (): bool => auth()->user()?->hasAnyRole(['super-admin', 'admin']) ?? false)
```

---

## 4. Header Actions per Invito Utenti

### Decision
Creare una Header Action custom nella ListUsers page per invitare nuovi utenti.

### Rationale
Le Header Actions in Filament sono ideali per azioni non legate a un record specifico. L'invito utente richiede un form modale con:
- Nome, Cognome, Email
- Selezione ruolo (filtrata in base al ruolo dell'utente corrente)

### Implementation Notes
```php
protected function getHeaderActions(): array
{
    return [
        Action::make('inviteUser')
            ->label('Invita Utente')
            ->form([
                TextInput::make('first_name')->required(),
                TextInput::make('last_name')->required(),
                TextInput::make('email')->email()->required(),
                Select::make('role')
                    ->options(fn () => $this->getAvailableRoles())
                    ->required(),
            ])
            ->action(fn (array $data) => $this->inviteUser($data)),
    ];
}
```

---

## 5. Invio Reset Password da Admin

### Decision
Utilizzare `Password::sendResetLink()` facade di Laravel per inviare link di reset password.

### Rationale
Laravel fornisce già il Password Broker che:
- Genera token sicuri
- Gestisce la scadenza (configurabile in `config/auth.php`)
- Invia notifica via mail

### Alternatives Considered
1. **Implementazione custom token**: Inutile, Laravel lo gestisce già
2. **Link di invito separato**: Per gli inviti useremo un approccio simile ma con Notification custom

### Implementation Notes
```php
// Action per reset password
Action::make('sendPasswordReset')
    ->action(function (User $record) {
        Password::sendResetLink(['email' => $record->email]);
        Notification::make()->title('Email inviata')->success()->send();
    })
```

---

## 6. Invio Email Verifica

### Decision
Utilizzare `$user->sendEmailVerificationNotification()` di Laravel per reinviare l'email di verifica.

### Rationale
Laravel MustVerifyEmail trait fornisce già questo metodo. L'utente deve implementare l'interfaccia `MustVerifyEmail`.

### Implementation Notes
```php
Action::make('resendVerification')
    ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail())
    ->action(function (User $record) {
        $record->sendEmailVerificationNotification();
        Notification::make()->title('Email di verifica inviata')->success()->send();
    })
```

---

## 7. Activity Log con Spatie

### Decision
Utilizzare il trait `LogsActivity` esistente (`LogsActivityAllDirty`) già presente nel model User.

### Rationale
Il progetto ha già `spatie/laravel-activitylog` configurato con tabella `activity_log`. Il trait `LogsActivityAllDirty` logga automaticamente tutte le modifiche ai campi dirty.

### Implementation Notes
Per la visualizzazione, creare una pagina custom o una risorsa separata per Activity:
```php
// Risorsa ActivityResource oppure pagina custom
Activity::query()
    ->where('subject_type', User::class)
    ->latest()
    ->paginate();
```

---

## 8. Comando Artisan Create Super Admin

### Decision
Creare comando `app:create-super-admin` con prompts per email e password.

### Rationale
Laravel Prompts (già installato) fornisce UI elegante per comandi interattivi. Il comando deve:
- Validare email unica
- Hashare password
- Assegnare ruolo super-admin
- Marcare email come verificata

### Implementation Notes
```php
public function handle(): int
{
    $email = text('Email:', required: true, validate: ['email', 'unique:users']);
    $password = password('Password:', required: true);

    $user = User::create([
        'name' => text('Nome completo:'),
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
    ]);

    $user->assignRole('super-admin');

    $this->info("Super admin {$email} creato con successo!");
    return Command::SUCCESS;
}
```

---

## 9. Migrazione per last_login_at

### Decision
Aggiungere colonna `last_login_at` alla tabella users tramite migrazione.

### Rationale
Per tracciare l'ultimo accesso, serve una colonna dedicata. Il valore verrà aggiornato via Listener sull'evento `Login`.

### Implementation Notes
```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('last_login_at')->nullable();
});

// Listener
Event::listen(Login::class, function (Login $event) {
    $event->user->update(['last_login_at' => now()]);
});
```

---

## 10. Gestione Ruoli nel Form

### Decision
Utilizzare `CheckboxList` o `Select` multiplo per assegnare ruoli, filtrando le opzioni in base al ruolo dell'utente corrente.

### Rationale
oltrematica/role-lite usa relazione many-to-many. L'admin può assegnare solo `caregiver`, il super-admin tutti i ruoli.

### Implementation Notes
```php
CheckboxList::make('roles')
    ->relationship('roles', 'name')
    ->options(function () {
        $user = auth()->user();
        if ($user->hasRole('super-admin')) {
            return Role::pluck('name', 'id');
        }
        return Role::where('name', 'caregiver')->pluck('name', 'id');
    })
    ->disabled(fn () => ! auth()->user()->hasAnyRole(['super-admin', 'admin']))
```

---

## Summary

| Topic | Decision | Key Package/Feature |
|-------|----------|---------------------|
| Soft Deletes | Filament native support | `--soft-deletes` flag |
| Authorization | UserPolicy con metodi standard | Laravel Policy + Filament auto-detection |
| Panel Access | `->canAccess()` callback | AdminPanelServiceProvider |
| Invite Users | Header Action con form modale | Filament Actions |
| Reset Password | `Password::sendResetLink()` | Laravel Password Broker |
| Email Verification | `sendEmailVerificationNotification()` | Laravel MustVerifyEmail |
| Activity Log | Trait esistente + vista custom | spatie/laravel-activitylog |
| Create Super Admin | Comando artisan con Prompts | Laravel Prompts |
| Last Login | Migrazione + Event Listener | Login event |
| Role Management | CheckboxList filtrato per ruolo | oltrematica/role-lite |
