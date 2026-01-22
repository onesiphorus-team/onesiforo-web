# Tasks: Caregiver Dashboard

**Input**: Design documents from `/specs/004-caregiver-dashboard/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Inclusi come da specifica ("test ovunque") - Feature tests per ogni user story, Browser tests per mobile responsiveness.

**Organization**: Tasks organizzati per user story per abilitare implementazione e testing indipendente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Eseguibile in parallelo (file diversi, nessuna dipendenza)
- **[Story]**: User story di appartenenza (US1, US2, US3, US4, US5)
- Percorsi file esatti nelle descrizioni

## Path Conventions

Struttura Laravel standard:
- **Components**: `app/Livewire/Dashboard/`
- **Views**: `resources/views/livewire/dashboard/`
- **Tests**: `tests/Feature/Dashboard/`, `tests/Browser/Dashboard/`
- **Services**: `app/Services/`
- **Policies**: `app/Policies/`
- **Events**: `app/Events/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Inizializzazione progetto e struttura base

- [ ] T001 Creare migrazione per aggiungere colonna `status` a `onesi_boxes` in `database/migrations/xxxx_add_status_to_onesi_boxes_table.php`
- [ ] T002 [P] Aggiungere cast `status` al model OnesiBox in `app/Models/OnesiBox.php`
- [ ] T003 [P] Creare OnesiBoxPolicy in `app/Policies/OnesiBoxPolicy.php`
- [ ] T004 Registrare OnesiBoxPolicy in `app/Providers/AppServiceProvider.php`
- [ ] T005 [P] Creare OnesiBoxOfflineException in `app/Exceptions/OnesiBoxOfflineException.php`
- [ ] T006 [P] Creare OnesiBoxCommandException in `app/Exceptions/OnesiBoxCommandException.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Infrastruttura core che DEVE essere completa prima di ogni user story

**⚠️ CRITICAL**: Nessun lavoro su user story può iniziare fino al completamento di questa fase

- [ ] T007 Creare OnesiBoxCommandServiceInterface in `app/Services/OnesiBoxCommandServiceInterface.php`
- [ ] T008 Creare OnesiBoxCommandService in `app/Services/OnesiBoxCommandService.php`
- [ ] T009 Registrare binding Service in `app/Providers/AppServiceProvider.php`
- [ ] T010 [P] Creare SendOnesiBoxCommand Job in `app/Jobs/SendOnesiBoxCommand.php`
- [ ] T011 [P] Creare OnesiBoxStatusUpdated Event in `app/Events/OnesiBoxStatusUpdated.php`
- [ ] T012 [P] Creare OnesiBoxCommandSent Event in `app/Events/OnesiBoxCommandSent.php`
- [ ] T013 Configurare broadcast channel authorization in `routes/channels.php`
- [ ] T014 Creare route /dashboard in `routes/web.php`
- [ ] T015 Creare route /dashboard/{onesiBox} in `routes/web.php`

**Checkpoint**: Foundation ready - implementazione user story può iniziare

---

## Phase 3: User Story 1 - Visualizzazione lista OnesiBox (Priority: P1) 🎯 MVP

**Goal**: Il caregiver visualizza l'elenco delle OnesiBox assegnate con stato online/offline e attività

**Independent Test**: Autenticare caregiver e verificare che visualizzi correttamente le OnesiBox assegnate

### Tests for User Story 1

- [ ] T016 [P] [US1] Feature test lista OnesiBox con caregiver autenticato in `tests/Feature/Dashboard/OnesiBoxListTest.php`
- [ ] T017 [P] [US1] Feature test lista vuota senza OnesiBox assegnate in `tests/Feature/Dashboard/OnesiBoxListTest.php`
- [ ] T018 [P] [US1] Feature test stato online/offline in `tests/Feature/Dashboard/OnesiBoxListTest.php`
- [ ] T019 [P] [US1] Feature test autorizzazione - utente non può vedere OnesiBox non assegnate in `tests/Feature/Dashboard/AuthorizationTest.php`

### Implementation for User Story 1

- [ ] T020 [US1] Creare componente OnesiBoxList in `app/Livewire/Dashboard/OnesiBoxList.php`
- [ ] T021 [US1] Creare template onesi-box-list.blade.php in `resources/views/livewire/dashboard/onesi-box-list.blade.php`
- [ ] T022 [US1] Implementare computed property `onesiBoxes` con eager loading recipient e pivot
- [ ] T023 [US1] Implementare metodo `selectOnesiBox()` per redirect a dettaglio
- [ ] T024 [US1] Aggiungere wire:poll.10s.visible per fallback real-time
- [ ] T025 [US1] Aggiungere listener Echo per OnesiBoxStatusUpdated
- [ ] T026 [US1] Implementare UI mobile-first con Flux cards per lista OnesiBox
- [ ] T027 [US1] Aggiungere stato vuoto con Flux callout quando nessuna OnesiBox assegnata

**Checkpoint**: User Story 1 completamente funzionale e testabile

---

## Phase 4: User Story 2 - Dettaglio OnesiBox e Contatti Recipient (Priority: P2)

**Goal**: Il caregiver visualizza dettaglio OnesiBox con contatti recipient

**Independent Test**: Selezionare OnesiBox e verificare visualizzazione stato e contatti recipient

### Tests for User Story 2

- [ ] T028 [P] [US2] Feature test visualizzazione dettaglio OnesiBox in `tests/Feature/Dashboard/OnesiBoxDetailTest.php`
- [ ] T029 [P] [US2] Feature test visualizzazione contatti recipient in `tests/Feature/Dashboard/OnesiBoxDetailTest.php`
- [ ] T030 [P] [US2] Feature test OnesiBox senza recipient in `tests/Feature/Dashboard/OnesiBoxDetailTest.php`
- [ ] T031 [P] [US2] Feature test contatti emergenza in `tests/Feature/Dashboard/OnesiBoxDetailTest.php`
- [ ] T032 [P] [US2] Feature test autorizzazione view policy in `tests/Feature/Dashboard/AuthorizationTest.php`

### Implementation for User Story 2

- [ ] T033 [US2] Creare componente OnesiBoxDetail in `app/Livewire/Dashboard/OnesiBoxDetail.php`
- [ ] T034 [US2] Creare template onesi-box-detail.blade.php in `resources/views/livewire/dashboard/onesi-box-detail.blade.php`
- [ ] T035 [US2] Implementare mount() con authorize('view', $onesiBox)
- [ ] T036 [US2] Implementare computed properties: recipient, permission, canControl, isOnline
- [ ] T037 [US2] Implementare listener Echo per real-time status update
- [ ] T038 [US2] Implementare UI header con stato e ultimo heartbeat
- [ ] T039 [US2] Implementare card contatti recipient con Flux components
- [ ] T040 [US2] Implementare visualizzazione contatti emergenza
- [ ] T041 [US2] Implementare stato "nessun recipient" con Flux callout warning
- [ ] T042 [US2] Implementare metodo goBack() per tornare alla lista

**Checkpoint**: User Stories 1 e 2 funzionano indipendentemente

---

## Phase 5: User Story 3 - Controllo Riproduzione Audio (Priority: P3)

**Goal**: Il caregiver con permesso Full può avviare riproduzione audio

**Independent Test**: Avviare riproduzione audio e verificare invio comando all'appliance

### Tests for User Story 3

- [ ] T043 [P] [US3] Feature test avvio audio con permesso Full in `tests/Feature/Dashboard/AudioControlTest.php`
- [ ] T044 [P] [US3] Feature test blocco audio con permesso ReadOnly in `tests/Feature/Dashboard/AudioControlTest.php`
- [ ] T045 [P] [US3] Feature test blocco audio con OnesiBox offline in `tests/Feature/Dashboard/AudioControlTest.php`
- [ ] T046 [P] [US3] Feature test validazione URL audio in `tests/Feature/Dashboard/AudioControlTest.php`

### Implementation for User Story 3

- [ ] T047 [US3] Creare componente AudioPlayer in `app/Livewire/Dashboard/Controls/AudioPlayer.php`
- [ ] T048 [US3] Creare template audio-player.blade.php in `resources/views/livewire/dashboard/controls/audio-player.blade.php`
- [ ] T049 [US3] Implementare proprietà audioUrl con validazione (required, url, max:2048)
- [ ] T050 [US3] Implementare metodo playAudio() con authorize('control', $onesiBox)
- [ ] T051 [US3] Integrare OnesiBoxCommandService per invio comando audio
- [ ] T052 [US3] Implementare UI form con Flux input e button
- [ ] T053 [US3] Aggiungere wire:loading states per feedback visivo
- [ ] T054 [US3] Implementare error handling con Flux toast notifications

**Checkpoint**: User Stories 1, 2 e 3 funzionano indipendentemente

---

## Phase 6: User Story 4 - Controllo Riproduzione Video (Priority: P4)

**Goal**: Il caregiver con permesso Full può avviare riproduzione video

**Independent Test**: Avviare riproduzione video e verificare invio comando all'appliance

### Tests for User Story 4

- [ ] T055 [P] [US4] Feature test avvio video con permesso Full in `tests/Feature/Dashboard/VideoControlTest.php`
- [ ] T056 [P] [US4] Feature test blocco video con permesso ReadOnly in `tests/Feature/Dashboard/VideoControlTest.php`
- [ ] T057 [P] [US4] Feature test validazione URL video in `tests/Feature/Dashboard/VideoControlTest.php`

### Implementation for User Story 4

- [ ] T058 [US4] Creare componente VideoPlayer in `app/Livewire/Dashboard/Controls/VideoPlayer.php`
- [ ] T059 [US4] Creare template video-player.blade.php in `resources/views/livewire/dashboard/controls/video-player.blade.php`
- [ ] T060 [US4] Implementare proprietà videoUrl con validazione (required, url, max:2048)
- [ ] T061 [US4] Implementare metodo playVideo() con authorize('control', $onesiBox)
- [ ] T062 [US4] Integrare OnesiBoxCommandService per invio comando video
- [ ] T063 [US4] Implementare UI form con Flux input e button
- [ ] T064 [US4] Aggiungere wire:loading states e error handling

**Checkpoint**: User Stories 1-4 funzionano indipendentemente

---

## Phase 7: User Story 5 - Avvio Chiamata Zoom (Priority: P5)

**Goal**: Il caregiver con permesso Full può avviare chiamata Zoom

**Independent Test**: Avviare chiamata Zoom e verificare invio comando all'appliance

### Tests for User Story 5

- [ ] T065 [P] [US5] Feature test avvio Zoom con permesso Full in `tests/Feature/Dashboard/ZoomControlTest.php`
- [ ] T066 [P] [US5] Feature test blocco Zoom con permesso ReadOnly in `tests/Feature/Dashboard/ZoomControlTest.php`
- [ ] T067 [P] [US5] Feature test blocco Zoom con OnesiBox offline in `tests/Feature/Dashboard/ZoomControlTest.php`
- [ ] T068 [P] [US5] Feature test validazione meeting ID in `tests/Feature/Dashboard/ZoomControlTest.php`

### Implementation for User Story 5

- [ ] T069 [US5] Creare componente ZoomCall in `app/Livewire/Dashboard/Controls/ZoomCall.php`
- [ ] T070 [US5] Creare template zoom-call.blade.php in `resources/views/livewire/dashboard/controls/zoom-call.blade.php`
- [ ] T071 [US5] Implementare proprietà meetingId e password con validazione
- [ ] T072 [US5] Implementare metodo startCall() con authorize('control', $onesiBox)
- [ ] T073 [US5] Implementare metodo endCall() per terminare chiamata
- [ ] T074 [US5] Integrare OnesiBoxCommandService per invio comando Zoom
- [ ] T075 [US5] Implementare UI form con Flux inputs e buttons
- [ ] T076 [US5] Aggiungere wire:loading states e error handling

**Checkpoint**: Tutte le User Stories funzionano indipendentemente

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Miglioramenti che impattano multiple user stories

- [ ] T077 [P] Creare Browser test per mobile responsiveness in `tests/Browser/Dashboard/MobileResponsiveTest.php`
- [ ] T078 [P] Verificare SC-003: operazioni completabili su smartphone senza scroll orizzontale
- [ ] T079 [P] Verificare SC-004: form completabili con meno di 4 tap
- [ ] T080 Integrare controlli (Audio, Video, Zoom) nella vista OnesiBoxDetail
- [ ] T081 Aggiungere condizionale @can('control') per nascondere controlli a ReadOnly
- [ ] T082 Aggiungere condizionale isOnline per disabilitare controlli quando offline
- [ ] T083 Eseguire `vendor/bin/pint --dirty` per code formatting
- [ ] T084 Eseguire `php artisan test --compact` per verificare tutti i test
- [ ] T085 Validare quickstart.md con test manuale del flusso completo

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Nessuna dipendenza - può iniziare immediatamente
- **Foundational (Phase 2)**: Dipende da Setup - BLOCCA tutte le user stories
- **User Stories (Phase 3-7)**: Dipendono da Foundational
  - Possono procedere in parallelo (se team disponibile)
  - O sequenzialmente per priorità (P1 → P2 → P3 → P4 → P5)
- **Polish (Phase 8)**: Dipende da completamento user stories desiderate

### User Story Dependencies

- **User Story 1 (P1)**: Dopo Foundational - Nessuna dipendenza da altre stories
- **User Story 2 (P2)**: Dopo Foundational - Indipendente, ma UI integra con US1
- **User Story 3 (P3)**: Dopo Foundational - Richiede OnesiBoxDetail (US2) per integrazione UI
- **User Story 4 (P4)**: Dopo Foundational - Richiede OnesiBoxDetail (US2) per integrazione UI
- **User Story 5 (P5)**: Dopo Foundational - Richiede OnesiBoxDetail (US2) per integrazione UI

### Within Each User Story

- Tests DEVONO essere scritti e FALLIRE prima dell'implementazione
- Componente Livewire prima del template
- Logica core prima dell'integrazione UI
- Story completa prima di passare alla prossima priorità

### Parallel Opportunities

**Phase 1 (Setup)**:
```
Parallel: T002, T003, T005, T006
```

**Phase 2 (Foundational)**:
```
Parallel: T010, T011, T012
```

**Phase 3-7 (User Stories)**:
- Tutti i test [P] per una story possono essere eseguiti in parallelo
- User stories diverse possono essere lavorate in parallelo da team member diversi

---

## Parallel Example: User Story 1

```bash
# Launch tutti i test per US1 insieme:
Task: "Feature test lista OnesiBox con caregiver autenticato"
Task: "Feature test lista vuota senza OnesiBox assegnate"
Task: "Feature test stato online/offline"
Task: "Feature test autorizzazione"
```

## Parallel Example: User Story 3

```bash
# Launch tutti i test per US3 insieme:
Task: "Feature test avvio audio con permesso Full"
Task: "Feature test blocco audio con permesso ReadOnly"
Task: "Feature test blocco audio con OnesiBox offline"
Task: "Feature test validazione URL audio"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Completare Phase 1: Setup
2. Completare Phase 2: Foundational (CRITICAL - blocca tutte le stories)
3. Completare Phase 3: User Story 1
4. **STOP e VALIDATE**: Test User Story 1 indipendentemente
5. Deploy/demo se pronto

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. User Story 1 → Test → Deploy (MVP!)
3. User Story 2 → Test → Deploy (Dettaglio + Contatti)
4. User Story 3 → Test → Deploy (Audio)
5. User Story 4 → Test → Deploy (Video)
6. User Story 5 → Test → Deploy (Zoom)
7. Ogni story aggiunge valore senza rompere le precedenti

### Parallel Team Strategy

Con più sviluppatori:

1. Team completa Setup + Foundational insieme
2. Una volta Foundational completato:
   - Developer A: User Story 1 (Lista)
   - Developer B: User Story 2 (Dettaglio)
3. Dopo US1 e US2:
   - Developer A: User Story 3 (Audio)
   - Developer B: User Story 4 (Video)
   - Developer C: User Story 5 (Zoom)
4. Stories completano e integrano indipendentemente

---

## Notes

- [P] tasks = file diversi, nessuna dipendenza
- [Story] label mappa task a user story specifica per tracciabilità
- Ogni user story deve essere completabile e testabile indipendentemente
- Verificare che i test falliscano prima di implementare
- Commit dopo ogni task o gruppo logico
- Stop a qualsiasi checkpoint per validare story indipendentemente
- Evitare: task vaghi, conflitti stesso file, dipendenze cross-story che rompono indipendenza
