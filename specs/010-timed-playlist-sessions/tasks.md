# Tasks: Sessioni Video a Tempo con Playlist

**Input**: Design documents from `/specs/010-timed-playlist-sessions/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-v1.md

**Tests**: Required per project conventions (CLAUDE.md: "Every change must be programmatically tested").

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

## Phase 1: Setup

**Purpose**: Enums, migrations, and database schema

- [x] T001 [P] Create PlaybackSessionStatus enum in app/Enums/PlaybackSessionStatus.php with values: Active, Completed, Stopped, Error. Implement HasColor, HasIcon, HasLabel for Filament compatibility. Follow existing enum patterns (see CommandStatus.php).
- [x] T002 [P] Create PlaylistSourceType enum in app/Enums/PlaylistSourceType.php with values: Manual, JworgSection. Implement HasLabel. Follow existing enum patterns.
- [x] T003 Create migration for playlists table in database/migrations/. Columns: id, onesi_box_id (FK cascade), name (nullable string 255), source_type (string 20), source_url (nullable string 2048), is_saved (boolean default false), timestamps. Index on (onesi_box_id, is_saved). See data-model.md for full schema.
- [x] T004 Create migration for playlist_items table in database/migrations/. Columns: id, playlist_id (FK cascade), media_url (string 2048), title (nullable string 500), duration_seconds (nullable unsigned int), position (unsigned int), created_at. Unique index on (playlist_id, position). See data-model.md.
- [x] T005 Create migration for playback_sessions table in database/migrations/. Columns: id, uuid (unique), onesi_box_id (FK cascade), playlist_id (FK restrict), status (string 20 default 'active'), duration_minutes (unsigned int), started_at, ended_at (nullable), current_position (unsigned int default 0), items_played (unsigned int default 0), items_skipped (unsigned int default 0), timestamps. Indexes on (onesi_box_id, status) and uuid. See data-model.md.
- [x] T006 Run migrations with `php artisan migrate` to verify all three migrations execute correctly.

---

## Phase 2: Foundational (Models, Factories, Relationships)

**Purpose**: Core models and factories that ALL user stories depend on

**CRITICAL**: No user story work can begin until this phase is complete

- [x] T007 [P] Create Playlist model in app/Models/Playlist.php. Relationships: belongsTo OnesiBox, hasMany PlaylistItem, hasMany PlaybackSession. Casts: source_type → PlaylistSourceType enum, is_saved → boolean. Scope: onlySaved() for is_saved=true (renamed from saved() to avoid conflict with Eloquent's Model::saved()). Follow existing model patterns (see Command.php).
- [x] T008 [P] Create PlaylistItem model in app/Models/PlaylistItem.php. Relationships: belongsTo Playlist. No updated_at ($timestamps = false, manually manage created_at like PlaybackEvent.php). Casts: position → integer, duration_seconds → integer.
- [x] T009 [P] Create PlaybackSession model in app/Models/PlaybackSession.php. Relationships: belongsTo OnesiBox, belongsTo Playlist. Casts: status → PlaybackSessionStatus enum, started_at → datetime, ended_at → datetime, duration_minutes → integer. Auto-generate UUID on creating (follow Command.php pattern). Scope: active() for status=Active. Method: isExpired() checks started_at + duration_minutes vs now(). Method: timeRemainingSeconds(). Method: currentItem() returns PlaylistItem at current_position. Route key: uuid.
- [x] T010 [P] Create PlaylistFactory in database/factories/PlaylistFactory.php. Default: manual source_type, is_saved=false. States: saved(), jworgSection(), forOnesiBox(OnesiBox).
- [x] T011 [P] Create PlaylistItemFactory in database/factories/PlaylistItemFactory.php. Default: fake jw.org video URL, position=0. States: withTitle(), withDuration(), atPosition(int).
- [x] T012 [P] Create PlaybackSessionFactory in database/factories/PlaybackSessionFactory.php. Default: active status, 60 duration_minutes, started_at=now. States: active(), completed(), stopped(), error(), withDuration(int), forOnesiBox(OnesiBox), forPlaylist(Playlist).
- [x] T013 Add relationships to OnesiBox model in app/Models/OnesiBox.php: hasMany Playlist, hasMany PlaybackSession. Add method activeSession() that returns the active PlaybackSession (if any).

**Checkpoint**: Foundation ready - all models, factories, and relationships in place

---

## Phase 3: User Story 1 - Avvio sessione con playlist manuale (Priority: P1) — MVP

**Goal**: Il caregiver inserisce URL video manuali, seleziona una durata, e avvia una sessione. I video vengono riprodotti in sequenza. La sessione si ferma quando il tempo scade o i video finiscono. Il caregiver può interrompere manualmente.

**Independent Test**: Creare una playlist con 2-3 URL, impostare durata, verificare riproduzione sequenziale e auto-stop.

### Tests for User Story 1

- [x] T014 [P] [US1] Create StartPlaybackSessionTest in tests/Feature/Sessions/StartPlaybackSessionTest.php. 5 tests covering: session start, playlist items creation, replacing active session, offline rejection, correct associations.
- [x] T015 [P] [US1] Create StopPlaybackSessionTest in tests/Feature/Sessions/StopPlaybackSessionTest.php. 5 tests covering: stop sets status/ended_at, sends stop_media, cancels pending commands, ignores completed session, handles offline box.
- [x] T016 [P] [US1] Create AdvancePlaybackSessionTest in tests/Feature/Sessions/AdvancePlaybackSessionTest.php. 7 tests covering: advance to next video, end on last video, end on time expired, error skips video, no active session, non-session playback, existing API behavior preserved.

### Implementation for User Story 1

- [x] T017 [P] [US1] Create CreatePlaylistAction in app/Actions/Playlists/CreatePlaylistAction.php.
- [x] T018 [P] [US1] Create JwOrgSectionUrl validation rule in app/Rules/JwOrgSectionUrl.php.
- [x] T019 [US1] Create StartPlaybackSessionAction in app/Actions/Sessions/StartPlaybackSessionAction.php. Added sendSessionMediaCommand to OnesiBoxCommandServiceInterface and OnesiBoxCommandService (with priority=2 and session_id in payload).
- [x] T020 [US1] Create StopPlaybackSessionAction in app/Actions/Sessions/StopPlaybackSessionAction.php.
- [x] T021 [US1] Create AdvancePlaybackSessionAction in app/Actions/Sessions/AdvancePlaybackSessionAction.php.
- [x] T022 [US1] Modify PlaybackController to invoke AdvancePlaybackSessionAction on completed/error events.
- [x] T023 [US1] Create PlaylistBuilder Livewire component with URL add/remove/reorder functionality and Flux UI.
- [x] T024 [US1] Create SessionManager Livewire component with duration selector, start/stop session, and active session display with polling.
- [x] T025 [US1] Integrate SessionManager into OnesiBoxDetail page controls section.
- [x] T026 [US1] Add video completion detection to OnesiBox client media.js with polling approach (inject ended listener, poll every 2s).
- [x] T027 [US1] All 538 tests pass (17 new session tests + 521 existing).
- [x] T028 [US1] Pint formatting applied to all new files.

**Checkpoint**: User Story 1 complete — caregiver can start a manual playlist session, videos play sequentially, session auto-stops, manual stop works.

---

## Phase 4: User Story 2 - Sessione da sezione JW.org (Priority: P2)

**Goal**: Il caregiver inserisce un URL di una sezione JW.org, il sistema estrae automaticamente i video, mostra un'anteprima, e avvia la sessione.

**Independent Test**: Inserire URL di sezione JW.org, verificare estrazione video con conteggio e durata totale, avviare sessione.

### Tests for User Story 2

- [x] T029 [P] [US2] Create JwOrgMediaExtractorTest — 10 tests covering: extraction from valid response, empty category, API error, URL parsing (IT/EN), invalid URL rejection, video URL building, duration formatting, JwOrgSectionUrl validation.

### Implementation for User Story 2

- [x] T030 [US2] Create JwOrgMediaExtractor service with parseUrl(), mapLanguageCode(), fetchCategory(), extractMedia(), buildVideoUrl(). Supports 10 languages.
- [x] T031 [US2] Create ExtractJwOrgVideosAction wrapping JwOrgMediaExtractor.
- [x] T032 [US2] Update PlaylistBuilder with sourceType toggle (manual/jworg_section), sectionUrl, extractFromJwOrg() action, extraction preview with video count and duration.
- [x] T033 [US2] All 548 tests pass (10 new JwOrg extractor tests).
- [x] T034 [US2] Pint formatting applied.

**Checkpoint**: User Story 2 complete — caregiver can input JW.org section URL, see extracted videos, and start session.

---

## Phase 5: User Story 3 - Monitoraggio sessione in corso (Priority: P2)

**Goal**: Il caregiver vede lo stato della sessione in tempo reale (video corrente, tempo rimanente, progresso) e può interromperla.

**Independent Test**: Avviare una sessione e verificare che la dashboard mostri progresso in tempo reale e che il pulsante stop funzioni.

### Tests for User Story 3

- [x] T035 [P] [US3] Create SessionStatusTest — 5 tests covering: active session display, no-session state, progress after advance, read-only caregiver visibility, items played/skipped counts.

### Implementation for User Story 3

- [x] T036 [US3] Create SessionStatus Livewire component with computed properties (activeSession, hasActiveSession, timeRemainingSeconds, currentVideo, totalItems), progress bar, and wire:poll.10s. Visible to all caregivers.
- [x] T037 [US3] Integrate SessionStatus into OnesiBoxDetail page BEFORE controls section (visible to all caregivers, not just full permission).
- [x] T038 [US3] All 5 SessionStatus tests pass.
- [x] T039 [US3] Pint formatting clean.

**Checkpoint**: User Story 3 complete — caregiver sees real-time session monitoring with stop capability.

---

## Phase 6: User Story 4 - Playlist salvate e riutilizzabili (Priority: P3)

**Goal**: Il caregiver può salvare playlist con un nome e riutilizzarle per sessioni future. Le playlist sono condivise tra caregiver della stessa OnesiBox.

**Independent Test**: Creare e salvare una playlist, verificare che appaia nella lista, ricaricarla e avviare una nuova sessione.

### Tests for User Story 4

- [x] T040 [P] [US4] Create PlaylistManagementTest in tests/Feature/Playlists/PlaylistManagementTest.php. Test cases: save playlist with name creates is_saved=true record; load saved playlist returns correct items; update playlist modifies items; delete playlist removes record; playlist visible to all full-permission caregivers of same OnesiBox; read-only caregivers cannot create/modify/delete; start session from saved playlist works correctly; playlist name required for saved playlists.

### Implementation for User Story 4

- [x] T041 [US4] Create SavedPlaylists Livewire component in app/Livewire/Dashboard/Controls/SavedPlaylists.php with view in resources/views/livewire/dashboard/controls/saved-playlists.blade.php. Computed property: savedPlaylists — Playlist::where(onesi_box_id, is_saved=true)->withCount('items')->get(). Actions: savePlaylist(name, videoUrls) — creates playlist via CreatePlaylistAction with is_saved=true; deletePlaylist(playlistId) — deletes with confirmation; loadPlaylist(playlistId) — emits event to PlaylistBuilder to load URLs. Use ChecksOnesiBoxPermission trait. UI: list of saved playlists with name, item count, created date; save, edit, delete, and load buttons. Use Flux UI.
- [x] T042 [US4] Update SessionManager in app/Livewire/Dashboard/Controls/SessionManager.php to support starting session from a saved playlist. Add property: selectedPlaylistId (nullable). When set, skip PlaylistBuilder and use the saved playlist directly. Add action: startFromSavedPlaylist(playlistId, durationMinutes).
- [x] T043 [US4] Update PlaylistBuilder in app/Livewire/Dashboard/Controls/PlaylistBuilder.php. Add listener for loadPlaylist event from SavedPlaylists component. When received, populate videoUrls from the saved playlist items. Add "Salva playlist" button that emits event to SavedPlaylists with current URLs.
- [x] T044 [US4] Integrate SavedPlaylists component into OnesiBoxDetail page alongside PlaylistBuilder and SessionManager.
- [x] T045 [US4] Run tests: `php artisan test --compact --filter=PlaylistManagement`. Fix any failures.
- [x] T046 [US4] Run `vendor/bin/pint --dirty` to fix code formatting.

**Checkpoint**: User Story 4 complete — caregiver can save, load, edit, and delete playlists for reuse.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Integration testing, code quality, final verification

- [x] T047 Create PlaybackSessionIntegrationTest in tests/Feature/Api/PlaybackSessionIntegrationTest.php. End-to-end test: create OnesiBox with token, start session via Livewire action, simulate playback completed events via API (POST /api/v1/appliances/playback with auth token), verify commands are created for next videos, verify session completes when time expires or playlist ends. Also test: error events skip to next video, stop session cancels pending commands.
- [x] T048 Run full test suite: `php artisan test --compact`. Verify all tests pass including existing tests (no regressions).
- [x] T049 Run `vendor/bin/pint --dirty` for final code formatting pass.
- [x] T050 Verify quickstart.md flows work by running key tinker examples from specs/010-timed-playlist-sessions/quickstart.md.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 (migrations must exist) — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Phase 2 — this is the MVP
- **User Story 2 (Phase 4)**: Depends on Phase 2. Can run in parallel with US1 (separate files), but best after US1 since it extends PlaylistBuilder.
- **User Story 3 (Phase 5)**: Depends on Phase 2. Can run in parallel with US1/US2 (separate component), but best after US1 since it monitors sessions.
- **User Story 4 (Phase 6)**: Depends on Phase 2. Can run in parallel with US2/US3, but best after US1 since it extends SessionManager and PlaylistBuilder.
- **Polish (Phase 7)**: Depends on all user stories being complete

### User Story Dependencies

- **US1 (P1)**: No dependencies on other stories — fully standalone MVP
- **US2 (P2)**: Extends PlaylistBuilder from US1 — best after US1, but can scaffold independently
- **US3 (P2)**: New component, no code dependency on US1 — can develop in parallel
- **US4 (P3)**: Extends SessionManager and PlaylistBuilder from US1 — requires US1 complete

### Within Each User Story

- Tests written first (TDD where applicable)
- Actions before Livewire components (backend before frontend)
- Livewire components before page integration
- Code formatting after each story

### Parallel Opportunities

**Phase 1**: T001, T002 can run in parallel (different enum files)
**Phase 2**: T007, T008, T009, T010, T011, T012 can all run in parallel (different model/factory files)
**Phase 3**: T014, T015, T016 (tests) in parallel; T017, T018 (action + rule) in parallel
**Phase 4**: T029 (test) can start while US1 implementation is finishing
**Phase 5**: T035 (test) + T036 (component) can run in parallel with Phase 4

---

## Parallel Example: User Story 1

```bash
# Launch all tests in parallel (they test different actions):
Task: "T014 - StartPlaybackSessionTest"
Task: "T015 - StopPlaybackSessionTest"
Task: "T016 - AdvancePlaybackSessionTest"

# Launch independent actions in parallel:
Task: "T017 - CreatePlaylistAction"
Task: "T018 - JwOrgSectionUrl rule"

# Then sequential (depends on actions):
Task: "T019 - StartPlaybackSessionAction" (needs T017)
Task: "T020 - StopPlaybackSessionAction"
Task: "T021 - AdvancePlaybackSessionAction"
Task: "T022 - Modify PlaybackController" (needs T021)

# Then Livewire components:
Task: "T023 - PlaylistBuilder component"
Task: "T024 - SessionManager component" (needs T023 for URL input)
Task: "T025 - Integrate into OnesiBoxDetail"

# OnesiBox client change (independent of backend Livewire):
Task: "T026 - Video ended detection" (can run in parallel with T023-T025)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (enums + migrations)
2. Complete Phase 2: Foundational (models + factories + relationships)
3. Complete Phase 3: User Story 1 (manual playlist session)
4. **STOP and VALIDATE**: Test full flow end-to-end
5. Deploy/demo — caregiver can already use timed playlist sessions

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US1 → Manual playlist sessions work → **MVP Deploy**
3. Add US2 → JW.org section extraction → Deploy
4. Add US3 → Real-time monitoring → Deploy
5. Add US4 → Saved playlists → Deploy
6. Each story adds value without breaking previous stories

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- The CLAUDE.md requires tests for every change — tests are included per story
- OnesiBox client change (T026) is the ONLY modification to the Node.js project at `/Users/ryuujin/workspace/dev2geek/repos/Progetto Onesiforo/onesi-box/`
- All backend changes follow existing patterns (Actions, Services, Livewire, Factories)
- Total: 50 tasks across 7 phases
