# Implementation Plan: Gestione Utenti e Ruoli

**Branch**: `001-user-management` | **Date**: 2026-01-21 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-user-management/spec.md`

## Summary

Implementazione del sistema di gestione utenti per il pannello amministrativo Filament, includendo:
- Comando artisan per creare super-admin
- Risorsa Filament UserResource con CRUD completo
- Sistema di autorizzazione basato su ruoli (super-admin, admin, caregiver)
- Azioni per invito utenti, invio email verifica, reset password
- Integrazione con spatie/laravel-activitylog per audit trail
- Soft-delete con possibilità di force-delete solo per super-admin

## Technical Context

**Language/Version**: PHP 8.4.17
**Framework**: Laravel 12.47.0
**Primary Dependencies**:
- filament/filament v5.0.0 (admin panel)
- laravel/fortify v1.33.0 (auth, 2FA, password reset)
- oltrematica/role-lite (ruoli con trait HasRoles)
- spatie/laravel-activitylog v4.10 (activity logging)
- livewire/livewire v4.0.1 (interattività)

**Storage**: SQLite (development), tabelle esistenti: users, roles, role_user, activity_log, password_reset_tokens
**Testing**: Pest v4.3.1 con supporto browser testing
**Target Platform**: Web application Laravel con Filament admin panel
**Project Type**: Web monolith Laravel
**Performance Goals**: Lista utenti < 2s con 1000 utenti, email inviate entro 30s
**Constraints**: Ruoli multipli per utente, soft-delete obbligatorio prima di force-delete
**Scale/Scope**: ~100-1000 utenti iniziali

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Status | Note |
|-----------|--------|------|
| Test-First | ✅ Pass | Test Pest per ogni componente |
| Library-First | N/A | Feature interna al progetto |
| Observability | ✅ Pass | Activity log integrato |
| Simplicity | ✅ Pass | Uso pattern Filament standard |

Nessuna violazione rilevata. Procedo con Phase 0.

## Project Structure

### Documentation (this feature)

```text
specs/001-user-management/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (N/A - no external API)
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   └── CreateSuperAdminCommand.php    # Comando artisan
├── Filament/
│   └── Resources/
│       └── UserResource/
│           ├── UserResource.php       # Risorsa principale
│           └── Pages/
│               ├── ListUsers.php
│               ├── CreateUser.php
│               ├── EditUser.php
│               └── ViewActivities.php # Pagina activity log
├── Models/
│   └── User.php                       # Esistente, da estendere
├── Policies/
│   └── UserPolicy.php                 # Autorizzazioni
├── Notifications/
│   └── UserInvitedNotification.php    # Email invito
└── Traits/
    └── LogsActivityAllDirty.php       # Esistente

database/
├── migrations/
│   └── xxxx_add_last_login_to_users.php
└── seeders/
    └── RoleSeeder.php                 # Seeder ruoli base

tests/
├── Feature/
│   ├── Commands/
│   │   └── CreateSuperAdminCommandTest.php
│   └── Filament/
│       ├── UserResourceTest.php
│       ├── UserInviteActionTest.php
│       └── UserPolicyTest.php
└── Unit/
    └── UserRoleTest.php
```

**Structure Decision**: Struttura Laravel standard con Filament Resources. Nessun modulo separato necessario.

## Complexity Tracking

Nessuna violazione da giustificare.
