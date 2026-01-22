# Implementation Plan: OnesiBox API Webservices

**Branch**: `003-onesibox-api-ws` | **Date**: 2026-01-22 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/003-onesibox-api-ws/spec.md`

## Summary

Implementare le API REST che consentono alle appliance OnesiBox di comunicare con Onesiforo in modalita polling (Fase 1). Le API includono:
- GET endpoint per recuperare comandi pendenti
- POST endpoint per confermare esecuzione comandi (acknowledgment)
- POST endpoint per aggiornare stato riproduzione multimediale

L'implementazione segue il pattern esistente di HeartbeatController/HeartbeatRequest/HeartbeatResource con autenticazione Sanctum e documentazione Scramble.

## Technical Context

**Language/Version**: PHP 8.4.17
**Primary Dependencies**: Laravel 12.47.0, Sanctum 4.2.4, Filament 5.0.0, dedoc/scramble
**Storage**: SQLite (development), con supporto per MySQL/PostgreSQL in produzione
**Testing**: Pest 4.3.1, PHPUnit 12.5.4
**Target Platform**: Linux server (Laravel Herd in sviluppo)
**Project Type**: Web application (Laravel monolith con API REST)
**Performance Goals**: 1000 req/min per appliance, latenza < 1 secondo
**Constraints**: Rate limiting 120 req/min per utente autenticato
**Scale/Scope**: 100 appliance simultanee, 50 caregiver simultanei

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

La constitution non e ancora configurata per questo progetto. Procedo con le best practices Laravel standard:
- [x] Seguire pattern esistenti (HeartbeatController)
- [x] Test coverage minimo 80%
- [x] PHPStan level 8
- [x] Documentazione API con Scramble

## Project Structure

### Documentation (this feature)

```text
specs/003-onesibox-api-ws/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (OpenAPI schemas)
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── OnesiBoxStatus.php          # Existing
│   ├── OnesiBoxPermission.php      # Existing
│   ├── CommandType.php             # NEW: Enum tipi comando
│   ├── CommandStatus.php           # NEW: Enum stati comando
│   └── PlaybackEventType.php       # NEW: Enum eventi playback
├── Models/
│   ├── OnesiBox.php                # Existing (da estendere)
│   ├── Command.php                 # NEW: Model comando
│   └── PlaybackEvent.php           # NEW: Model evento playback
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── HeartbeatController.php # Existing
│   │   ├── CommandController.php   # NEW: GET commands, POST ack
│   │   └── PlaybackController.php  # NEW: POST playback events
│   ├── Requests/Api/V1/
│   │   ├── HeartbeatRequest.php    # Existing
│   │   ├── GetCommandsRequest.php  # NEW
│   │   ├── AckCommandRequest.php   # NEW
│   │   └── PlaybackEventRequest.php # NEW
│   └── Resources/Api/V1/
│       ├── HeartbeatResource.php   # Existing
│       ├── CommandResource.php     # NEW
│       ├── CommandCollection.php   # NEW
│       └── PlaybackEventResource.php # NEW
└── Services/
    └── CommandExpirationService.php # NEW: Logic for expiring commands

database/
├── migrations/
│   ├── xxxx_create_commands_table.php        # NEW
│   └── xxxx_create_playback_events_table.php # NEW
└── factories/
    ├── CommandFactory.php          # NEW
    └── PlaybackEventFactory.php    # NEW

routes/
└── api.php                         # Extend with new endpoints

tests/
└── Feature/Api/V1/
    ├── HeartbeatApiTest.php        # Existing
    ├── CommandApiTest.php          # NEW
    └── PlaybackApiTest.php         # NEW
```

**Structure Decision**: Laravel monolith con API REST versionata (v1). Segue la struttura esistente con Controller/Request/Resource pattern per ogni endpoint API.

## Complexity Tracking

Nessuna violazione della constitution rilevata. L'implementazione segue i pattern esistenti senza introdurre complessita aggiuntiva.
