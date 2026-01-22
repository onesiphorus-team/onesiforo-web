# Tasks: OnesiBox Management

**Input**: Design documents from `/specs/008-onesibox-management/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md

**Tests**: Required per constitution check ("Write Pest tests for all new functionality")

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Laravel monolith**: `app/`, `tests/` at repository root
- All paths are relative to repository root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create new directories and base files

- [ ] T001 Create RelationManagers directory at app/Filament/Resources/OnesiBoxes/RelationManagers/
- [ ] T002 Create Actions directory at app/Actions/ (if not exists)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before user story implementation

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T003 Create GenerateOnesiBoxToken action class at app/Actions/GenerateOnesiBoxToken.php
- [ ] T004 [P] Write unit tests for GenerateOnesiBoxToken at tests/Unit/Actions/GenerateOnesiBoxTokenTest.php
- [ ] T005 Register TokensRelationManager in OnesiBoxResource at app/Filament/Resources/OnesiBoxes/OnesiBoxResource.php

**Checkpoint**: Foundation ready - user story implementation can now begin

---

## Phase 3: User Story 1 - Create OnesiBox with Recipient (Priority: P1) 🎯 MVP

**Goal**: Enable Admin/Super Admin to create new OnesiBox with recipient data via enhanced form

**Independent Test**: Navigate to OnesiBox create page, fill form with device and recipient data, verify both records created with relationship

### Tests for User Story 1

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T006 [P] [US1] Write test for OnesiBox create page rendering at tests/Feature/Filament/OnesiBoxResourceTest.php
- [ ] T007 [P] [US1] Write test for creating OnesiBox with existing recipient at tests/Feature/Filament/OnesiBoxResourceTest.php
- [ ] T008 [P] [US1] Write test for creating OnesiBox with new inline recipient at tests/Feature/Filament/OnesiBoxResourceTest.php

### Implementation for User Story 1

- [ ] T009 [P] [US1] Create RecipientFieldset reusable schema at app/Filament/Resources/OnesiBoxes/Schemas/RecipientFieldset.php
- [ ] T010 [US1] Enhance OnesiBoxForm with createOptionForm for recipient at app/Filament/Resources/OnesiBoxes/Schemas/OnesiBoxForm.php
- [ ] T011 [US1] Update recipient Select to use createOptionUsing callback at app/Filament/Resources/OnesiBoxes/Schemas/OnesiBoxForm.php
- [ ] T012 [US1] Run tests and verify US1 acceptance scenarios pass

**Checkpoint**: User Story 1 is fully functional - can create OnesiBox with recipient inline

---

## Phase 4: User Story 2 - Generate Authentication Token (Priority: P1)

**Goal**: Enable token generation with copyable modal display

**Independent Test**: Navigate to OnesiBox edit page, click generate token, verify modal shows plain text token with copy button

### Tests for User Story 2

- [ ] T013 [P] [US2] Write test for TokensRelationManager rendering at tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php
- [ ] T014 [P] [US2] Write test for generate token action at tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php
- [ ] T015 [P] [US2] Write test for token activity logging at tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php

### Implementation for User Story 2

- [ ] T016 [US2] Create TokensRelationManager class at app/Filament/Resources/OnesiBoxes/RelationManagers/TokensRelationManager.php
- [ ] T017 [US2] Implement table columns (name, created_at, last_used_at, expires_at) in TokensRelationManager
- [ ] T018 [US2] Implement generate token header action with modal in TokensRelationManager
- [ ] T019 [US2] Create token display Blade view at resources/views/filament/modals/token-display.blade.php
- [ ] T020 [US2] Add activity logging for token generation in TokensRelationManager
- [ ] T021 [US2] Run tests and verify US2 acceptance scenarios pass

**Checkpoint**: User Stories 1 AND 2 are functional - can create OnesiBox and generate tokens

---

## Phase 5: User Story 3 - View Token Usage History (Priority: P2)

**Goal**: Display last_used_at timestamp for each token

**Independent Test**: Generate token, use it via API, verify last_used_at shows in token list

### Tests for User Story 3

- [ ] T022 [P] [US3] Write test for token list displays last_used_at at tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php
- [ ] T023 [P] [US3] Write test for "Never" display when token unused at tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php

### Implementation for User Story 3

- [ ] T024 [US3] Enhance last_used_at column formatting in TokensRelationManager (placeholder for "Never")
- [ ] T025 [US3] Add created_at and expires_at columns with proper formatting in TokensRelationManager
- [ ] T026 [US3] Run tests and verify US3 acceptance scenarios pass

**Checkpoint**: Token usage history is visible

---

## Phase 6: User Story 4 - Revoke Authentication Token (Priority: P2)

**Goal**: Enable token revocation with confirmation

**Independent Test**: Click revoke on token, confirm, verify token removed and cannot authenticate

### Tests for User Story 4

- [ ] T027 [P] [US4] Write test for revoke action visibility at tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php
- [ ] T028 [P] [US4] Write test for revoke action with confirmation at tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php
- [ ] T029 [P] [US4] Write test for revoke activity logging at tests/Feature/Filament/OnesiBoxTokensRelationManagerTest.php

### Implementation for User Story 4

- [ ] T030 [US4] Implement revoke (delete) action with confirmation in TokensRelationManager
- [ ] T031 [US4] Add activity logging for token revocation in TokensRelationManager
- [ ] T032 [US4] Run tests and verify US4 acceptance scenarios pass

**Checkpoint**: Full token lifecycle (generate, view, revoke) is functional

---

## Phase 7: User Story 5 - Form Validation and UX (Priority: P2)

**Goal**: Polish form with proper validation messages and elegant UX

**Independent Test**: Submit form with invalid data, verify inline errors display correctly

### Tests for User Story 5

- [ ] T033 [P] [US5] Write test for required field validation at tests/Feature/Filament/OnesiBoxResourceTest.php
- [ ] T034 [P] [US5] Write test for unique serial number validation at tests/Feature/Filament/OnesiBoxResourceTest.php
- [ ] T035 [P] [US5] Write test for phone format validation at tests/Feature/Filament/OnesiBoxResourceTest.php

### Implementation for User Story 5

- [ ] T036 [US5] Add validation rules to OnesiBoxForm fields at app/Filament/Resources/OnesiBoxes/Schemas/OnesiBoxForm.php
- [ ] T037 [US5] Add validation rules to RecipientFieldset fields at app/Filament/Resources/OnesiBoxes/Schemas/RecipientFieldset.php
- [ ] T038 [US5] Add custom validation messages for phone format at app/Filament/Resources/OnesiBoxes/Schemas/RecipientFieldset.php
- [ ] T039 [US5] Run tests and verify US5 acceptance scenarios pass

**Checkpoint**: All validation scenarios pass

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Cleanup, remove old code, final verification

- [ ] T040 Remove generateTokenAction from OnesiBoxesTable at app/Filament/Resources/OnesiBoxes/Tables/OnesiBoxesTable.php
- [ ] T041 Run full test suite with `php artisan test --compact`
- [ ] T042 Run Laravel Pint formatter with `vendor/bin/pint --dirty`
- [ ] T043 Run Larastan static analysis with `vendor/bin/phpstan analyse`
- [ ] T044 Verify all acceptance scenarios manually per quickstart.md

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3-7)**: All depend on Foundational phase completion
  - US1 and US2 are both P1 - can proceed in parallel if staffed
  - US3, US4, US5 are P2 - can proceed after US1/US2 or in parallel
- **Polish (Phase 8)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) - Independent, but uses same relation manager registration
- **User Story 3 (P2)**: Builds on US2 TokensRelationManager - can extend existing code
- **User Story 4 (P2)**: Builds on US2 TokensRelationManager - can extend existing code
- **User Story 5 (P2)**: Enhances US1 form - can extend existing code

### Within Each User Story

- Tests MUST be written and FAIL before implementation
- Implementation follows tests
- Story complete before moving to next priority (or parallel if staffed)

### Parallel Opportunities

- T006, T007, T008 (US1 tests) can run in parallel
- T013, T014, T015 (US2 tests) can run in parallel
- T022, T023 (US3 tests) can run in parallel
- T027, T028, T029 (US4 tests) can run in parallel
- T033, T034, T035 (US5 tests) can run in parallel
- US1 and US2 can be worked on in parallel by different developers (both P1)
- US3, US4, US5 can be worked on in parallel after foundational US2 relation manager exists

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together:
Task: "Write test for OnesiBox create page rendering"
Task: "Write test for creating OnesiBox with existing recipient"
Task: "Write test for creating OnesiBox with new inline recipient"

# Then implement:
Task: "Create RecipientFieldset reusable schema"
Task: "Enhance OnesiBoxForm with createOptionForm for recipient"
```

---

## Implementation Strategy

### MVP First (User Story 1 + 2)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (Create OnesiBox with Recipient)
4. Complete Phase 4: User Story 2 (Generate Token)
5. **STOP and VALIDATE**: Test US1 and US2 independently
6. Deploy/demo if ready - Core functionality complete!

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (can create OnesiBox)
3. Add User Story 2 → Test independently → Deploy/Demo (can generate tokens)
4. Add User Story 3 → Test independently → Deploy/Demo (can see token history)
5. Add User Story 4 → Test independently → Deploy/Demo (can revoke tokens)
6. Add User Story 5 → Test independently → Deploy/Demo (polished validation)
7. Complete Polish → Final release

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (form enhancement)
   - Developer B: User Story 2 (token generation)
3. After US2 relation manager exists:
   - Developer A: User Story 5 (validation polish)
   - Developer B: User Stories 3 + 4 (token view/revoke)

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing (TDD required per constitution)
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Run `vendor/bin/pint --dirty` before committing PHP changes
