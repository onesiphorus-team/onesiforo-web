# Data Model: Sessioni Video a Tempo con Playlist

**Feature Branch**: `010-timed-playlist-sessions`
**Date**: 2026-02-05

## New Entities

### playlists

| Column       | Type                | Nullable | Default | Notes                                |
|-------------|---------------------|----------|---------|--------------------------------------|
| id          | bigint unsigned PK  | no       | auto    |                                      |
| onesi_box_id| bigint unsigned FK  | no       |         | FK → onesi_boxes.id (cascade delete) |
| name        | string(255)         | yes      | null    | Nome della playlist (richiesto se is_saved=true) |
| source_type | string(20)          | no       |         | 'manual' \| 'jworg_section'          |
| source_url  | string(2048)        | yes      | null    | URL sezione JW.org (se source_type=jworg_section) |
| is_saved    | boolean             | no       | false   | true = playlist riutilizzabile       |
| timestamps  |                     |          |         | created_at, updated_at               |

**Indexes:**
- `(onesi_box_id, is_saved)` — query playlist salvate per OnesiBox

**Relationships:**
- `belongsTo` OnesiBox
- `hasMany` PlaylistItem
- `hasMany` PlaybackSession

**Validazione:**
- `name` obbligatorio se `is_saved = true`
- `source_url` obbligatorio se `source_type = 'jworg_section'`
- `source_url` deve essere un URL JW.org valido (pattern `#XX/categories/...`)

---

### playlist_items

| Column          | Type                | Nullable | Default | Notes                            |
|----------------|---------------------|----------|---------|----------------------------------|
| id             | bigint unsigned PK  | no       | auto    |                                  |
| playlist_id    | bigint unsigned FK  | no       |         | FK → playlists.id (cascade delete) |
| media_url      | string(2048)        | no       |         | URL del video                    |
| title          | string(500)         | yes      | null    | Titolo del video                 |
| duration_seconds| unsigned int       | yes      | null    | Durata in secondi (se nota)      |
| position       | unsigned int        | no       |         | Ordine nella playlist (0-based)  |
| created_at     | timestamp           | no       |         |                                  |

**Indexes:**
- `(playlist_id, position)` unique — ordine univoco per playlist

**Relationships:**
- `belongsTo` Playlist

**Validazione:**
- `media_url` deve essere un URL JW.org o dominio consentito (whitelist esistente)
- `position` deve essere univoco all'interno della playlist
- Massimo 100 items per playlist

---

### playback_sessions

| Column          | Type                | Nullable | Default   | Notes                                    |
|----------------|---------------------|----------|-----------|------------------------------------------|
| id             | bigint unsigned PK  | no       | auto      |                                          |
| uuid           | uuid                | no       | auto      | Per routing API                          |
| onesi_box_id   | bigint unsigned FK  | no       |           | FK → onesi_boxes.id (cascade delete)     |
| playlist_id    | bigint unsigned FK  | no       |           | FK → playlists.id (restrict delete)      |
| status         | string(20)          | no       | 'active'  | Enum: active, completed, stopped, error  |
| duration_minutes| unsigned int       | no       |           | 30, 60, 120, 180                         |
| started_at     | timestamp           | no       |           | Quando la sessione è iniziata            |
| ended_at       | timestamp           | yes      | null      | Quando la sessione è terminata           |
| current_position| unsigned int       | no       | 0         | Indice del video corrente (0-based)      |
| items_played   | unsigned int        | no       | 0         | Video riprodotti con successo            |
| items_skipped  | unsigned int        | no       | 0         | Video saltati per errore                 |
| timestamps     |                     |          |           | created_at, updated_at                   |

**Indexes:**
- `(onesi_box_id, status)` — trovare sessione attiva per OnesiBox
- `uuid` unique — routing API

**Relationships:**
- `belongsTo` OnesiBox
- `belongsTo` Playlist

**Validazione:**
- Una sola sessione `active` per OnesiBox alla volta
- `duration_minutes` deve essere uno dei valori consentiti: 30, 60, 120, 180
- `current_position` deve essere ≤ numero totale di items nella playlist

---

## State Transitions

### PlaybackSession Status

```
                    ┌──────────────┐
                    │   active     │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
       ┌────────────┐ ┌─────────┐ ┌─────────┐
       │ completed   │ │ stopped │ │  error  │
       │(tempo/video │ │(manuale)│ │(critico)│
       │ esauriti)   │ │         │ │         │
       └────────────┘ └─────────┘ └─────────┘
```

- `active` → `completed`: tutti i video riprodotti OPPURE tempo scaduto (al completamento del video corrente)
- `active` → `stopped`: il caregiver interrompe manualmente la sessione
- `active` → `error`: errore critico irrecuperabile (tutti i video rimanenti falliscono)

---

## Enum Values

### PlaybackSessionStatus
```
Active, Completed, Stopped, Error
```

### PlaylistSourceType
```
Manual, JworgSection
```

---

## Modified Entities

### commands (nessuna modifica allo schema)

Il payload del comando `play_media` durante una sessione includerà un campo aggiuntivo `session_id` per tracciabilità:

```json
{
  "url": "https://www.jw.org/...",
  "media_type": "video",
  "session_id": "uuid-della-sessione"
}
```

Nota: `session_id` nel payload è informativo. La OnesiBox lo ignora; serve al backend per correlare comandi e sessioni.

### playback_events (nessuna modifica allo schema)

Gli eventi `completed` ed `error` per video di una sessione attiveranno l'`AdvancePlaybackSessionAction`. La correlazione avviene tramite `onesi_box_id` + sessione attiva.

---

## Impatto sulle Relazioni Esistenti

### OnesiBox (modifiche al model)

Nuove relazioni:
- `hasMany` Playlist
- `hasMany` PlaybackSession
- `hasOne` PlaybackSession (scope: `active`) — sessione corrente

Nuovo metodo:
- `activeSession()`: ritorna la sessione attiva (se presente)

---

## Query Frequenti

1. **Sessione attiva per OnesiBox**: `PlaybackSession::where('onesi_box_id', $id)->where('status', 'active')->first()`
2. **Playlist salvate per OnesiBox**: `Playlist::where('onesi_box_id', $id)->where('is_saved', true)->get()`
3. **Prossimo video nella sessione**: `PlaylistItem::where('playlist_id', $session->playlist_id)->where('position', $session->current_position)->first()`
4. **Tempo rimanente sessione**: `$session->started_at->addMinutes($session->duration_minutes)->diffInSeconds(now())`
