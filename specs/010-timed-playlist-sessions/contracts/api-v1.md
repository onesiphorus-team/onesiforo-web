# API Contracts: Sessioni Video a Tempo con Playlist

**Feature Branch**: `010-timed-playlist-sessions`
**Date**: 2026-02-05
**Base URL**: `/api/v1`

## Endpoints Overview

### OnesiBox API (autenticazione Sanctum token — usata dalla OnesiBox)

Nessun nuovo endpoint per la OnesiBox. Il flusso utilizza gli endpoint esistenti:
- `GET /appliances/commands` — riceve comandi `play_media` / `stop_media`
- `POST /commands/{command}/ack` — conferma esecuzione
- `POST /appliances/playback` — riporta eventi (incluso `completed`)

L'unica modifica è che il backend reagisce agli eventi `completed` ed `error` creando nuovi comandi quando una sessione è attiva.

---

### Caregiver Dashboard API (autenticazione sessione web — usata dalla dashboard Livewire)

Questi endpoint non sono REST tradizionali ma **Livewire actions** invocate dai componenti della dashboard. Li documentiamo come contratti funzionali.

---

## C1: Avvio Sessione

**Component**: `SessionManager`
**Action**: `startSession`

### Input
```json
{
  "onesi_box_id": 1,
  "duration_minutes": 60,
  "playlist_source": "manual",
  "video_urls": [
    "https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODMinistryTools/pub-mwbv_202401_1_VIDEO",
    "https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODBible/pub-nwtsv_I_1_VIDEO"
  ]
}
```

### Input (variante JW.org section)
```json
{
  "onesi_box_id": 1,
  "duration_minutes": 120,
  "playlist_source": "jworg_section",
  "section_url": "https://www.jw.org/it/biblioteca/video/#it/categories/VODBible"
}
```

### Input (variante da playlist salvata)
```json
{
  "onesi_box_id": 1,
  "duration_minutes": 60,
  "playlist_id": 5
}
```

### Validation Rules
```
onesi_box_id    : required|exists:onesi_boxes,id
duration_minutes: required|in:30,60,120,180
playlist_source : required_without:playlist_id|in:manual,jworg_section
video_urls      : required_if:playlist_source,manual|array|min:1|max:100
video_urls.*    : url|max:2048 (+ JwOrgUrl validation)
section_url     : required_if:playlist_source,jworg_section|url|max:2048 (+ JwOrgSectionUrl validation)
playlist_id     : required_without:playlist_source|exists:playlists,id
```

### Authorization
- Utente autenticato deve essere caregiver con permesso `full` sulla OnesiBox.
- Se esiste una sessione attiva, viene mostrata una richiesta di conferma (gestita dal frontend).

### Success Response
```json
{
  "session_id": "uuid",
  "status": "active",
  "duration_minutes": 60,
  "total_items": 3,
  "started_at": "2026-02-05T10:00:00Z"
}
```

### Error Responses
- `403`: permesso insufficiente
- `422`: validazione fallita (URL non valido, OnesiBox offline, etc.)
- `409`: sessione già attiva (se l'utente non ha confermato la sostituzione)

---

## C2: Interruzione Sessione

**Component**: `SessionManager`
**Action**: `stopSession`

### Input
```json
{
  "session_id": "uuid"
}
```

### Behavior
1. Trova la sessione attiva
2. Imposta status = `stopped`, ended_at = now
3. Invia comando `stop_media` alla OnesiBox
4. Cancella eventuali comandi `play_media` pending della sessione

### Success Response
```json
{
  "session_id": "uuid",
  "status": "stopped",
  "items_played": 2,
  "items_skipped": 0,
  "actual_duration_minutes": 15
}
```

---

## C3: Stato Sessione

**Component**: `SessionStatus`
**Computed Properties** (Livewire computed, aggiornate via polling/Echo)

### Output
```json
{
  "has_active_session": true,
  "session": {
    "id": "uuid",
    "status": "active",
    "duration_minutes": 60,
    "started_at": "2026-02-05T10:00:00Z",
    "time_remaining_seconds": 2400,
    "progress": {
      "current_position": 1,
      "total_items": 5,
      "items_played": 1,
      "items_skipped": 0
    },
    "current_video": {
      "title": "Introduzione alla Bibbia",
      "url": "https://www.jw.org/...",
      "position_in_playlist": 2
    }
  }
}
```

### Update Mechanism
- Polling Livewire ogni 10 secondi (`wire:poll.10s`)
- Oppure Echo event `SessionUpdated` sul canale privato `onesibox.{id}`

---

## C4: Estrazione Video da JW.org

**Component**: `PlaylistBuilder`
**Action**: `extractFromJwOrg`

### Input
```json
{
  "section_url": "https://www.jw.org/it/biblioteca/video/#it/categories/VODBible"
}
```

### Backend Flow
1. Valida URL (pattern `#XX/categories/CategoryKey`)
2. Estrae lingua e CategoryKey
3. Chiama `https://b.jw-cdn.org/apis/mediator/v1/categories/{LANG}/{CategoryKey}?detailed=1`
4. Estrae video dalla risposta (media[] + subcategories[].media[])
5. Ritorna lista video con titolo, URL e durata

### Success Response
```json
{
  "category_name": "La Bibbia",
  "videos": [
    {
      "title": "Introduzione alla Bibbia",
      "url": "https://www.jw.org/it/biblioteca/video/#it/mediaitems/BibleBooks/pub-nwtsv_I_1_VIDEO",
      "duration_seconds": 328,
      "duration_formatted": "5:28"
    }
  ],
  "total_count": 42,
  "total_duration_formatted": "3h 45m"
}
```

### Error Responses
- `422`: URL non valido o non è un URL di sezione JW.org
- `502`: JW.org API non raggiungibile
- `404`: Categoria non trovata o vuota

---

## C5: Gestione Playlist Salvate

**Component**: `SavedPlaylists`

### Action: `savePlaylist`
```json
{
  "onesi_box_id": 1,
  "name": "Video del mattino per Rosa",
  "video_urls": ["url1", "url2", "url3"]
}
```

### Action: `deletePlaylist`
```json
{
  "playlist_id": 5
}
```

### Action: `updatePlaylist`
```json
{
  "playlist_id": 5,
  "name": "Nuovo nome",
  "video_urls": ["url1", "url3", "url4"]
}
```

### Computed: `savedPlaylists`
```json
[
  {
    "id": 5,
    "name": "Video del mattino per Rosa",
    "source_type": "manual",
    "items_count": 3,
    "total_duration_formatted": "15m 30s",
    "created_at": "2026-02-01T08:00:00Z",
    "updated_at": "2026-02-03T09:30:00Z"
  }
]
```

---

## C6: Avanzamento Automatico (Backend Internal)

Non è un endpoint API ma un contratto interno. Documentato per completezza.

**Trigger**: `PlaybackController::store()` riceve un evento `completed` o `error`.

**Action**: `AdvancePlaybackSessionAction`

### Flow
```
1. Trova sessione attiva per onesi_box_id
2. Se non esiste → return (nessuna sessione, comportamento normale)
3. Aggiorna contatori (items_played++ o items_skipped++)
4. Incrementa current_position
5. Calcola tempo rimanente:
   - tempo_scadenza = started_at + duration_minutes
   - Se now() >= tempo_scadenza → termina sessione (status=completed)
6. Cerca prossimo item nella playlist:
   - Se esiste → crea comando play_media con OnesiBoxCommandService
   - Se non esiste → termina sessione (status=completed, tutti i video riprodotti)
7. Se sessione terminata → invia comando stop_media (opzionale, per sicurezza)
```

### Comando play_media generato
```json
{
  "type": "play_media",
  "payload": {
    "url": "https://www.jw.org/...",
    "media_type": "video",
    "session_id": "uuid-sessione"
  },
  "priority": 2
}
```
