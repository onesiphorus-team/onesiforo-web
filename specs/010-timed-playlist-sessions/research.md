# Research: Sessioni Video a Tempo con Playlist

**Feature Branch**: `010-timed-playlist-sessions`
**Date**: 2026-02-05

## R1: Meccanismo di avanzamento automatico tra video

### Decision
Utilizzare il sistema di playback events esistente per rilevare il completamento di un video. Quando il backend riceve un evento `completed` per un video appartenente a una sessione attiva, crea automaticamente un nuovo comando `play_media` per il video successivo. La OnesiBox lo riceve tramite il polling normale (5 secondi).

### Rationale
- **Zero logica di sessione sulla OnesiBox**: la OnesiBox deve solo rilevare il completamento del video e reportarlo. Non ha bisogno di sapere di "sessioni", "playlist" o "timer".
- **Riutilizzo dell'infrastruttura esistente**: comandi, polling, playback events sono già implementati e testati.
- **Latenza accettabile**: il polling ogni 5 secondi rientra nel target SC-002 (transizione entro 10 secondi).
- **Semplicità di debug**: tutto il flusso è tracciabile nei comandi e playback events esistenti.

### Alternatives Considered
1. **Nuovo endpoint "next video"**: la OnesiBox chiamerebbe `GET /api/v1/appliances/sessions/next` dopo ogni video. Più diretto (latenza ~1s), ma richiede logica di sessione sulla OnesiBox (session_id, gestione errori endpoint, fallback).
2. **WebSocket push**: il backend invia il prossimo video in tempo reale. Troppo complesso per il beneficio marginale; richiederebbe un cambio architetturale significativo.

### Implementation Note
La OnesiBox attualmente NON rileva il completamento naturale di un video. È necessario aggiungere un listener per l'evento `ended` dell'elemento `<video>` nel browser controller. Questa è l'unica modifica necessaria al client OnesiBox.

---

## R2: Estrazione video da sezioni JW.org

### Decision
Utilizzare la Mediator API pubblica di JW.org (`b.jw-cdn.org/apis/mediator/v1/`) per estrarre i video da una sezione/categoria. Non è scraping ma una API JSON pubblica usata dalle app mobili ufficiali.

### Rationale
- API pubblica, stabile, usata dalle app mobili JW.org.
- Nessuna autenticazione richiesta.
- Risposta JSON strutturata con tutti i metadati necessari (titolo, durata, URL diretto MP4).
- Il progetto ha già un proxy per questa API nella OnesiBox (`GET /api/jw-media`).

### Alternatives Considered
1. **Web scraping delle pagine HTML**: fragile, dipendente dalla struttura del DOM, soggetto a rotture.
2. **Richiesta manuale di URL all'utente**: funziona (è la User Story 1) ma non scala per sezioni con molti video.

### API Details

**Endpoint categoria:**
```
GET https://b.jw-cdn.org/apis/mediator/v1/categories/{LANG}/{CategoryKey}?detailed=1
```

**Codici lingua:** `I` = Italiano, `E` = English, `S` = Spanish, etc.

**Struttura URL sezione JW.org:**
```
https://www.jw.org/it/biblioteca/video/#it/categories/{CategoryKey}
```

**Parsing URL → API call:**
- Estrarre lingua dal path (es. `/it/`) → mappare a codice API (`I`)
- Estrarre CategoryKey dal fragment (`#it/categories/VODBible`) → `VODBible`

**Risposta API (campi rilevanti):**
```json
{
  "category": {
    "key": "VODBible",
    "name": "La Bibbia",
    "media": [...],
    "subcategories": [
      {
        "key": "BibleBooks",
        "media": [
          {
            "naturalKey": "pub-nwtsv_I_1_VIDEO",
            "title": "Introduzione alla Bibbia",
            "duration": 327.723,
            "files": [
              {
                "progressiveDownloadURL": "https://cfp2.jw-cdn.org/a/.../video.mp4",
                "frameHeight": 720
              }
            ]
          }
        ]
      }
    ]
  }
}
```

### Existing Code
- `app/Rules/JwOrgUrl.php`: validazione URL video individuali (pattern `#it/mediaitems/...`). Da estendere per supportare anche URL categoria (`#it/categories/...`).
- `app/Concerns/MediaUrlValidation.php`: trait per validazione URL media, usato dai componenti Livewire.
- OnesiBox proxy: `GET /api/jw-media?lang=XX&mediaId=YY` (proxy per evitare CORS).

---

## R3: Rilevamento completamento video sulla OnesiBox

### Decision
Aggiungere un listener per l'evento `ended` dell'elemento `<video>` HTML tramite Playwright `page.exposeFunction()` nel browser controller della OnesiBox. Quando il video finisce naturalmente, la OnesiBox riporta un evento `completed` al backend.

### Rationale
- Minima modifica al client: un solo listener JavaScript iniettato dopo la navigazione.
- Compatibile con sia video diretti MP4 che il player locale JW.org.
- Nessuna logica di sessione necessaria: la OnesiBox riporta il completamento, il backend decide cosa fare.

### Implementation Approach
```javascript
// In media handler, dopo aver navigato al video
await browserController.executeScript(`
  const video = document.querySelector('video');
  if (video) {
    video.addEventListener('ended', () => {
      window.__onesiboxVideoEnded = true;
    });
  }
`);

// Polling periodico o exposeFunction per rilevare il completamento
// e chiamare reportPlaybackEvent('completed', mediaInfo)
```

### Alternatives Considered
1. **Polling della posizione video**: controllare periodicamente se `position >= duration`. Meno preciso, spreca risorse.
2. **MutationObserver sul DOM**: troppo fragile, dipendente dalla struttura del player.

---

## R4: Gestione sessione lato backend

### Decision
Creare un'action `AdvancePlaybackSessionAction` che viene invocata quando il `PlaybackController` riceve un evento `completed` o `error`. L'action:
1. Trova la sessione attiva per la OnesiBox
2. Aggiorna lo stato dell'item corrente
3. Verifica il tempo rimanente
4. Se c'è tempo e ci sono video: crea un comando `play_media` per il prossimo video
5. Se il tempo è scaduto o non ci sono più video: termina la sessione

### Rationale
- Segue il pattern Action già usato nel progetto (`ProcessHeartbeatAction`, `CancelCommandAction`, etc.)
- Tutta la logica è nel backend
- Testabile unitariamente
- La creazione del comando usa l'`OnesiBoxCommandService` esistente

---

## R5: Struttura dashboard caregiver

### Decision
Estendere i componenti Livewire esistenti nella dashboard con nuovi componenti per la gestione delle sessioni. I componenti esistenti (`VolumeControl`, `VideoPlayer`, `CommandQueue`, etc.) in `app/Livewire/Dashboard/Controls/` forniscono il pattern da seguire.

### Rationale
- Segue le convenzioni esistenti (Livewire + Flux UI)
- Riutilizza i trait esistenti (`ChecksOnesiBoxPermission`, `HandlesOnesiBoxErrors`)
- Si integra nella pagina `OnesiBoxDetail` esistente

### Key Components Needed
1. `SessionManager` - avvio/stop sessione, selezione durata
2. `PlaylistBuilder` - inserimento URL manuali o URL sezione JW.org
3. `SessionStatus` - monitoraggio sessione in corso (video corrente, tempo rimanente)
4. `SavedPlaylists` - gestione playlist salvate
