# Tasks: OnesiBox Caregiver Controls

**Input**: Design documents from `/specs/009-onesibox-caregiver-controls/`
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, contracts/ ✓

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Include exact file paths in descriptions

## Path Conventions

This project involves two repositories:
- **Onesiforo (Laravel)**: `/onesiforo/` - Backend + Livewire UI
- **OnesiBox (Node.js)**: `/onesi-box/` - Raspberry Pi client

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and foundational enums/models changes

- [x] T001 [P] Add `GetSystemInfo` and `GetLogs` cases to `app/Enums/CommandType.php`
- [x] T002 [P] Add `Cancelled` case to `app/Enums/CommandStatus.php`
- [x] T003 Create migration for OnesiBox extended fields in `database/migrations/xxxx_add_media_fields_to_onesi_boxes_table.php`
- [x] T004 Update `app/Models/OnesiBox.php` - add fillable fields and casts for `current_media_url`, `current_media_type`, `current_media_title`, `current_meeting_id`, `volume`, `last_system_info_at`
- [x] T005 Run migration: `php artisan migrate`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T006 [P] Extend `app/Http/Requests/Api/V1/HeartbeatRequest.php` validation rules for `current_media`, `current_meeting`, `volume`
- [x] T007 [P] Extend `app/Http/Controllers/Api/V1/HeartbeatController.php` to persist extended fields to OnesiBox model
- [x] T008 Extend `app/Events/OnesiBoxStatusUpdated.php` to include `current_media`, `current_meeting`, `volume` in broadcast payload
- [x] T009 Write feature test for extended heartbeat in `tests/Feature/Api/V1/HeartbeatExtendedTest.php`

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Visualizzazione Stato Attuale OnesiBox (Priority: P1) 🎯 MVP

**Goal**: Caregiver visualizes OnesiBox current status (idle/video/audio/Zoom) with contextual info

**Independent Test**: Simulate different appliance states via heartbeat and verify UI displays correct status with contextual information

### Tests for User Story 1

- [x] T010 [P] [US1] Write Livewire component test in `tests/Feature/Livewire/Dashboard/OnesiBoxStatusDisplayTest.php`

### Implementation for User Story 1

- [x] T011 [US1] Add helper methods to `app/Enums/OnesiBoxStatus.php` for `getLabel()` and `getIcon()` if not present
- [x] T012 [US1] Update `app/Livewire/Dashboard/OnesiBoxDetail.php` to expose `currentMediaInfo` and `currentMeetingInfo` computed properties
- [x] T013 [US1] Update `resources/views/livewire/dashboard/onesi-box-detail.blade.php` to display contextual status info (media URL/title, meeting ID)
- [x] T014 [US1] Verify real-time update via Echo listener `StatusUpdated` event refreshes state within 5 seconds

**Checkpoint**: User Story 1 should be fully functional - caregiver sees real-time status with contextual info

---

## Phase 4: User Story 2 - Regolazione Volume (Priority: P1) 🎯 MVP

**Goal**: Caregiver can adjust volume with 5 preset levels (20%, 40%, 60%, 80%, 100%)

**Independent Test**: Select each volume level and verify command is sent correctly to appliance

### Tests for User Story 2

- [x] T015 [P] [US2] Write Livewire component test in `tests/Feature/Livewire/Dashboard/Controls/VolumeControlTest.php`
- [x] T016 [P] [US2] Write unit test for volume command action in `tests/Unit/Actions/Commands/CreateVolumeCommandActionTest.php`

### Implementation for User Story 2

- [x] T017 [P] [US2] Create action `app/Actions/Commands/CreateVolumeCommandAction.php`
- [x] T018 [US2] Create Livewire component `app/Livewire/Dashboard/Controls/VolumeControl.php`
- [x] T019 [US2] Create Blade view `resources/views/livewire/dashboard/controls/volume-control.blade.php` with 5 Flux buttons
- [x] T020 [US2] Include VolumeControl component in `resources/views/livewire/dashboard/onesi-box-detail.blade.php`
- [x] T021 [US2] Add permission check for `canControl` property (disable for ReadOnly caregivers)
- [x] T022 [US2] Add offline check - disable controls and show message when OnesiBox is offline

**Checkpoint**: User Story 2 should be fully functional - caregiver can set volume with visual feedback

---

## Phase 5: User Story 3 - Visualizzazione e Gestione Coda Comandi (Priority: P2)

**Goal**: Caregiver can view pending commands and cancel them (single or all)

**Independent Test**: Create pending commands in database, view them in UI, verify cancellation works

### Tests for User Story 3

- [x] T023 [P] [US3] Write Livewire component test in `tests/Feature/Livewire/Dashboard/Controls/CommandQueueTest.php`
- [ ] T024 [P] [US3] Write API test for command cancel in `tests/Feature/Api/V1/CommandCancelTest.php` (DEFERRED - API not required for web UI)
- [x] T025 [P] [US3] Write unit test for cancel action in `tests/Unit/Actions/Commands/CancelCommandActionTest.php`

### Implementation for User Story 3

- [x] T026 [P] [US3] Create action `app/Actions/Commands/CancelCommandAction.php`
- [ ] T027 [US3] Extend `app/Http/Controllers/Api/V1/CommandController.php` with `destroy()` method for single cancel (DEFERRED - API not required for web UI)
- [ ] T028 [US3] Add routes in `routes/api.php`: `DELETE /api/v1/commands/{uuid}`, `GET /api/v1/appliances/{onesiBox}/commands/pending`, `DELETE /api/v1/appliances/{onesiBox}/commands/pending` (DEFERRED - API not required for web UI)
- [x] T029 [US3] Create Livewire component `app/Livewire/Dashboard/Controls/CommandQueue.php`
- [x] T030 [US3] Create Blade view `resources/views/livewire/dashboard/controls/command-queue.blade.php` with command list and delete buttons
- [x] T031 [US3] Include CommandQueue component in OnesiBoxDetail with Flux modal for delete confirmation
- [x] T032 [US3] Add permission check - ReadOnly caregivers can view but not delete

**Checkpoint**: User Story 3 should be fully functional - caregiver can manage command queue

---

## Phase 6: User Story 4 - Informazioni di Sistema (Priority: P2)

**Goal**: Caregiver can view system info (uptime, memory, CPU, disk, network) and request refresh

**Independent Test**: View system info from last heartbeat, request fresh data via command

### Tests for User Story 4

- [x] T033 [P] [US4] Write Livewire component test in `tests/Feature/Livewire/Dashboard/Controls/SystemInfoTest.php`
- [ ] T034 [P] [US4] Write OnesiBox unit test in `onesi-box/tests/system-info.test.js` (OnesiBox repository)

### Implementation for User Story 4 - Onesiforo

- [x] T035 [US4] Create Livewire component `app/Livewire/Dashboard/Controls/SystemInfo.php`
- [x] T036 [US4] Create Blade view `resources/views/livewire/dashboard/controls/system-info.blade.php` with Flux UI cards and progress bars
- [x] T037 [US4] Include SystemInfo component in OnesiBoxDetail
- [x] T038 [US4] Add method to create `GetSystemInfo` command when refresh button clicked

### Implementation for User Story 4 - OnesiBox

- [ ] T039 [P] [US4] Create handler `onesi-box/src/commands/handlers/system-info.js` using `systeminformation` package (OnesiBox repository)
- [ ] T040 [US4] Register `get_system_info` handler in `onesi-box/src/commands/command-executor.js` (OnesiBox repository)
- [ ] T041 [US4] Ensure `systeminformation` is in `onesi-box/package.json` dependencies (OnesiBox repository)

**Checkpoint**: User Story 4 should be fully functional - caregiver sees system metrics with refresh option

---

## Phase 7: User Story 5 - Richiesta Log Applicazione (Priority: P3)

**Goal**: Caregiver can request last N log lines (max 500) with sensitive data filtered

**Independent Test**: Request specific number of log lines, verify sanitized response

### Tests for User Story 5

- [ ] T042 [P] [US5] Write Livewire component test in `tests/Feature/Livewire/Dashboard/Controls/LogViewerTest.php`
- [ ] T043 [P] [US5] Write OnesiBox unit test for log handler in `onesi-box/tests/logs.test.js`
- [ ] T044 [P] [US5] Write OnesiBox unit test for sanitizer in `onesi-box/tests/log-sanitizer.test.js`

### Implementation for User Story 5 - Onesiforo

- [ ] T045 [US5] Create Livewire component `app/Livewire/Dashboard/Controls/LogViewer.php`
- [ ] T046 [US5] Create Blade view `resources/views/livewire/dashboard/controls/log-viewer.blade.php` with Flux input and scrollable log display
- [ ] T047 [US5] Include LogViewer component in OnesiBoxDetail
- [ ] T048 [US5] Add method to create `GetLogs` command with `lines` payload

### Implementation for User Story 5 - OnesiBox

- [ ] T049 [P] [US5] Create log sanitizer module `onesi-box/src/logging/log-sanitizer.js` with patterns for passwords, tokens, credentials
- [ ] T050 [US5] Create handler `onesi-box/src/commands/handlers/logs.js` using sanitizer
- [ ] T051 [US5] Register `get_logs` handler in `onesi-box/src/commands/command-executor.js`
- [ ] T052 [US5] Ensure logs are stored in JSON Lines format for structured parsing

**Checkpoint**: User Story 5 should be fully functional - caregiver can retrieve sanitized logs

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T053 [P] Run `vendor/bin/pint --dirty` to fix code style
- [ ] T054 [P] Run `php artisan test --compact` to verify all tests pass
- [ ] T055 [P] Run OnesiBox tests: `npm test` in `onesi-box/`
- [ ] T056 Validate quickstart.md by following setup steps on clean environment
- [ ] T057 [P] Add Italian translations for new UI labels in `lang/it/` if applicable

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup (T001-T005) completion - BLOCKS all user stories
- **User Stories (Phase 3-7)**: All depend on Foundational phase completion
  - US1 and US2 are both P1 - can proceed in parallel or sequentially
  - US3 and US4 are both P2 - can proceed in parallel after P1
  - US5 is P3 - can proceed after P2 or in parallel if resources allow
- **Polish (Phase 8)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational - No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational - May use status info from US1 but independently testable
- **User Story 3 (P2)**: Can start after Foundational - Independent of US1/US2
- **User Story 4 (P2)**: Can start after Foundational - Independent
- **User Story 5 (P3)**: Can start after Foundational - Independent

### Cross-Repository Tasks

Some user stories require changes in both repositories:
- **US4 (System Info)**: Onesiforo T035-T038 + OnesiBox T039-T041
- **US5 (Logs)**: Onesiforo T045-T048 + OnesiBox T049-T052

These can be developed in parallel but must be integration-tested together.

### Parallel Opportunities

**Within Setup:**
- T001 and T002 can run in parallel (different enum files)

**Within Foundational:**
- T006 and T007 can run in parallel (different files)

**Within User Stories:**
- All test tasks marked [P] can run in parallel
- US1, US2, US3, US4, US5 can be worked on in parallel after Foundational

**Cross-Repository:**
- OnesiBox tasks (T039-T041, T049-T052) can run in parallel with Onesiforo tasks

---

## Task Summary

| Phase | Tasks | Parallel Opportunities |
|-------|-------|----------------------|
| Setup | 5 | T001, T002 |
| Foundational | 4 | T006, T007 |
| US1 - Status Display | 5 | T010 |
| US2 - Volume Control | 8 | T015, T016, T017 |
| US3 - Command Queue | 10 | T023, T024, T025, T026 |
| US4 - System Info | 9 | T033, T034, T039 |
| US5 - Log Viewer | 11 | T042, T043, T044, T049 |
| Polish | 5 | T053, T054, T055, T057 |
| **Total** | **57** | |

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: User Story 1 (Status Display)
4. Complete Phase 4: User Story 2 (Volume Control)
5. **STOP and VALIDATE**: Test US1 + US2 independently
6. Deploy/demo - Caregiver can see status and control volume

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US1 + US2 → Test → Deploy (MVP with core controls)
3. Add US3 (Command Queue) → Test → Deploy
4. Add US4 (System Info) → Test → Deploy
5. Add US5 (Logs) → Test → Deploy
