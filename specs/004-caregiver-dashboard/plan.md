# Implementation Plan: Caregiver Dashboard

**Branch**: `004-caregiver-dashboard` | **Date**: 2026-01-22 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/004-caregiver-dashboard/spec.md`

## Summary

Dashboard per caregiver che consente di visualizzare e controllare le appliance OnesiBox assegnate. L'implementazione utilizza Livewire 4 con componenti separati (non Volt), Flux UI per i form responsive mobile-first, e Laravel Reverb per aggiornamenti real-time dello stato delle appliance.

## Technical Context

**Language/Version**: PHP 8.4.17
**Primary Dependencies**: Laravel 12.47.0, Livewire 4.0.1, Flux UI 2.10.2, Laravel Reverb 1.7.0
**Storage**: SQLite (development), tabelle esistenti: users, onesi_boxes, recipients, onesi_box_user
**Testing**: Pest 4.3.1 (Unit, Feature, Browser tests)
**Target Platform**: Web application (mobile-first responsive)
**Project Type**: Laravel monolith (TALL Stack)
**Performance Goals**: <2s caricamento lista, <3s aggiornamento real-time stato
**Constraints**: Mobile-first, <4 tap per completare azioni
**Scale/Scope**: Multi-tenant (caregiver vede solo OnesiBox assegnate)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Status | Notes |
|------|--------|-------|
| DRY | PASS | Componenti Livewire riutilizzabili per card OnesiBox e form controlli |
| SOLID | PASS | Single responsibility per componente, Policy per autorizzazione |
| YAGNI | PASS | Solo funzionalità richieste, no catalogo contenuti |
| Test Coverage | PASS | Feature tests per ogni user story, browser tests per mobile |
| Livewire (non Volt) | PASS | File PHP separati da Blade template |

## Project Structure

### Documentation (this feature)

```text
specs/004-caregiver-dashboard/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Events/
│   └── OnesiBoxStatusUpdated.php    # Broadcast event per real-time
├── Livewire/
│   └── Dashboard/
│       ├── OnesiBoxList.php          # P1: Lista OnesiBox del caregiver
│       ├── OnesiBoxDetail.php        # P2: Dettaglio con contatti recipient
│       └── Controls/
│           ├── AudioPlayer.php       # P3: Form riproduzione audio
│           ├── VideoPlayer.php       # P4: Form riproduzione video
│           └── ZoomCall.php          # P5: Form avvio Zoom
├── Policies/
│   └── OnesiBoxPolicy.php            # Autorizzazione view/control
└── Services/
    └── OnesiBoxCommandService.php    # Invio comandi all'appliance

resources/views/
├── dashboard.blade.php               # Layout dashboard
└── livewire/
    └── dashboard/
        ├── onesi-box-list.blade.php
        ├── onesi-box-detail.blade.php
        └── controls/
            ├── audio-player.blade.php
            ├── video-player.blade.php
            └── zoom-call.blade.php

tests/
├── Feature/
│   └── Dashboard/
│       ├── OnesiBoxListTest.php      # FR-001, FR-002, FR-003
│       ├── OnesiBoxDetailTest.php    # FR-004, FR-005
│       ├── AudioControlTest.php      # FR-007, FR-010, FR-011
│       ├── VideoControlTest.php      # FR-008
│       ├── ZoomControlTest.php       # FR-009
│       └── AuthorizationTest.php     # SC-007, SC-008
└── Browser/
    └── Dashboard/
        └── MobileResponsiveTest.php  # FR-012, SC-003, SC-004
```

**Structure Decision**: Laravel standard structure con Livewire components organizzati in namespace `Dashboard/`. I controlli (audio, video, Zoom) sono sotto-componenti riutilizzabili nella vista dettaglio.

## Complexity Tracking

> Nessuna violazione della Constitution rilevata.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
