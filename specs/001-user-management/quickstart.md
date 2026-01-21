# Quickstart: Gestione Utenti e Ruoli

**Feature**: 001-user-management
**Date**: 2026-01-21

## Prerequisites

- PHP 8.4+
- Laravel 12.x installato
- Filament 5.x configurato
- Database SQLite/MySQL/PostgreSQL
- Mail configurata (per inviti e reset password)

## Setup Rapido

### 1. Eseguire le migrazioni

```bash
php artisan migrate
```

### 2. Eseguire il seeder dei ruoli

```bash
php artisan db:seed --class=RoleSeeder
```

### 3. Creare il primo super-admin

```bash
php artisan app:create-super-admin
```

Verranno richiesti:
- Nome completo
- Email
- Password

### 4. Accedere al pannello admin

Visitare `/admin` e accedere con le credenziali del super-admin.

---

## Componenti Principali

### Comando Artisan

| Comando | Descrizione |
|---------|-------------|
| `php artisan app:create-super-admin` | Crea un nuovo utente super-admin |

### Filament Resources

| Resource | Path | Descrizione |
|----------|------|-------------|
| UserResource | `/admin/users` | Gestione completa utenti |
| ActivityResource | `/admin/activities` | Visualizzazione activity log |

### Actions Disponibili

| Action | Tipo | Descrizione |
|--------|------|-------------|
| Invita Utente | Header | Invita nuovo utente via email |
| Modifica | Record | Modifica dati utente |
| Invia Verifica Email | Record | Reinvia email di verifica |
| Invia Reset Password | Record | Invia link reset password |
| Elimina | Record | Soft delete utente |
| Ripristina | Record | Restore utente eliminato |
| Elimina Definitivamente | Record | Force delete utente |

### Colonne Tabella Utenti

| Colonna | Descrizione |
|---------|-------------|
| Nome | Nome completo dell'utente |
| Email | Email con indicatore verifica |
| Ruoli | Badge con ruoli assegnati |
| Ultimo Accesso | Data/ora ultimo login |
| Stato | Online/Offline/Mai connesso |

---

## Testing

### Eseguire tutti i test della feature

```bash
php artisan test --filter=User
```

### Test specifici

```bash
# Test comando creazione super-admin
php artisan test tests/Feature/Commands/CreateSuperAdminCommandTest.php

# Test UserResource Filament
php artisan test tests/Feature/Filament/UserResourceTest.php

# Test Policy autorizzazioni
php artisan test tests/Feature/Filament/UserPolicyTest.php

# Test invito utenti
php artisan test tests/Feature/Filament/UserInviteActionTest.php

# Test activity log
php artisan test tests/Feature/Filament/ActivityLogTest.php
```

---

## Configurazione

### Scadenza Token

Configurare in `config/auth.php`:

```php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60, // minuti per reset password
        'throttle' => 60,
    ],
],
```

### Activity Log

Configurare in `config/activitylog.php`:

```php
'default_log_name' => 'default',
'subject_returns_soft_deleted_models' => true,
```

---

## Troubleshooting

### "Accesso negato" per utente admin

Verificare che l'utente abbia il ruolo `admin` o `super-admin`:

```bash
php artisan tinker
>>> User::find(1)->roles->pluck('name')
```

### Email non inviate

1. Verificare configurazione mail in `.env`
2. Controllare coda: `php artisan queue:work`
3. Verificare log: `storage/logs/laravel.log`

### Utente non può essere eliminato

Possibili cause:
- È l'unico super-admin rimasto
- Stai tentando di eliminare te stesso
- Non hai il ruolo super-admin

---

## Permessi per Ruolo

| Ruolo | Descrizione |
|-------|-------------|
| `super-admin` | Accesso completo, può gestire tutti i ruoli e eliminare utenti |
| `admin` | Può gestire utenti e assegnare solo ruolo caregiver |
| `caregiver` | Nessun accesso al pannello admin, solo area caregiver |
