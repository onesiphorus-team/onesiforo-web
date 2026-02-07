# Quickstart: Sessioni Video a Tempo con Playlist

**Feature Branch**: `010-timed-playlist-sessions`
**Date**: 2026-02-05

## Setup Sviluppo

```bash
# Checkout branch
git checkout 010-timed-playlist-sessions

# Installa dipendenze
composer install
npm install

# Crea migrazioni e seed
php artisan migrate

# Avvia sviluppo
composer run dev   # oppure: php artisan serve + npm run dev
```

## Flusso Completo (End-to-End)

### 1. Caregiver avvia sessione con playlist manuale

```
Dashboard → Seleziona OnesiBox → Inserisci URL video → Seleziona durata → "Avvia Sessione"
```

**Backend:**
1. Crea `Playlist` (is_saved=false, source_type=manual)
2. Crea `PlaylistItem` per ogni URL
3. Crea `PlaybackSession` (status=active, started_at=now)
4. Crea comando `play_media` per il primo video tramite `OnesiBoxCommandService`

### 2. OnesiBox riceve e riproduce il video

```
Polling (5s) → Riceve play_media → Riproduce video → Video finisce → Riporta "completed"
```

**OnesiBox:**
1. Polling `GET /api/v1/appliances/commands` riceve il comando `play_media`
2. Media handler riproduce il video nel browser kiosk
3. Listener `ended` sul `<video>` rileva il completamento
4. Riporta evento `completed` via `POST /api/v1/appliances/playback`

### 3. Backend avanza la sessione

```
Riceve "completed" → Controlla sessione attiva → Tempo rimasto? → Invia prossimo video
```

**Backend (in PlaybackController → AdvancePlaybackSessionAction):**
1. Riceve evento `completed` per la OnesiBox
2. Trova sessione attiva
3. Incrementa `items_played`, avanza `current_position`
4. Verifica tempo rimanente
5. Se c'è tempo e ci sono video: crea nuovo `play_media`
6. Se tempo scaduto o video finiti: imposta sessione come `completed`

### 4. Ciclo si ripete fino a fine sessione

## Flusso Sessione da JW.org

### 1. Caregiver inserisce URL sezione

```
Dashboard → Inserisci URL sezione JW.org → "Estrai video"
```

**Backend (JwOrgMediaExtractor):**
1. Valida URL sezione (pattern `#XX/categories/CategoryKey`)
2. Estrae lingua e CategoryKey
3. Chiama Mediator API: `GET https://b.jw-cdn.org/apis/mediator/v1/categories/{LANG}/{Key}?detailed=1`
4. Estrae video da `media[]` e `subcategories[].media[]`
5. Ritorna lista con titolo, URL, durata

### 2. Caregiver vede anteprima e avvia

```
Vede: "42 video trovati (durata totale: 3h 45m)" → Seleziona durata → "Avvia Sessione"
```

Da qui il flusso è identico alla playlist manuale.

## Testing

### Test Backend (Feature Tests)

```bash
# Tutti i test della feature
php artisan test --compact --filter=PlaybackSession

# Test specifici
php artisan test --compact --filter=StartPlaybackSessionTest
php artisan test --compact --filter=AdvancePlaybackSessionTest
php artisan test --compact --filter=JwOrgMediaExtractorTest
php artisan test --compact --filter=PlaylistTest
```

### Simulazione Manuale (Tinker)

```php
// Creare una sessione di test
$box = OnesiBox::first();
$playlist = Playlist::create([
    'onesi_box_id' => $box->id,
    'name' => 'Test',
    'source_type' => 'manual',
    'is_saved' => false,
]);

$playlist->items()->createMany([
    ['media_url' => 'https://www.jw.org/...video1', 'position' => 0],
    ['media_url' => 'https://www.jw.org/...video2', 'position' => 1],
]);

$session = PlaybackSession::create([
    'onesi_box_id' => $box->id,
    'playlist_id' => $playlist->id,
    'status' => 'active',
    'duration_minutes' => 60,
    'started_at' => now(),
]);

// Simulare avanzamento
app(AdvancePlaybackSessionAction::class)->execute($box, 'completed');
```

## File Principali

### Backend (Onesiforo)

```
app/
├── Models/
│   ├── Playlist.php                    # Nuovo model
│   ├── PlaylistItem.php                # Nuovo model
│   └── PlaybackSession.php             # Nuovo model
├── Enums/
│   ├── PlaybackSessionStatus.php       # Nuovo enum
│   └── PlaylistSourceType.php          # Nuovo enum
├── Actions/
│   ├── Sessions/
│   │   ├── StartPlaybackSessionAction.php
│   │   ├── StopPlaybackSessionAction.php
│   │   └── AdvancePlaybackSessionAction.php
│   └── Playlists/
│       ├── CreatePlaylistAction.php
│       └── ExtractJwOrgVideosAction.php
├── Services/
│   └── JwOrgMediaExtractor.php         # Servizio estrazione video JW.org
├── Rules/
│   └── JwOrgSectionUrl.php             # Nuova regola validazione
├── Livewire/Dashboard/Controls/
│   ├── SessionManager.php              # Avvio/stop sessione
│   ├── PlaylistBuilder.php             # Costruzione playlist
│   ├── SessionStatus.php               # Monitoraggio sessione
│   └── SavedPlaylists.php              # Gestione playlist salvate
└── Http/Controllers/Api/V1/
    └── PlaybackController.php          # Modifica: trigger AdvancePlaybackSessionAction

database/migrations/
├── xxxx_create_playlists_table.php
├── xxxx_create_playlist_items_table.php
└── xxxx_create_playback_sessions_table.php

resources/views/livewire/dashboard/controls/
├── session-manager.blade.php
├── playlist-builder.blade.php
├── session-status.blade.php
└── saved-playlists.blade.php

tests/Feature/
├── Sessions/
│   ├── StartPlaybackSessionTest.php
│   ├── StopPlaybackSessionTest.php
│   └── AdvancePlaybackSessionTest.php
├── Playlists/
│   ├── PlaylistManagementTest.php
│   └── JwOrgMediaExtractorTest.php
└── Api/
    └── PlaybackSessionIntegrationTest.php
```

### Client (OnesiBox)

```
src/
├── commands/handlers/
│   └── media.js                        # Modifica: aggiungere listener video ended
└── browser/
    └── controller.js                   # Modifica: metodo monitorVideoCompletion()
```
