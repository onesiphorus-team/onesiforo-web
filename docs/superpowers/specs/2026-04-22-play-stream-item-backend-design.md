# Backend support for `play_stream_item` — Design Spec

**Status:** Draft
**Date:** 2026-04-22
**Author:** m.dangelo@oltrematica.it (pairing with Claude)
**Repo:** `onesiforo-web`

## Goal

Aggiungere al backend Onesiforo la capacità di emettere il comando `play_stream_item` verso un OnesiBox, tramite una nuova UI admin Livewire dedicata (`StreamPlayer`) con controlli "Avvia playlist", "Precedente", "Successivo", "Stop". Il comando è definito e implementato lato client (vedi `onesi-box` PR #42 — `feature/play-stream-item`). Lo scope di questa spec copre: estensione del servizio comandi, UI Livewire, validazione URL, e due fix di plumbing condiviso necessari (`error_code` nelle playback events, broadcast reattivo degli eventi).

## Context

### Cosa esiste già nel repo

Il backend gestisce oggi il comando `play_media` tramite:

- `app/Enums/CommandType.php` — enum PHP nativo con `PlayMedia = 'play_media'`, `StopMedia`, `JoinZoom`, ecc.
- `app/Services/OnesiBoxCommandService.php` — service centralizzato con `sendMediaCommand(OnesiBox, url, mediaType): void`, `sendSessionMediaCommand(..., sessionId): void` (priority 2), `sendStopCommand`, ecc. `sendCommand()` privato chiama `ensureOnline()` → `createCommand()` → `dispatch(SendOnesiBoxCommand)` → `dispatchCommandSentEvent(OnesiBoxCommandSent)`. Firma `void`, non ritorna `Command`.
- `app/Livewire/Dashboard/Controls/MediaPlayer.php` — classe astratta base, estesa da `AudioPlayer` / `VideoPlayer`. Prende `$onesiBox` come public property. Pattern: `$this->authorize('control', $this->onesiBox)` + `$this->validate()` + `$commandService->sendMediaCommand(...)` via `executeWithErrorHandling`.
- `app/Rules/JwOrgUrl.php` — invocation rule per URL `www.jw.org` / `jw.org` / `wol.jw.org` + pattern `mediaitems`.
- `app/Events/NewCommandAvailable.php` — `ShouldBroadcastNow` su channel privato `appliance.{serial_number}` per notificare il comando disponibile al client.
- `app/Models/PlaybackEvent.php` + `app/Http/Controllers/Api/V1/PlaybackController.php` + `app/Http/Requests/Api/V1/PlaybackEventRequest.php` + `app/Actions/StorePlaybackEventAction.php` — API `POST /api/v1/appliances/playback` dove l'OnesiBox riporta eventi di playback. Chiama `AdvancePlaybackSessionAction` su `Completed`/`Error` per orchestrare sessioni playlist lato server.
- `app/Models/PlaybackSession.php` + `StartPlaybackSessionAction` + `AdvancePlaybackSessionAction` — orchestrazione server-side di playlist timed (con N `play_media` consecutivi stesso `session_id`).
- Test framework: **Pest** (vedi `tests/Pest.php`). Il pattern è `tests/Feature/**` + `tests/Unit/**` + `tests/Browser/**` + `tests/Architecture/**`.

### Cosa il client `onesi-box` supporta già (PR #42)

Il comando `play_stream_item {url, ordinal, session_id?}` è già implementato nel client con handler dedicato `src/commands/handlers/stream-playlist.js`. Accetta URL `stream.jw.org`, ordinale 1-50, session_id opzionale. Restituisce eventi `started`/`completed`/`error` via `POST /api/v1/appliances/playback`. I codici di errore specifici sono `E110 STREAM_NAV_FAILED`, `E111 PLAYLIST_LOAD_FAILED`, `E112 ORDINAL_OUT_OF_RANGE`, `E113 VIDEO_START_FAILED`.

**Punto dolente scoperto durante la mappatura backend**: il client manda già `error_code` nel payload degli eventi di errore, ma il backend NON lo valida né lo persiste (vedi "Shared plumbing" sotto).

## Requirements

### Functional

1. **Nuovo case enum** `CommandType::PlayStreamItem = 'play_stream_item'`.
2. **Nuovo metodo service** `OnesiBoxCommandService::sendStreamItemCommand(OnesiBox $box, string $url, int $ordinal): void` (firma `void` coerente con gli altri `send*Command`). Priority 2 (stesso di `sendSessionMediaCommand`). Payload: `{url, ordinal}`. Nessun `session_id`.
3. **Aggiornare l'interfaccia** `OnesiBoxCommandServiceInterface` con la nuova firma.
4. **Nuova validation rule** `App\Rules\JwStreamUrl` — accetta solo `https://stream.jw.org/...`, porta 443, HTTPS, formato token `NNNN-NNNN-NNNN-NNNN` nel path (o `/home` e derivati post-redirect).
5. **Nuovo Livewire component** `App\Livewire\Dashboard\Controls\StreamPlayer` con:
   - Props: `OnesiBox $onesiBox`
   - Public state: `string $url = ''`, `?int $lastOrdinalSent = null`, `?string $errorCode = null`, `bool $reachedEnd = false`
   - Method `playFromStart(OnesiBoxCommandServiceInterface)` — invia ordinale 1
   - Method `next(OnesiBoxCommandServiceInterface)` — invia `lastOrdinalSent + 1`
   - Method `previous(OnesiBoxCommandServiceInterface)` — invia `lastOrdinalSent - 1`
   - Method `stop(OnesiBoxCommandServiceInterface)` — chiama `sendStopCommand` (riuso codice esistente)
   - Listener Echo sul canale `appliance.{serial_number}` per evento `playback.event-received`
   - Metodo `mount()` per ripristino stato da tabella `commands` (filtro: `play_stream_item`, ultime 6 ore, ultimo inviato) + ultimo `playback_events` correlato per `errorCode`/`reachedEnd`
6. **Nuova view Blade** `resources/views/livewire/dashboard/controls/stream-player.blade.php` con:
   - Form: input URL + button "Avvia playlist"
   - Controlli: "◀ Precedente" (disabled se `lastOrdinalSent <= 1` o `null`), "Successivo ▶" (disabled se `reachedEnd`), "Stop"
   - Display: "Ordinale corrente: N" (quando `lastOrdinalSent` è set)
   - Banner errore: colore rosso per E110/E111/E113, colore info/verde per E112 ("Ultimo video raggiunto"). Dismissibile.
7. **Integrazione layout**: aggiungere `<livewire:dashboard.controls.stream-player :onesi-box="$onesiBox" />` accanto al `<livewire:dashboard.controls.video-player>` esistente nel layout del pannello per-dispositivo. Il file esatto verrà identificato in fase implementativa (grep per `video-player` nelle view).
8. **Autorizzazione**: `$this->authorize('control', $this->onesiBox)` in tutti i 4 method pubblici (coerente con `MediaPlayer`).

### Non-functional

- Nessuna nuova dipendenza Composer.
- Coerenza con i pattern esistenti: trait `HandlesOnesiBoxErrors`, `executeWithErrorHandling`, messaggi in italiano.
- Test coverage: unit per rule + service method, feature per Livewire component (`Livewire::test(...)`), smoke manuale end-to-end.

### Out of scope

- **Server-orchestration di playlist stream** (scope "C" del brainstorm). Resta out of scope: se in futuro si vorrà auto-advance su `completed`, si aggiunge al listener Echo o si estende `PlaybackSession` per supportare stream items.
- **Auto-discovery del numero di item della playlist** via chiamata a API `stream.jw.org`. Resta out of scope — usiamo il modello "self-limiting" (reazione a E112).
- **Cronologia/log separato delle playlist JW Stream riprodotte**. Lo storico resta nella tabella `commands` esistente.
- **Cambiamenti alla UI `MediaPlayer` esistente**. Nessun refactoring cross-component.

## Architecture

### Nuovi file

```
app/
  Enums/
    CommandType.php                                  (+ 1 case)
  Services/
    OnesiBoxCommandService.php                       (+ 1 metodo)
    OnesiBoxCommandServiceInterface.php              (+ 1 firma)
  Rules/
    JwStreamUrl.php                                  (nuovo)
  Livewire/Dashboard/Controls/
    StreamPlayer.php                                 (nuovo, ~120 LOC)
resources/views/livewire/dashboard/controls/
  stream-player.blade.php                            (nuovo)
```

### File modificati per shared plumbing

```
database/migrations/
  YYYY_MM_DD_HHmmss_add_error_code_to_playback_events.php  (nuova migration)
app/
  Http/Requests/Api/V1/
    PlaybackEventRequest.php                         (+ rule error_code)
  Actions/
    StorePlaybackEventAction.php                     (+ parametro errorCode)
  Models/
    PlaybackEvent.php                                (+ fillable error_code)
  Events/
    PlaybackEventReceived.php                        (nuovo, broadcast)
  Http/Controllers/Api/V1/
    PlaybackController.php                           (chiama broadcast)
```

### Layout integration

Un solo file di view (dashboard per-OnesiBox) va aggiornato per montare il nuovo component. Si identifica in implementazione con `grep "video-player" resources/views`. Cambio atteso: aggiungere 1 riga.

### Flow diagram

```
[Operatore browser] 
   │  Submit URL in StreamPlayer
   ▼
Livewire::StreamPlayer::playFromStart()
   │  authorize + validate (JwStreamUrl + ordinal=1)
   ▼
OnesiBoxCommandService::sendStreamItemCommand($box, $url, 1)
   │  ensureOnline + create Command(type=play_stream_item, payload={url,ordinal:1}, priority=2)
   │  dispatch(SendOnesiBoxCommand) + event(OnesiBoxCommandSent)
   │  broadcast(NewCommandAvailable) su channel appliance.{serial}
   ▼
[OnesiBox client]
   │  Riceve via WebSocket
   │  Esegue play_stream_item handler (DOM automation)
   │  POST /api/v1/appliances/playback con {event, error_code?, ...}
   ▼
PlaybackController::store()
   │  Valida tramite PlaybackEventRequest (ora include error_code)
   │  StorePlaybackEventAction persiste (ora include error_code)
   │  broadcast(PlaybackEventReceived) su channel appliance.{serial}   ← NUOVO
   ▼
[Browser StreamPlayer]
   │  Echo listener riceve payload
   │  handleEvent(): set errorCode / reachedEnd
   │  UI aggiornata reattivamente
```

### Mount / state restoration

```
StreamPlayer::mount(OnesiBox $onesiBox):
  $this->onesiBox = $onesiBox

  $lastCommand = Command::query()
    ->where('onesi_box_id', $onesiBox->id)
    ->where('type', CommandType::PlayStreamItem)
    ->where('created_at', '>=', now()->subHours(6))
    ->latest()
    ->first();

  if ($lastCommand) {
    $this->url = $lastCommand->payload['url'];
    $this->lastOrdinalSent = $lastCommand->payload['ordinal'];

    $lastEvent = PlaybackEvent::query()
      ->where('onesi_box_id', $onesiBox->id)
      ->where('media_url', $this->url)
      ->where('created_at', '>=', $lastCommand->created_at)
      ->latest()
      ->first();

    if ($lastEvent && $lastEvent->event === PlaybackEventType::Error) {
      $this->errorCode = $lastEvent->error_code;
      if ($this->errorCode === 'E112') $this->reachedEnd = true;
    }
  }
```

## Data flow (dettaglio)

### Submit nuovo URL → ordinale 1

1. Livewire validate: `$rules = ['url' => ['required', new JwStreamUrl]]` → errore inline se fallisce.
2. `$commandService->sendStreamItemCommand($this->onesiBox, $this->url, 1)`.
3. Se throw `OnesiBoxOfflineException` → gestito da `HandlesOnesiBoxErrors` trait (pattern esistente).
4. Altrimenti: success toast, `$this->lastOrdinalSent = 1`, `$this->reachedEnd = false`, `$this->errorCode = null`.

### Click Successivo

1. Pre-check: se `$reachedEnd` true, bottone disabilitato nel Blade — nessuna chiamata arriva.
2. `$commandService->sendStreamItemCommand($this->onesiBox, $this->url, $this->lastOrdinalSent + 1)`.
3. Success: `$this->lastOrdinalSent++`, `$this->errorCode = null`.

### Click Precedente

1. Pre-check: `$lastOrdinalSent > 1` (altrimenti bottone disabilitato).
2. `$commandService->sendStreamItemCommand(..., $this->lastOrdinalSent - 1)`.
3. Success: `$this->lastOrdinalSent--`, `$this->reachedEnd = false`, `$this->errorCode = null`.

### Click Stop

Riutilizza `$commandService->sendStopCommand($this->onesiBox)` esistente. Non cambia lo stato locale del component (l'operatore potrebbe voler ricominciare da un ordinale specifico dopo lo stop).

### Evento WebSocket `PlaybackEventReceived`

```php
#[On('echo-private:appliance.{$this->onesiBox->serial_number},.playback.event-received')]
public function handlePlaybackEvent(array $payload): void
{
    if ($payload['media_url'] !== $this->url) return; // non è per noi

    if ($payload['event'] === 'error') {
        $this->errorCode = $payload['error_code'] ?? null;
        if ($this->errorCode === 'E112') $this->reachedEnd = true;
    }
    // Gli eventi completed/started/stopped non modificano lo stato locale (scope B)
}
```

### Interazione con `AdvancePlaybackSessionAction`

`PlaybackController::store()` oggi invoca `AdvancePlaybackSessionAction` su eventi `Completed` o `Error`. Questa action cerca una `PlaybackSession` associata al media in corso.

**Comportamento atteso per `play_stream_item`**: non esisterà `PlaybackSession` matching (non le creiamo mai per stream items), quindi l'action è no-op. Nessuna modifica necessaria. **Da verificare in implementazione** con un test feature: evento `completed` per un media URL stream.jw.org NON deve avviare sessioni fantasma.

## Error handling

### Service-side

| Eccezione | Causa | UX |
|---|---|---|
| `ValidationException` | URL non `stream.jw.org` o ordinal fuori 1-50 | Mostrata inline sotto input dal trait `validate()` Livewire |
| `OnesiBoxOfflineException` | Il dispositivo è offline al momento del submit | Toast rosso (tramite `HandlesOnesiBoxErrors` esistente): "OnesiBox offline" |

### Client-reported error events (via broadcast)

| `error_code` | UX nel component |
|---|---|
| `E110` | Banner rosso: "Impossibile raggiungere JW Stream. Verifica la connessione del dispositivo." |
| `E111` | Banner rosso: "Playlist non caricata. L'URL potrebbe essere errato o scaduto." + bottone "Cambia URL" che resetta `url` e `lastOrdinalSent` |
| `E112` | Banner info/verde: "Ultimo video della playlist raggiunto." → disabilita "Successivo", setta `reachedEnd=true` |
| `E113` | Banner giallo: "Impossibile avviare il video. Il sito potrebbe essere cambiato — riprova o contatta supporto." |
| Altro (`E010` timeout) | Banner rosso generico: "Tempo scaduto sul dispositivo. Riprova." |

Tutti i banner dismissibili. `reachedEnd` NON viene resettato dal dismiss — solo da "Precedente" o "Avvia playlist" (nuovo URL).

## Shared plumbing changes

### Migration `add_error_code_to_playback_events`

```php
Schema::table('playback_events', function (Blueprint $table) {
    $table->string('error_code', 10)->nullable()->after('error_message');
    $table->index('error_code');
});
```

### `PlaybackEventRequest` (rules aggiuntive)

```php
'error_code' => [
    'nullable',
    'string',
    'regex:/^E\d{3}$/',  // formato E### (E110, E010, ecc.)
],
```

Messaggio italiano custom: `"Il codice errore deve essere nel formato E### (es. E110)."`

### `StorePlaybackEventAction` (signature aggiornata)

Aggiungere `?string $errorCode = null` in coda ai parametri di `__invoke()` e includere nel `create()`. Il metodo `fromArray()` estrae `$data['error_code'] ?? null`.

### `PlaybackEvent` model

Aggiungere `'error_code'` al `$fillable`.

### Nuovo event broadcast `PlaybackEventReceived`

```php
namespace App\Events;

use App\Models\PlaybackEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PlaybackEventReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public PlaybackEvent $playbackEvent) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("appliance.{$this->playbackEvent->onesiBox->serial_number}");
    }

    public function broadcastAs(): string { return 'playback.event-received'; }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->playbackEvent->event->value,
            'media_url' => $this->playbackEvent->media_url,
            'media_type' => $this->playbackEvent->media_type,
            'error_code' => $this->playbackEvent->error_code,
            'error_message' => $this->playbackEvent->error_message,
            'occurred_at' => $this->playbackEvent->created_at->toISOString(),
        ];
    }
}
```

### `PlaybackController::store()` invoca il broadcast

Dopo `$playbackEvent = $storeAction->fromArray(...)` e PRIMA di `$advanceAction->execute(...)`:

```php
broadcast(new PlaybackEventReceived($playbackEvent));
```

## Testing

### Unit test — `tests/Unit/Rules/JwStreamUrlTest.php`

```
✓ accetta https://stream.jw.org/6311-4713-5379-2156
✓ accetta https://stream.jw.org/home
✓ accetta https://stream.jw.org/home?playerOpen=true
✗ rifiuta http://stream.jw.org/x (no HTTPS)
✗ rifiuta https://stream.jw.org.evil.com/x (subdomain attack)
✗ rifiuta https://fake-stream.jw.org/x (non è esattamente stream.jw.org)
✗ rifiuta https://www.jw.org/mediaitems/... (dominio sbagliato per questa rule)
✗ rifiuta '' / null
✗ rifiuta porte non-standard (:9999)
✗ rifiuta URL > 2048 caratteri
```

### Unit test — `tests/Unit/Services/OnesiBoxCommandServiceTest.php`

Nuovo o esteso. Test per `sendStreamItemCommand`:

```
✓ crea record commands con type=play_stream_item, payload={url, ordinal}, priority=2
✓ dispatcha SendOnesiBoxCommand job
✓ fires OnesiBoxCommandSent event
✗ box offline → throw OnesiBoxOfflineException, niente command creato, niente dispatch
```

### Unit test — `tests/Unit/Actions/StorePlaybackEventActionTest.php`

Esteso. Test: `✓ persiste error_code se fornito` e `✓ error_code null se non fornito`.

### Feature test — `tests/Feature/Livewire/StreamPlayerTest.php`

```
Mount / state restoration
  ✓ monta pulito se non ci sono comandi recenti
  ✓ ripristina url + lastOrdinalSent dall'ultimo play_stream_item nelle 6 ore
  ✓ ignora comandi play_stream_item oltre 6 ore
  ✓ ripristina reachedEnd=true se ultimo evento Error aveva error_code=E112
  ✓ ripristina errorCode da ultimo evento Error E110/E111/E113

playFromStart
  ✓ validation URL vuota → errore
  ✓ validation URL non-stream.jw.org → errore via rule
  ✓ URL valido → chiama sendStreamItemCommand(box, url, 1)
  ✓ setta lastOrdinalSent=1, reachedEnd=false, errorCode=null
  ✓ richiede autorizzazione 'control' sul box (401 se unauthorized)

next
  ✓ incrementa ordinal e chiama service
  ✓ disabilitato se reachedEnd=true (non chiama service)
  ✓ resetta errorCode

previous
  ✓ decrementa ordinal
  ✓ disabilitato se lastOrdinalSent=1
  ✓ resetta reachedEnd=false

stop
  ✓ chiama sendStopCommand (riuso service esistente)

handlePlaybackEvent (Echo listener)
  ✓ evento error E112 → reachedEnd=true
  ✓ evento error E110/E111/E113 → errorCode settato, reachedEnd resta false
  ✓ evento completed/started/stopped → no-op
  ✓ ignora eventi con media_url diverso dall'url corrente
```

### Feature test — `tests/Feature/Api/V1/PlaybackControllerTest.php` (esteso)

```
✓ accetta error_code valido (E110-E113, E010)
✗ rifiuta error_code mal formattato (es. "error")
✓ persiste error_code in DB
✓ broadcast PlaybackEventReceived dopo persistenza (verifica via Event::fake)
✓ AdvancePlaybackSessionAction NON viene invocata se media_url è stream.jw.org (no-op di fatto perché non c'è session)
```

### Smoke test manuale end-to-end

Documentato come nuova sezione in `docs/` (file specifico da decidere, tipicamente `docs/superpowers/` oppure nel README del progetto):

1. Dashboard admin → seleziona OnesiBox online (es. onesibox-macos-dev per test in locale).
2. Nella sezione del dispositivo, localizzare il nuovo pannello "Stream Playlist".
3. Inserire URL `https://stream.jw.org/6311-4713-5379-2156` → click "Avvia playlist".
4. Verifica: toast success, sul dispositivo parte la parte 1, il pannello mostra "Ordinale corrente: 1".
5. Click "Successivo ▶": parte la parte 2, UI mostra "Ordinale corrente: 2".
6. Click "Precedente ◀": torna alla parte 1, UI mostra "Ordinale corrente: 1".
7. Clicca "Successivo" ripetutamente finché non si raggiunge la fine (dopo la parte 4): banner info verde "Ultimo video raggiunto", "Successivo" disabilitato.
8. Click "Precedente": banner scompare, bottone "Successivo" ri-abilitato.
9. Click "Stop": dispositivo torna in standby, lo stato del pannello resta invariato (posso ripartire dallo stesso ordinale).
10. Refresh browser: il pannello ricostruisce `url` e `lastOrdinalSent` dalla tabella `commands` (ultimi 6 ore).

### Niente E2E automatico

Stessa filosofia del client: non testiamo contro `stream.jw.org` reale in CI. Copertura manuale al pre-release.

## Alternative considerate (rifiutate)

- **Estendere `MediaPlayer` con un tab "Stream playlist"** — rifiutato: mescolare responsabilità (URL singolo vs playlist con stato).
- **Pagina separata `/admin/onesibox/{id}/stream-playlist`** — rifiutato: UX peggiore se l'operatore controlla più dispositivi.
- **Orchestrazione server-side via `PlaybackSession`** — rifiutato: complicazione (serve scoprire il numero di item), gap percepibile tra video a causa della re-navigazione client, fragilità.
- **Auto-discovery del numero di item via API `stream.jw.org`** — rifiutato: dipendenza su API non documentata, bassa UX value.
- **Persistenza stato in tabella dedicata** — rifiutato: YAGNI, Commands-derived basta.
- **Session_id nel payload** — rifiutato: senza `PlaybackSession` server-orchestrated il campo perde significato.
- **Estendere `JwOrgUrl` invece di rule nuova** — rifiutato: semantiche diverse, messaggi di errore più confusi.
- **Spec separata per i fix di plumbing condiviso** — rifiutato: complica il rilascio, i fix servono subito al nuovo component.

## Open questions

Nessuna. Tutte le decisioni sono state validate nel brainstorming del 2026-04-22.
