# Tasks: Gestione Utenti e Ruoli

**Input**: Design documents from `/specs/001-user-management/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, quickstart.md

**Tests**: Tests are REQUIRED as per spec.md which specifies test paths and testing requirements.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Database migrations and seeders required for user management

- [x] T001 Create migration for `last_login_at` and `deleted_at` columns in `database/migrations/xxxx_add_last_login_and_soft_deletes_to_users_table.php`
- [x] T002 Create RoleSeeder for default roles (super-admin, admin, caregiver) in `database/seeders/RoleSeeder.php`
- [x] T003 Run migrations and seeder to verify setup

**Checkpoint**: Database schema ready with users soft-delete support and default roles seeded

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 Update User model with SoftDeletes trait and `last_login_at` cast in `app/Models/User.php`
- [x] T005 [P] Create UserPolicy with authorization methods in `app/Policies/UserPolicy.php`
- [x] T006 [P] Register UserPolicy in `app/Providers/AppServiceProvider.php`
- [x] T007 [P] Configure Filament panel access restriction via `canAccessPanel()` in `app/Models/User.php` (FilamentUser interface)
- [x] T008 [P] Create Login event listener to update `last_login_at` in `app/Listeners/UpdateLastLogin.php`
- [x] T009 Register Login listener in `app/Providers/AppServiceProvider.php`

**Checkpoint**: Foundation ready - User model supports soft-delete, authorization via Policy, panel access restricted by role

---

## Phase 3: User Story 1 - Creazione Super Admin Iniziale (Priority: P1)

**Goal**: Fornire un comando artisan per creare il primo super-admin del sistema

**Independent Test**: Eseguire il comando e verificare che l'utente venga creato con ruolo super-admin e email verificata

### Tests for User Story 1

- [x] T010 [US1] Write test for CreateSuperAdminCommand in `tests/Feature/Commands/CreateSuperAdminCommandTest.php`

### Implementation for User Story 1

- [x] T011 [US1] Create CreateSuperAdminCommand with Laravel Prompts in `app/Console/Commands/CreateSuperAdminCommand.php`
- [x] T012 [US1] Implement command logic: name, email, password prompts with validation
- [x] T013 [US1] Add email uniqueness validation and hash password
- [x] T014 [US1] Assign super-admin role and mark email as verified
- [x] T015 [US1] Run tests to verify command works correctly

**Checkpoint**: Super-admin can be created via `php artisan app:create-super-admin`

---

## Phase 4: User Story 2 - Accesso al Pannello Amministrazione (Priority: P1)

**Goal**: Permettere accesso al pannello Filament solo a super-admin e admin, negare a caregiver

**Independent Test**: Login con diversi ruoli e verificare accesso/rifiuto al pannello

### Tests for User Story 2

- [x] T016 [US2] Write test for panel access control in `tests/Feature/Filament/PanelAccessTest.php`

### Implementation for User Story 2

- [x] T017 [US2] Verify `canAccessPanel()` method in User model restricts access correctly
- [x] T018 [US2] Filament returns 403 for unauthorized access attempts (built-in behavior)
- [x] T019 [US2] Run tests to verify access control works for all roles

**Checkpoint**: Super-admin and admin can access panel, caregiver is denied

---

## Phase 5: User Story 3 - Visualizzazione Lista Utenti (Priority: P1)

**Goal**: Mostrare lista utenti con nome, email, stato verifica, ruoli e ultimo accesso

**Independent Test**: Accedere a `/admin/users` e verificare che le colonne mostrino dati corretti

### Tests for User Story 3

- [x] T020 [US3] Write test for UserResource table columns in `tests/Feature/Filament/UserResourceTest.php`

### Implementation for User Story 3

- [x] T021 [US3] Generate UserResource with soft-deletes via `php artisan make:filament-resource User --soft-deletes`
- [x] T022 [US3] Configure table columns: name, email (with verification badge), roles (badges), last_login_at, online status
- [x] T023 [US3] Add TrashedFilter for viewing soft-deleted users
- [x] T024 [US3] Override `getRecordRouteBindingEloquentQuery()` to exclude SoftDeletingScope
- [x] T025 [US3] Run tests to verify table displays correctly

**Checkpoint**: Admins can view user list with all required columns and filters

---

## Phase 6: User Story 4 - Invito Nuovo Utente (Priority: P2)

**Goal**: Permettere agli admin di invitare nuovi utenti via email con link per impostare password

**Independent Test**: Creare un invito e verificare che l'email venga inviata con link corretto

### Tests for User Story 4

- [x] T026 [US4] Write test for user invite action in `tests/Feature/Filament/UserInviteActionTest.php`

### Implementation for User Story 4

- [x] T027 [P] [US4] Create UserInvitedNotification in `app/Notifications/UserInvitedNotification.php`
- [x] T028 [US4] Create Header Action "Invita Utente" in ListUsers page `app/Filament/Resources/UserResource/Pages/ListUsers.php`
- [x] T029 [US4] Implement invite form: name, email, role selection (filtered by current user role)
- [x] T030 [US4] Create user with temporary password and send invite notification
- [x] T031 [US4] Filter available roles: super-admin sees all, admin sees only caregiver
- [x] T032 [US4] Log activity "User invited" with invited_by and role
- [x] T033 [US4] Run tests to verify invite flow works correctly

**Checkpoint**: Admins can invite users, email is sent with setup link

---

## Phase 7: User Story 5 - Modifica Dati Utente (Priority: P2)

**Goal**: Permettere agli admin di modificare nome, cognome ed email degli utenti

**Independent Test**: Modificare dati utente e verificare il salvataggio

### Tests for User Story 5

- [x] T034 [US5] Write test for user edit form in `tests/Feature/Filament/UserResourceTest.php` (add to existing)

### Implementation for User Story 5

- [x] T035 [US5] Configure edit form fields: name, email in `app/Filament/Resources/UserResource.php`
- [x] T036 [US5] Add validation: email unique (excluding current user)
- [x] T037 [US5] Activity log automatically tracks changes via LogsActivityAllDirty trait
- [x] T038 [US5] Run tests to verify edit functionality

**Checkpoint**: Admins can edit user data with proper validation

---

## Phase 8: User Story 6 - Gestione Ruoli Utente (Priority: P2)

**Goal**: Permettere assegnazione/rimozione ruoli rispettando gerarchia

**Independent Test**: Assegnare ruoli con diversi tipi di amministratore

### Tests for User Story 6

- [x] T039 [US6] Write test for role management in `tests/Feature/Filament/UserPolicyTest.php`

### Implementation for User Story 6

- [x] T040 [US6] Add CheckboxList for roles in edit form with relationship `->relationship('roles', 'name')`
- [x] T041 [US6] Filter role options based on current user: super-admin sees all, admin sees only caregiver
- [x] T042 [US6] Disable role editing if user lacks permission
- [x] T043 [US6] Log activity "Role assigned" / "Role removed" with role name
- [x] T044 [US6] Run tests to verify role management respects hierarchy

**Checkpoint**: Role assignment works correctly with proper restrictions

---

## Phase 9: User Story 7 - Invio Email di Verifica (Priority: P2)

**Goal**: Permettere reinvio email di verifica per utenti non verificati

**Independent Test**: Inviare email verifica e verificare ricezione

### Tests for User Story 7

- [x] T045 [US7] Write test for resend verification action in `tests/Feature/Filament/UserResourceTest.php` (add to existing)

### Implementation for User Story 7

- [x] T046 [US7] Create record action "Invia Verifica Email" in UserResource table
- [x] T047 [US7] Action visible only for users with `email_verified_at = null`
- [x] T048 [US7] Call `$record->sendEmailVerificationNotification()`
- [x] T049 [US7] Show success notification after sending
- [x] T050 [US7] Log activity "Verification email sent"
- [x] T051 [US7] Run tests to verify action works correctly

**Checkpoint**: Admins can resend verification emails to unverified users

---

## Phase 10: User Story 8 - Invio Reset Password (Priority: P2)

**Goal**: Permettere invio link reset password agli utenti

**Independent Test**: Inviare reset password e verificare email ricevuta

### Tests for User Story 8

- [x] T052 [US8] Write test for password reset action in `tests/Feature/Filament/UserResourceTest.php` (add to existing)

### Implementation for User Story 8

- [x] T053 [US8] Create record action "Invia Reset Password" in UserResource table
- [x] T054 [US8] Use `Password::sendResetLink(['email' => $record->email])`
- [x] T055 [US8] Show success notification after sending
- [x] T056 [US8] Log activity "Password reset sent"
- [x] T057 [US8] Run tests to verify action works correctly

**Checkpoint**: Admins can send password reset links to users

---

## Phase 11: User Story 9 - Eliminazione Utente (Priority: P3)

**Goal**: Permettere solo ai super-admin di eliminare utenti (soft/force delete)

**Independent Test**: Tentare eliminazione con diversi ruoli

### Tests for User Story 9

- [x] T058 [US9] Write test for delete actions in `tests/Feature/Filament/UserPolicyTest.php` (add to existing)

### Implementation for User Story 9

- [x] T059 [US9] Add DeleteAction, ForceDeleteAction, RestoreAction to table
- [x] T060 [US9] UserPolicy: `delete()` only for super-admin, not self
- [x] T061 [US9] UserPolicy: `forceDelete()` only for super-admin, not self
- [x] T062 [US9] UserPolicy: `restore()` only for super-admin
- [x] T063 [US9] Add constraint: cannot delete if last super-admin
- [x] T064 [US9] Add DeleteBulkAction, ForceDeleteBulkAction, RestoreBulkAction
- [x] T065 [US9] Run tests to verify delete restrictions work correctly

**Checkpoint**: Only super-admin can delete users, with proper constraints

---

## Phase 12: User Story 10 - Visualizzazione Activity Log (Priority: P3)

**Goal**: Permettere visualizzazione e filtro delle attivita' utente

**Independent Test**: Eseguire azioni e verificare registrazione nel log

### Tests for User Story 10

- [x] T066 [US10] Write test for activity log display in `tests/Feature/Filament/ActivityLogTest.php`

### Implementation for User Story 10

- [x] T067 [P] [US10] Create ActivityResource or custom page for activity log in `app/Filament/Resources/ActivityResource.php`
- [x] T068 [US10] Configure table: date, causer, action, subject, properties
- [x] T069 [US10] Add filters: by user (causer), by subject, by event type, by date range
- [x] T070 [US10] Add search functionality
- [x] T071 [US10] Link to subject record if exists
- [x] T072 [US10] Run tests to verify activity log displays correctly

**Checkpoint**: Admins can view and filter activity logs

---

## Phase 13: Polish & Cross-Cutting Concerns

**Purpose**: Final refinements and validation

- [x] T073 Run full test suite: `php artisan test --filter=User`
- [x] T074 Run Laravel Pint for code formatting: `vendor/bin/pint --dirty`
- [x] T075 Validate quickstart.md instructions work end-to-end
- [x] T076 Verify all activity log events are properly recorded
- [x] T077 Security review: ensure no authorization bypass possible

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-12)**: All depend on Foundational phase completion
  - P1 stories (US1-3) should complete before P2 stories
  - P2 stories (US4-8) should complete before P3 stories
  - Within same priority, stories can proceed in parallel
- **Polish (Phase 13)**: Depends on all user stories being complete

### User Story Dependencies

- **US1 (Create Super Admin)**: No dependencies on other stories
- **US2 (Panel Access)**: No dependencies on other stories
- **US3 (User List)**: No dependencies on other stories, but base for US4-10
- **US4 (Invite User)**: Depends on US3 (UserResource exists)
- **US5 (Edit User)**: Depends on US3 (UserResource exists)
- **US6 (Role Management)**: Depends on US3 (UserResource exists)
- **US7 (Email Verification)**: Depends on US3 (UserResource exists)
- **US8 (Password Reset)**: Depends on US3 (UserResource exists)
- **US9 (Delete User)**: Depends on US3 (UserResource exists)
- **US10 (Activity Log)**: Depends on activity being logged by other stories

### Within Each User Story

- Tests MUST be written and FAIL before implementation
- Implementation follows test
- Verify tests pass after implementation
- Commit after each task or logical group

### Parallel Opportunities

- T005, T006, T007, T008 in Phase 2 can run in parallel
- After US3 completes, US4-US9 can proceed in parallel
- T027, T067 are marked [P] as they create independent files

---

## Implementation Strategy

### MVP First (P1 Stories Only)

1. Complete Phase 1: Setup (migrations, seeder)
2. Complete Phase 2: Foundational (model, policy, panel access)
3. Complete Phase 3: US1 - Create Super Admin
4. Complete Phase 4: US2 - Panel Access
5. Complete Phase 5: US3 - User List
6. **STOP and VALIDATE**: All P1 stories work independently
7. Deploy/demo if ready

### Full Implementation

1. Complete MVP (P1 stories)
2. Add P2 stories (US4-US8) - can be done in parallel
3. Add P3 stories (US9-US10)
4. Complete Polish phase
5. Full deployment

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- Use existing spatie/laravel-activitylog for activity tracking
- Use oltrematica/role-lite for role management (HasRoles trait)
