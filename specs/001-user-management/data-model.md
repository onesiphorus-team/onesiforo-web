# Data Model: Gestione Utenti e Ruoli

**Feature**: 001-user-management
**Date**: 2026-01-21

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────┐
│                   users                      │
├─────────────────────────────────────────────┤
│ id              : bigint (PK)               │
│ name            : varchar(255)              │
│ email           : varchar(255) UNIQUE       │
│ email_verified_at: timestamp NULL           │
│ password        : varchar(255)              │
│ remember_token  : varchar(100) NULL         │
│ two_factor_secret: text NULL                │
│ two_factor_recovery_codes: text NULL        │
│ two_factor_confirmed_at: timestamp NULL     │
│ last_login_at   : timestamp NULL  [NEW]     │
│ created_at      : timestamp                 │
│ updated_at      : timestamp                 │
│ deleted_at      : timestamp NULL  [NEW]     │
└─────────────────────────────────────────────┘
          │
          │ many-to-many
          ▼
┌─────────────────────────────────────────────┐
│                 role_user                    │
├─────────────────────────────────────────────┤
│ id              : bigint (PK)               │
│ role_id         : bigint (FK → roles.id)    │
│ user_id         : bigint (FK → users.id)    │
│ created_at      : timestamp                 │
│ updated_at      : timestamp                 │
└─────────────────────────────────────────────┘
          │
          │
          ▼
┌─────────────────────────────────────────────┐
│                   roles                      │
├─────────────────────────────────────────────┤
│ id              : bigint (PK)               │
│ name            : varchar(255) UNIQUE       │
│ created_at      : timestamp                 │
│ updated_at      : timestamp                 │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│               activity_log                   │
├─────────────────────────────────────────────┤
│ id              : bigint (PK)               │
│ log_name        : varchar(255) NULL         │
│ description     : text                      │
│ subject_type    : varchar(255) NULL         │
│ subject_id      : bigint NULL               │
│ causer_type     : varchar(255) NULL         │
│ causer_id       : bigint NULL               │
│ properties      : json NULL                 │
│ event           : varchar(255) NULL         │
│ batch_uuid      : varchar(36) NULL          │
│ created_at      : timestamp                 │
│ updated_at      : timestamp                 │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│           password_reset_tokens              │
├─────────────────────────────────────────────┤
│ email           : varchar(255) (PK)         │
│ token           : varchar(255)              │
│ created_at      : timestamp NULL            │
└─────────────────────────────────────────────┘
```

## Entities

### User (Esistente - Da Estendere)

**Tabella**: `users`

| Campo | Tipo | Nullable | Default | Note |
|-------|------|----------|---------|------|
| id | bigint | No | auto | Primary Key |
| name | varchar(255) | No | - | Nome completo |
| email | varchar(255) | No | - | Unique |
| email_verified_at | timestamp | Yes | NULL | Data verifica email |
| password | varchar(255) | No | - | Hash bcrypt/argon2 |
| remember_token | varchar(100) | Yes | NULL | Token "remember me" |
| two_factor_secret | text | Yes | NULL | Encrypted TOTP secret |
| two_factor_recovery_codes | text | Yes | NULL | Encrypted recovery codes |
| two_factor_confirmed_at | timestamp | Yes | NULL | Data conferma 2FA |
| **last_login_at** | timestamp | Yes | NULL | **[NEW]** Ultimo accesso |
| created_at | timestamp | No | now() | Data creazione |
| updated_at | timestamp | No | now() | Data modifica |
| **deleted_at** | timestamp | Yes | NULL | **[NEW]** Soft delete |

**Traits**:
- `HasFactory`
- `Notifiable`
- `TwoFactorAuthenticatable` (Fortify)
- `HasRoles` (oltrematica/role-lite)
- `LogsActivityAllDirty` (spatie/activitylog)
- `SoftDeletes` **[NEW]**

**Relazioni**:
- `roles()`: BelongsToMany → Role (via role_user)
- `activities()`: MorphMany → Activity (come subject)
- `causedActivities()`: MorphMany → Activity (come causer)

**Validation Rules**:
- `name`: required, string, max:255
- `email`: required, email, unique:users,email
- `password`: required, min:8, confirmed (solo creazione/modifica)

---

### Role (Esistente - oltrematica/role-lite)

**Tabella**: `roles`

| Campo | Tipo | Nullable | Default | Note |
|-------|------|----------|---------|------|
| id | bigint | No | auto | Primary Key |
| name | varchar(255) | No | - | Unique, es: "super-admin" |
| created_at | timestamp | No | now() | |
| updated_at | timestamp | No | now() | |

**Valori Predefiniti** (da creare via Seeder):
- `super-admin` - Accesso completo, può gestire tutti i ruoli
- `admin` - Accesso pannello, può gestire solo caregiver
- `caregiver` - Nessun accesso al pannello admin

**Relazioni**:
- `users()`: BelongsToMany → User (via role_user)

---

### Activity (Esistente - spatie/laravel-activitylog)

**Tabella**: `activity_log`

| Campo | Tipo | Nullable | Default | Note |
|-------|------|----------|---------|------|
| id | bigint | No | auto | Primary Key |
| log_name | varchar(255) | Yes | "default" | Nome del log |
| description | text | No | - | Descrizione azione |
| subject_type | varchar(255) | Yes | NULL | Morphable type |
| subject_id | bigint | Yes | NULL | Morphable id |
| causer_type | varchar(255) | Yes | NULL | Chi ha causato l'azione |
| causer_id | bigint | Yes | NULL | ID di chi ha causato |
| properties | json | Yes | NULL | Dati aggiuntivi (old/new values) |
| event | varchar(255) | Yes | NULL | created/updated/deleted |
| batch_uuid | varchar(36) | Yes | NULL | Per raggruppare azioni |
| created_at | timestamp | No | now() | |
| updated_at | timestamp | No | now() | |

**Indici**:
- `log_name` - Per filtro per tipo log
- `(subject_type, subject_id)` - Per query su soggetto
- `(causer_type, causer_id)` - Per query su causante

---

## Migrations Required

### 1. Add last_login_at and soft deletes to users

```php
// database/migrations/xxxx_xx_xx_add_last_login_and_soft_deletes_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('last_login_at')->nullable()->after('two_factor_confirmed_at');
    $table->softDeletes();
});
```

### 2. Seed default roles (if not exists)

```php
// database/seeders/RoleSeeder.php
$roles = ['super-admin', 'admin', 'caregiver'];
foreach ($roles as $roleName) {
    Role::firstOrCreate(['name' => $roleName]);
}
```

---

## State Transitions

### User States

```
                    ┌─────────────┐
                    │   INVITED   │
                    │ (no password)│
                    └──────┬──────┘
                           │ sets password
                           ▼
                    ┌─────────────┐
                    │  UNVERIFIED │
                    │ (email_verified_at = null)
                    └──────┬──────┘
                           │ verifies email
                           ▼
                    ┌─────────────┐
                    │   ACTIVE    │
                    │ (verified)  │
                    └──────┬──────┘
                           │ soft delete
                           ▼
                    ┌─────────────┐
                    │   TRASHED   │◄───┐
                    │ (deleted_at)│    │ restore
                    └──────┬──────┘────┘
                           │ force delete
                           ▼
                    ┌─────────────┐
                    │   DELETED   │
                    │ (permanent) │
                    └─────────────┘
```

### User Lifecycle Events (Activity Log)

| Evento | Log Name | Description | Properties |
|--------|----------|-------------|------------|
| created | default | "User created" | {attributes: {...}} |
| updated | default | "User updated" | {old: {...}, attributes: {...}} |
| deleted | default | "User soft deleted" | {old: {...}} |
| restored | default | "User restored" | {} |
| forceDeleted | default | "User permanently deleted" | {old: {...}} |
| role_assigned | roles | "Role assigned" | {role: "admin"} |
| role_removed | roles | "Role removed" | {role: "caregiver"} |
| invited | users | "User invited" | {invited_by: id, role: "caregiver"} |
| password_reset_sent | users | "Password reset sent" | {requested_by: id} |
| verification_email_sent | users | "Verification email sent" | {requested_by: id} |

---

## Access Control Matrix

| Azione | super-admin | admin | caregiver |
|--------|:-----------:|:-----:|:---------:|
| Accesso panel Filament | ✅ | ✅ | ❌ |
| Visualizza lista utenti | ✅ | ✅ | ❌ |
| Crea utente (invito) | ✅ | ✅ | ❌ |
| Modifica dati utente | ✅ | ✅ | ❌ |
| Assegna ruolo super-admin | ✅ | ❌ | ❌ |
| Assegna ruolo admin | ✅ | ❌ | ❌ |
| Assegna ruolo caregiver | ✅ | ✅ | ❌ |
| Soft delete utente | ✅ | ❌ | ❌ |
| Force delete utente | ✅ | ❌ | ❌ |
| Restore utente | ✅ | ❌ | ❌ |
| Invia reset password | ✅ | ✅ | ❌ |
| Invia verifica email | ✅ | ✅ | ❌ |
| Visualizza activity log | ✅ | ✅ | ❌ |

---

## Constraints

1. **Almeno un super-admin attivo**: Il sistema deve sempre avere almeno un utente con ruolo super-admin non eliminato.

2. **Non auto-eliminazione**: Un utente non può eliminare sé stesso (né soft né force delete).

3. **Email unica**: L'email deve essere univoca, anche considerando utenti soft-deleted (per evitare conflitti al restore).

4. **Ruoli gerarchici**: Admin non può modificare/assegnare ruoli di pari livello o superiore.
