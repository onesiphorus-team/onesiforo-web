# Research: OnesiBox Caregiver Controls

## 1. Estensione Stato OnesiBox con Info Contestuali

### Decision
Estendere il heartbeat esistente per includere informazioni contestuali sullo stato attuale (media URL/titolo per playing, meeting ID per Zoom).

### Rationale
- Il heartbeat viene già inviato ogni 30 secondi dall'OnesiBox
- Lo `stateManager` in OnesiBox già traccia `currentMedia` e `currentMeeting`
- Aggiungere i campi al payload heartbeat è la soluzione più semplice e coerente

### Implementation Details
- **OnesiBox**: Il heartbeat già include `current_media` con `url`, `type`, `position`, `duration`
- **Onesiforo**: Aggiungere campi `current_media_url`, `current_media_type`, `current_meeting_id` alla tabella `onesi_boxes` o creare una relazione con `playback_events`
- **Alternative scartata**: Creare endpoint separato per stato real-time (overengineering per il caso d'uso)

### Alternatives Considered
1. WebSocket per stato real-time → Troppo complesso, Laravel Reverb già configurato per eventi broadcast
2. Polling separato dal heartbeat → Duplicazione logica, inefficiente

---

## 2. Controllo Volume con PipeWire/PulseAudio

### Decision
Utilizzare `amixer` come metodo primario con fallback a `amixer -D pulse` per compatibilità con PipeWire su Raspberry Pi OS.

### Rationale
- Il codice esistente in `volume.js` già implementa questo pattern
- PipeWire su Raspberry Pi OS espone un'interfaccia compatibile con ALSA via `pipewire-alsa`
- Non serve usare `wpctl` o `pactl` direttamente

### Implementation Details
```javascript
// Già implementato in src/commands/handlers/volume.js
await execAsync(`amixer set Master ${level}%`);
// Fallback
await execAsync(`amixer -D pulse set Master ${level}%`);
```

### Alternatives Considered
1. `wpctl set-volume` (PipeWire nativo) → Non necessario, amixer funziona
2. `pactl set-sink-volume` → Più complesso, meno portabile

---

## 3. Gestione Coda Comandi - Cancellazione

### Decision
Permettere cancellazione solo di comandi con `status = 'pending'`. L'eliminazione sarà un soft-delete impostando `status = 'cancelled'` (nuovo stato).

### Rationale
- I comandi già hanno stati: `pending`, `completed`, `failed`, `expired`
- Aggiungere `cancelled` permette audit trail completo
- Non si può cancellare un comando già prelevato dall'appliance (race condition gestita)

### Implementation Details
- **Nuovo enum value**: `CommandStatus::Cancelled`
- **Metodo Model**: `Command::cancel()` che verifica `status === pending` prima di aggiornare
- **API endpoint**: `DELETE /api/v1/commands/{uuid}` restituisce 409 Conflict se non cancellabile
- **Bulk delete**: `DELETE /api/v1/appliances/commands/pending` per cancellare tutti i pending

### Alternatives Considered
1. Hard delete → Perdita audit trail, problemi se appliance ha già fetchato il comando
2. Flag `is_cancelled` separato → Ridondante rispetto a stato enum

---

## 4. Informazioni di Sistema Estese

### Decision
Creare nuovo tipo comando `get_system_info` che l'OnesiBox esegue e risponde via ACK con payload contenente le informazioni di sistema.

### Rationale
- Pattern coerente con architettura esistente (comandi → ACK)
- Evita necessità di nuovo endpoint API lato OnesiBox
- Le informazioni di base (CPU, memoria, disco, temperatura) sono già nel heartbeat

### Implementation Details
- **Nuovo CommandType**: `GetSystemInfo`
- **OnesiBox handler**: Usa `systeminformation` (già installato) per:
  - `si.time()` → uptime
  - `si.currentLoad()` → load average
  - `si.mem()` → memoria dettagliata
  - `si.fsSize()` → disco
  - `si.networkInterfaces()` → IP
  - `si.wifiConnections()` → SSID WiFi
- **ACK payload**: JSON con tutti i campi formattati
- **UI**: Mostra dati da ultimo heartbeat + pulsante "Aggiorna" che invia comando

### Data Format
```json
{
  "uptime_seconds": 172800,
  "uptime_formatted": "2 giorni, 0 ore",
  "load_average": { "1m": 0.5, "5m": 0.3, "15m": 0.2 },
  "memory": { "used_bytes": 1073741824, "total_bytes": 4294967296, "percent": 25 },
  "cpu_percent": 15,
  "disk": { "used_bytes": 10737418240, "total_bytes": 32212254720, "percent": 33 },
  "network": { "ip": "192.168.1.100", "wifi_ssid": "HomeNetwork" },
  "timestamp": "2026-01-25T10:30:00Z"
}
```

### Alternatives Considered
1. Estendere heartbeat con tutti i dati → Heartbeat diventerebbe troppo pesante
2. Endpoint HTTP diretto su OnesiBox → Richiede esposizione porta, problemi firewall/NAT

---

## 5. Recupero Log Remoti

### Decision
Creare nuovo tipo comando `get_logs` con parametro `lines` (default 50, max 500). L'OnesiBox legge i log, li sanitizza e li restituisce nell'ACK.

### Rationale
- Pattern coerente con `get_system_info`
- Filtro dati sensibili eseguito lato OnesiBox prima dell'invio
- Limite 500 righe per evitare payload troppo grandi

### Implementation Details

#### OnesiBox - Log Reading
```javascript
const fs = require('fs');
const path = require('path');
const readline = require('readline');

async function getLastNLines(logDir, n) {
  // Trova il file di log più recente
  const today = new Date().toISOString().split('T')[0];
  const logFile = path.join(logDir, `onesibox-${today}.log`);

  // Leggi ultime N righe (tail-like)
  // Implementare con reverse line reader o buffer
}
```

#### OnesiBox - Log Sanitization
Pattern da rimuovere:
- Password Zoom: `/pwd=[A-Za-z0-9]+/g` → `pwd=***`
- Token Bearer: `/Bearer [A-Za-z0-9|]+/g` → `Bearer ***`
- Token Sanctum: `/\d+\|[A-Za-z0-9]+/g` → `***|***`
- URL con password: `/password=[^&\s]+/g` → `password=***`

#### Response Format
```json
{
  "lines": [
    { "timestamp": "2026-01-25T10:30:00.123Z", "level": "info", "message": "Command executed", "context": {} },
    { "timestamp": "2026-01-25T10:29:55.456Z", "level": "error", "message": "Failed to connect", "context": { "error": "timeout" } }
  ],
  "total_lines": 100,
  "returned_lines": 50,
  "log_file": "onesibox-2026-01-25.log"
}
```

### Alternatives Considered
1. Streaming log via WebSocket → Out of scope, troppo complesso per il caso d'uso
2. Download file intero → Troppo pesante, problemi privacy

---

## 6. Real-time UI Updates

### Decision
Utilizzare Laravel Echo con Reverb (già configurato) per broadcast eventi di cambio stato. Livewire components ascoltano eventi specifici.

### Rationale
- Laravel Reverb già installato e configurato
- Pattern già usato in `OnesiBoxDetail.php` con `#[On('echo-private:onesibox.{onesiBox.id},StatusUpdated')]`
- Aggiornamento UI senza polling continuo lato frontend

### Implementation Details
- **Evento esistente**: `OnesiBoxStatusUpdated` già broadcasta su canale privato
- **Estensione**: Aggiungere payload con `current_media`, `current_meeting` all'evento
- **Livewire**: Usare `$this->onesiBox->refresh()` nel listener esistente

### Alternatives Considered
1. Polling frontend ogni 5s → Meno efficiente, più richieste server
2. SSE (Server-Sent Events) → Laravel Reverb già fornisce WebSocket, ridondante

---

## 7. UI Mobile-First con Flux UI

### Decision
Utilizzare componenti Flux UI esistenti (button, badge, modal, callout) per i nuovi controlli. Layout responsive con grid Tailwind.

### Rationale
- Flux UI Free già installato con componenti disponibili
- Pattern UI già stabiliti negli altri componenti dashboard
- Tailwind v4 per responsive design

### Implementation Details

#### Volume Control
```blade
<div class="grid grid-cols-5 gap-2">
  @foreach([20, 40, 60, 80, 100] as $level)
    <flux:button
      wire:click="setVolume({{ $level }})"
      :variant="$currentVolume === $level ? 'primary' : 'outline'"
      class="aspect-square"
    >
      {{ $level }}%
    </flux:button>
  @endforeach
</div>
```

#### Command Queue
- Lista con `<flux:badge>` per tipo comando
- Azioni delete con `<flux:button>` e modale conferma
- Empty state con `<flux:callout>`

#### System Info
- Card con grid di metriche
- Progress bar per percentuali (CPU, memoria, disco)
- Pulsante refresh per richiedere dati aggiornati

#### Log Viewer
- Input numerico per righe da recuperare
- Area scrollabile con `max-h-96 overflow-y-auto`
- Badge colorati per livello log (info=gray, warn=yellow, error=red)

### Alternatives Considered
1. Custom components → Più lavoro, meno consistenza
2. Tabella Filament per log → Overkill per visualizzazione semplice

---

## 8. Testing Strategy

### Decision
Test-first con Pest per Livewire components e API endpoints. Test unitari Node.js per handler OnesiBox.

### Test Categories

#### Livewire Components (Feature Tests)
- `VolumeControlTest`: Verifica invio comando, rispetto permessi, UI feedback
- `CommandQueueTest`: Lista comandi, delete singolo, delete tutti, permessi ReadOnly
- `SystemInfoTest`: Visualizzazione dati heartbeat, richiesta aggiornamento
- `LogViewerTest`: Richiesta log, visualizzazione, limite righe

#### API Endpoints (Feature Tests)
- `CommandCancelTest`: Cancel singolo, cancel bulk, 409 su non-pending
- `SystemInfoApiTest`: Heartbeat con dati estesi, validazione payload

#### OnesiBox (Unit Tests)
- `system-info.test.js`: Handler restituisce dati corretti
- `logs.test.js`: Lettura ultime N righe, limite max
- `log-sanitizer.test.js`: Rimozione password, token, URL sensibili

### Alternatives Considered
1. Solo integration tests → Meno coverage, più lenti
2. Browser tests Pest 4 → Utili per E2E ma non necessari per MVP

---

## Summary of Decisions

| Area | Decision | Key Rationale |
|------|----------|---------------|
| Stato contestuale | Estendere heartbeat | Già implementato, minimo sforzo |
| Volume control | amixer con fallback | Codice esistente, compatibile PipeWire |
| Cancel comandi | Nuovo stato 'cancelled' | Audit trail preservato |
| System info | Comando get_system_info | Pattern coerente, no nuovi endpoint |
| Log retrieval | Comando get_logs + sanitizer | Sicurezza dati sensibili |
| Real-time | Laravel Reverb esistente | Già configurato e funzionante |
| UI | Flux UI + Tailwind grid | Consistenza con dashboard esistente |
| Testing | Pest + Node unit tests | Test-first, coverage completa |
