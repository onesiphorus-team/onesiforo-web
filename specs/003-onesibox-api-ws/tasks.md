# Tasks: OnesiBox API Webservices

**Input**: Design documents from `/specs/003-onesibox-api-ws/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/openapi.yaml

**Tests**: Tests are REQUIRED per spec.md SC-006 (minimum 80% coverage).

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create enums, migrations, and foundational database structure

- [x] T001 [P] Create CommandType enum in app/Enums/CommandType.php with defaultExpiresInMinutes() method
- [x] T002 [P] Create CommandStatus enum in app/Enums/CommandStatus.php (pending, completed, failed, expired)
- [x] T003 [P] Create PlaybackEventType enum in app/Enums/PlaybackEventType.php (started, paused, resumed, stopped, completed, error)
- [x] T004 Create migration for commands table in database/migrations/xxxx_create_commands_table.php
- [x] T005 Create migration for playback_events table in database/migrations/xxxx_create_playback_events_table.php
- [x] T006 Run migrations and verify schema: `php artisan migrate`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core models, factories, and OnesiBox extension that ALL user stories depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T007 Create Command model in app/Models/Command.php with UUID route key, casts, relationships, and scopes
- [x] T008 Create PlaybackEvent model in app/Models/PlaybackEvent.php with casts and relationships
- [x] T009 [P] Create CommandFactory in database/factories/CommandFactory.php with states for each status
- [x] T010 [P] Create PlaybackEventFactory in database/factories/PlaybackEventFactory.php with states for each event type
- [x] T011 Extend OnesiBox model with commands(), pendingCommands(), and playbackEvents() relationships in app/Models/OnesiBox.php
- [x] T012 Verify foundation by running: `php artisan tinker` and testing relationships

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - OnesiBox Retrieves Pending Commands (Priority: P1) 🎯 MVP

**Goal**: L'appliance OnesiBox recupera i comandi pendenti da eseguire ordinati per priorita e data

**Independent Test**: Creare comandi nel database e verificare che l'appliance li riceva correttamente tramite l'endpoint GET /api/v1/appliances/commands

### Tests for User Story 1 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [x] T013 [US1] Create feature test file tests/Feature/Api/V1/CommandApiTest.php with test structure
- [x] T014 [P] [US1] Test: authenticated appliance retrieves pending commands ordered by priority
- [x] T015 [P] [US1] Test: authenticated appliance with no pending commands receives empty list
- [x] T016 [P] [US1] Test: appliance with invalid token receives 401 Unauthorized with error_code E001
- [x] T017 [P] [US1] Test: disabled appliance (is_active=false) receives 403 Forbidden with error_code E003
- [x] T018 [P] [US1] Test: expired commands are automatically filtered and marked as expired
- [x] T019 [P] [US1] Test: status and limit query parameters work correctly

### Implementation for User Story 1

- [x] T020 [US1] Create GetCommandsRequest in app/Http/Requests/Api/V1/GetCommandsRequest.php with OnesiBox auth pattern
- [x] T021 [US1] Create CommandResource in app/Http/Resources/Api/V1/CommandResource.php
- [x] T022 [US1] Create CommandCollection in app/Http/Resources/Api/V1/CommandCollection.php with meta (total, pending)
- [x] T023 [US1] Create CommandController in app/Http/Controllers/Api/V1/CommandController.php with index() method
- [x] T024 [US1] Add route GET /api/v1/appliances/commands in routes/api.php
- [x] T025 [US1] Add Scramble PHPDoc annotations for automatic documentation
- [x] T026 [US1] Run tests: `php artisan test --filter=CommandApiTest`

**Checkpoint**: GET /appliances/commands endpoint fully functional and tested independently

---

## Phase 4: User Story 2 - OnesiBox Conferma Esecuzione Comando (Priority: P1)

**Goal**: L'appliance conferma l'esito dell'esecuzione di un comando (successo, fallimento, skip)

**Independent Test**: Inviare acknowledgment per un comando esistente e verificare che lo stato venga aggiornato nel database

### Tests for User Story 2 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [x] T027 [P] [US2] Test: authenticated appliance acknowledges command with success updates status to completed
- [x] T028 [P] [US2] Test: authenticated appliance acknowledges command with failure updates status to failed with error_code and error_message
- [x] T029 [P] [US2] Test: acknowledging non-existent command returns 404 Not Found with error_code E002
- [x] T030 [P] [US2] Test: acknowledging command belonging to another appliance returns 403 Forbidden with error_code E003
- [x] T031 [P] [US2] Test: idempotent acknowledgment - already processed command returns 200 OK without state change
- [x] T032 [P] [US2] Test: acknowledging expired command returns success with status expired

### Implementation for User Story 2

- [x] T033 [US2] Create AckCommandRequest in app/Http/Requests/Api/V1/AckCommandRequest.php with validation rules (status, error_code, error_message, executed_at)
- [x] T034 [US2] Create AckCommandResponse resource in app/Http/Resources/Api/V1/AckCommandResource.php
- [x] T035 [US2] Add acknowledge() method to CommandController in app/Http/Controllers/Api/V1/CommandController.php
- [x] T036 [US2] Add route POST /api/v1/commands/{command}/ack in routes/api.php with UUID binding
- [x] T037 [US2] Add Scramble PHPDoc annotations for ack endpoint documentation
- [x] T038 [US2] Run tests: `php artisan test --filter=CommandApiTest`

**Checkpoint**: POST /commands/{uuid}/ack endpoint fully functional and tested independently

---

## Phase 5: User Story 3 - OnesiBox Aggiorna Stato Riproduzione (Priority: P2)

**Goal**: L'appliance notifica eventi di riproduzione multimediale (started, paused, resumed, stopped, completed, error)

**Independent Test**: Inviare eventi di playback e verificare che vengano registrati correttamente nel sistema

### Tests for User Story 3 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [x] T039 [US3] Create feature test file tests/Feature/Api/V1/PlaybackApiTest.php with test structure
- [x] T040 [P] [US3] Test: authenticated appliance logs started event with media_url and media_type
- [x] T041 [P] [US3] Test: authenticated appliance logs paused event with position
- [x] T042 [P] [US3] Test: authenticated appliance logs error event with error_message
- [x] T043 [P] [US3] Test: appliance with invalid token receives 401 Unauthorized
- [x] T044 [P] [US3] Test: disabled appliance receives 403 Forbidden
- [x] T045 [P] [US3] Test: validation errors for invalid media_url or missing required fields

### Implementation for User Story 3

- [x] T046 [US3] Create PlaybackEventRequest in app/Http/Requests/Api/V1/PlaybackEventRequest.php with validation rules
- [x] T047 [US3] Create PlaybackEventResource in app/Http/Resources/Api/V1/PlaybackEventResource.php
- [x] T048 [US3] Create PlaybackController in app/Http/Controllers/Api/V1/PlaybackController.php with store() method
- [x] T049 [US3] Add route POST /api/v1/appliances/playback in routes/api.php
- [x] T050 [US3] Add Scramble PHPDoc annotations for playback endpoint documentation
- [x] T051 [US3] Run tests: `php artisan test --filter=PlaybackApiTest`

**Checkpoint**: POST /appliances/playback endpoint fully functional and tested independently

---

## Phase 6: User Story 4 - Documentazione API Automatica (Priority: P2)

**Goal**: Le API sono documentate automaticamente via Scramble e accessibili in /docs/api

**Independent Test**: Accedere a /docs/api e verificare che tutti gli endpoint siano documentati con parametri, response e errori

### Tests for User Story 4

- [x] T052 [US4] Verify Scramble documentation includes GET /appliances/commands with parameters (status, limit)
- [x] T053 [US4] Verify Scramble documentation includes POST /commands/{uuid}/ack with request/response schemas
- [x] T054 [US4] Verify Scramble documentation includes POST /appliances/playback with event types
- [x] T055 [US4] Verify all error responses (401, 403, 404, 422) are documented with error_code examples
- [x] T056 [US4] Manual review: access /docs/api and validate documentation completeness per SC-003

**Checkpoint**: All API endpoints are fully documented in /docs/api

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Final cleanup, validation, and integration verification

- [x] T057 Run full test suite: `php artisan test --compact`
- [x] T058 Run PHPStan analysis: `./vendor/bin/phpstan analyse` (level 8)
- [x] T059 Run Pint formatting: `./vendor/bin/pint --dirty`
- [x] T060 Validate quickstart.md scenarios work correctly
- [x] T061 Verify error messages are in Italian per FR-012 and SC-007
- [x] T062 Review and verify all error codes E001-E010 are correctly mapped per research.md

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-6)**: All depend on Foundational phase completion
  - US1 (P1) and US2 (P1) can proceed in parallel after Phase 2
  - US3 (P2) and US4 (P2) can proceed after Phase 2, or after US1/US2
- **Polish (Phase 7)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 3 (P2)**: Can start after Foundational (Phase 2) - Independent of US1/US2
- **User Story 4 (P2)**: Can start after Foundational (Phase 2) - Depends on implementations being complete to verify documentation

### Within Each User Story

- Tests MUST be written and FAIL before implementation
- Request before Resource before Controller
- Controller before Routes
- Scramble annotations at the end
- Story tests must pass before moving to next story

### Parallel Opportunities

- T001, T002, T003 (enums) can run in parallel
- T009, T010 (factories) can run in parallel
- All test tasks marked [P] can run in parallel
- US1 and US2 (both P1) can run in parallel after Phase 2
- US3 and US4 (both P2) can run in parallel after Phase 2

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Complete Phase 1: Setup (enums, migrations)
2. Complete Phase 2: Foundational (models, factories, OnesiBox extension)
3. Complete Phase 3: User Story 1 (GET commands)
4. Complete Phase 4: User Story 2 (POST ack)
5. **STOP and VALIDATE**: Test both US1 and US2 independently
6. Deploy/demo MVP - appliance can receive and acknowledge commands

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US1 (GET commands) → Test → Appliance can poll for commands
3. Add US2 (POST ack) → Test → Appliance can confirm execution
4. Add US3 (POST playback) → Test → Appliance can report media state
5. Add US4 (documentation) → Test → Developers can integrate
6. Polish → Final validation → Production ready

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Pattern: Follow existing HeartbeatController/HeartbeatRequest/HeartbeatResource
- All validation messages must be in Italian
