# Implementation Plan: Sessioni Video a Tempo con Playlist

**Branch**: `010-timed-playlist-sessions` | **Date**: 2026-02-05 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/010-timed-playlist-sessions/spec.md`

## Summary

Implementare sessioni di riproduzione video a tempo sulla OnesiBox. Il caregiver crea una playlist (manuale o da sezione JW.org), seleziona una durata (30min/1h/2h/3h) e avvia la sessione. Il backend gestisce tutta la logica: quando la OnesiBox riporta il completamento di un video, il backend verifica il tempo rimanente e invia automaticamente il prossimo video tramite il sistema comandi esistente. Nessuna logica di sessione sulla OnesiBox — l'unica modifica al client è l'aggiunta del rilevamento completamento video (`ended` event listener).

## Technical Context

**Language/Version**: PHP 8.4 (backend), Node.js 20+ (OnesiBox client)
**Primary Dependencies**: Laravel 12, Livewire 4, Flux UI 2, Sanctum 4, Playwright (OnesiBox)
**Storage**: SQLite (dev), MySQL/PostgreSQL (prod) — 3 nuove tabelle
**Testing**: Pest 4 (backend), manuale (OnesiBox client)
**Target Platform**: Web (dashboard caregiver), Linux ARM (OnesiBox Raspberry Pi)
**Project Type**: Web application (backend Laravel + client Node.js)
**Performance Goals**: Transizione tra video < 10s (5s polling + latenza), tempo stop sessione < 30s
**Constraints**: OnesiBox ha zero logica di sessione; tutto il controllo risiede nel backend
**Scale/Scope**: 1 sessione attiva per OnesiBox, max 100 video per playlist, max ~50 OnesiBox concorrenti

## Constitution Check

*Constitution non configurata (template vuoto). Nessun gate da verificare.*

**Post-Design Re-check**: Il design rispetta i principi generali del progetto:
- Utilizza pattern esistenti (Actions, Services, Livewire components)
- Segue le convenzioni del codebase (naming, struttura directory)
- Nessuna dipendenza esterna aggiuntiva richiesta
- Minima modifica al client OnesiBox

## Project Structure

### Documentation (this feature)

```text
specs/010-timed-playlist-sessions/
├── plan.md              # Questo file
├── spec.md              # Specifica funzionale
├── research.md          # Ricerca e decisioni tecniche
├── data-model.md        # Modello dati (3 nuove tabelle)
├── quickstart.md        # Guida sviluppo rapido
├── contracts/
│   └── api-v1.md        # Contratti API/Livewire
├── checklists/
│   └── requirements.md  # Checklist qualità specifica
└── tasks.md             # Task di implementazione (da /speckit.tasks)
```

### Source Code (repository root)

```text
# Backend (onesiforo - Laravel)
app/
├── Models/
│   ├── Playlist.php                          # Nuovo
│   ├── PlaylistItem.php                      # Nuovo
│   └── PlaybackSession.php                   # Nuovo
├── Enums/
│   ├── PlaybackSessionStatus.php             # Nuovo
│   └── PlaylistSourceType.php                # Nuovo
├── Actions/
│   ├── Sessions/
│   │   ├── StartPlaybackSessionAction.php    # Nuovo
│   │   ├── StopPlaybackSessionAction.php     # Nuovo
│   │   └── AdvancePlaybackSessionAction.php  # Nuovo
│   └── Playlists/
│       ├── CreatePlaylistAction.php          # Nuovo
│       └── ExtractJwOrgVideosAction.php      # Nuovo
├── Services/
│   └── JwOrgMediaExtractor.php               # Nuovo
├── Rules/
│   └── JwOrgSectionUrl.php                   # Nuovo
├── Livewire/Dashboard/Controls/
│   ├── SessionManager.php                    # Nuovo
│   ├── PlaylistBuilder.php                   # Nuovo
│   ├── SessionStatus.php                     # Nuovo
│   └── SavedPlaylists.php                    # Nuovo
└── Http/Controllers/Api/V1/
    └── PlaybackController.php                # Modifica: trigger advance session

database/migrations/
├── xxxx_create_playlists_table.php           # Nuovo
├── xxxx_create_playlist_items_table.php      # Nuovo
└── xxxx_create_playback_sessions_table.php   # Nuovo

database/factories/
├── PlaylistFactory.php                       # Nuovo
├── PlaylistItemFactory.php                   # Nuovo
└── PlaybackSessionFactory.php                # Nuovo

resources/views/livewire/dashboard/controls/
├── session-manager.blade.php                 # Nuovo
├── playlist-builder.blade.php                # Nuovo
├── session-status.blade.php                  # Nuovo
└── saved-playlists.blade.php                 # Nuovo

tests/Feature/
├── Sessions/
│   ├── StartPlaybackSessionTest.php          # Nuovo
│   ├── StopPlaybackSessionTest.php           # Nuovo
│   └── AdvancePlaybackSessionTest.php        # Nuovo
├── Playlists/
│   ├── PlaylistManagementTest.php            # Nuovo
│   └── JwOrgMediaExtractorTest.php           # Nuovo
└── Api/
    └── PlaybackSessionIntegrationTest.php    # Nuovo

# Client (onesi-box - Node.js) — modifiche minimali
src/commands/handlers/media.js                # Modifica: video ended detection + completed event
```

**Structure Decision**: Il progetto segue la struttura Laravel 12 esistente. I nuovi file si inseriscono nelle directory convenzionali già presenti. Il client OnesiBox richiede una singola modifica al media handler.

## Design Decisions

### D1: Avanzamento sessione tramite comandi esistenti (non nuovo endpoint)

Quando un video finisce, la OnesiBox riporta un evento `completed` tramite l'endpoint esistente `POST /api/v1/appliances/playback`. Il `PlaybackController` invoca `AdvancePlaybackSessionAction` che verifica la sessione attiva e crea un nuovo comando `play_media` se appropriato. La OnesiBox riceve il nuovo comando al prossimo polling (≤5 secondi).

**Vantaggi**: zero logica di sessione sulla OnesiBox, riutilizzo completo dell'infrastruttura esistente.

### D2: Playlist come entità separata dalla sessione

La Playlist è un'entità indipendente che può essere salvata e riutilizzata. Quando si avvia una sessione, si crea una Playlist (anche temporanea se non salvata) e una PlaybackSession che la referenzia. Questo permette di:
- Riutilizzare playlist salvate per più sessioni
- Mantenere lo storico delle sessioni anche dopo la modifica/cancellazione di una playlist

### D3: JW.org Mediator API per estrazione video

Utilizzo della API pubblica `b.jw-cdn.org/apis/mediator/v1/` invece di web scraping. API stabile, JSON strutturato, nessuna autenticazione richiesta. Già parzialmente supportata dal proxy nella OnesiBox (`GET /api/jw-media`).

### D4: Video URL formato JW.org page (non MP4 diretto)

I video estratti da JW.org vengono salvati come URL della pagina JW.org (es. `https://www.jw.org/it/biblioteca/video/#it/mediaitems/...`) e non come URL MP4 diretto. Questo perché:
- La OnesiBox gestisce già la conversione tramite il player locale (`localhost:3000/player.html`)
- Gli URL MP4 diretti possono cambiare; l'URL della pagina è stabile
- Compatibile con la validazione URL esistente (`JwOrgUrl` rule)

## Complexity Tracking

Nessuna violazione di constitution da giustificare. Il design è lineare:
- 3 nuove tabelle, 3 nuovi modelli
- 5 nuove Actions (pattern esistente)
- 1 nuovo Service
- 4 nuovi componenti Livewire (pattern esistente)
- 1 modifica al PlaybackController
- 1 modifica al client OnesiBox (video ended detection)
