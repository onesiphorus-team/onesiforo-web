# Implementation Plan: OnesiBox Caregiver Controls

**Branch**: `009-onesibox-caregiver-controls` | **Date**: 2026-01-25 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/009-onesibox-caregiver-controls/spec.md`

## Summary

Estensione della dashboard caregiver per includere controlli avanzati di monitoraggio e gestione delle appliance OnesiBox. La feature aggiunge:
- Visualizzazione stato dettagliato (idle/video/audio/Zoom con info contestuali)
- Controllo volume con 5 livelli predefiniti (20-40-60-80-100%)
- Gestione coda comandi (visualizza, elimina singolo, elimina tutti)
- Informazioni di sistema estese (uptime, load average, memoria, CPU, disco, rete)
- Richiesta log remoti con filtro dati sensibili

L'implementazione coinvolge sia Onesiforo (Laravel) che OnesiBox (Node.js).

## Technical Context

**Language/Version**: PHP 8.4.17 (Onesiforo), Node.js 20+ (OnesiBox)
**Primary Dependencies**: Laravel 12, Livewire 4, Flux UI 2, Winston (logging OnesiBox)
**Storage**: SQLite (dev), MySQL/PostgreSQL (prod) - tabelle esistenti: `onesi_boxes`, `commands`, `playback_events`
**Testing**: Pest 4 (PHP), test unitari (Node.js)
**Target Platform**: Web (Onesiforo), Raspberry Pi OS (OnesiBox)
**Project Type**: Web application con componente embedded IoT
**Performance Goals**: Stato aggiornato entro 5s, feedback volume entro 2s, caricamento lista comandi entro 1s
**Constraints**: Mobile-first UI, rispetto permessi Full/ReadOnly, filtro dati sensibili nei log
**Scale/Scope**: ~10-50 OnesiBox, ~100 utenti caregiver

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Status | Notes |
|------|--------|-------|
| Test-First | Pass | Pest tests per ogni componente Livewire e API |
| Simplicity/YAGNI | Pass | Solo 5 livelli volume predefiniti, no streaming log |
| Observability | Pass | Winston già presente, estendiamo logging |

## Project Structure

### Documentation (this feature)

```text
specs/009-onesibox-caregiver-controls/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (API contracts)
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (Onesiforo - Laravel)

```text
app/
├── Livewire/Dashboard/
│   ├── OnesiBoxDetail.php           # Estendere con nuovi controlli
│   ├── Controls/
│   │   ├── VolumeControl.php        # NUOVO: Controllo volume 5 livelli
│   │   ├── CommandQueue.php         # NUOVO: Gestione coda comandi
│   │   ├── SystemInfo.php           # NUOVO: Info sistema
│   │   └── LogViewer.php            # NUOVO: Visualizzazione log
│   └── ...
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── HeartbeatController.php  # Estendere per stato media
│   │   ├── CommandController.php    # Estendere per cancellazione
│   │   └── SystemInfoController.php # NUOVO: Info sistema e log
│   └── Requests/Api/V1/
│       ├── HeartbeatRequest.php     # Estendere validazione
│       └── SystemInfoRequest.php    # NUOVO
├── Http/Resources/Api/V1/
│   ├── SystemInfoResource.php       # NUOVO
│   └── LogEntryResource.php         # NUOVO
├── Enums/
│   └── CommandType.php              # Aggiungere get_system_info, get_logs
├── Actions/
│   └── Commands/
│       ├── CreateVolumeCommand.php  # NUOVO
│       └── CancelCommand.php        # NUOVO
└── Events/
    └── OnesiBoxStatusUpdated.php    # Estendere per stato media

resources/views/livewire/dashboard/
├── onesi-box-detail.blade.php       # Estendere layout
└── controls/
    ├── volume-control.blade.php     # NUOVO
    ├── command-queue.blade.php      # NUOVO
    ├── system-info.blade.php        # NUOVO
    └── log-viewer.blade.php         # NUOVO

tests/
├── Feature/
│   ├── Livewire/Dashboard/
│   │   ├── VolumeControlTest.php    # NUOVO
│   │   ├── CommandQueueTest.php     # NUOVO
│   │   ├── SystemInfoTest.php       # NUOVO
│   │   └── LogViewerTest.php        # NUOVO
│   └── Api/V1/
│       ├── SystemInfoApiTest.php    # NUOVO
│       └── CommandCancelTest.php    # NUOVO
└── Unit/
    └── Actions/
        └── CreateVolumeCommandTest.php  # NUOVO
```

### Source Code (OnesiBox - Node.js)

```text
src/
├── commands/handlers/
│   ├── system-info.js               # NUOVO: Handler info sistema
│   └── logs.js                      # NUOVO: Handler recupero log
├── logging/
│   ├── logger.js                    # Estendere: log più dettagliati
│   └── log-sanitizer.js             # NUOVO: Filtro dati sensibili
├── communication/
│   └── api-client.js                # Estendere per nuovi endpoint
└── main.js                          # Registrare nuovi handler

tests/
├── system-info.test.js              # NUOVO
├── logs.test.js                     # NUOVO
└── log-sanitizer.test.js            # NUOVO
```

**Structure Decision**: Estensione architettura esistente. Livewire components per UI, API REST per comunicazione con OnesiBox. I nuovi handler OnesiBox seguono pattern esistente in `src/commands/handlers/`.

## Complexity Tracking

> Nessuna violazione dei gate constitution rilevata.

| Item | Justification |
|------|---------------|
| 2 progetti (Laravel + Node.js) | Architettura pre-esistente, necessaria per separazione web/embedded |
