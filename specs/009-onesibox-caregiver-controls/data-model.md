# Data Model: OnesiBox Caregiver Controls

## Entity Changes

### 1. OnesiBox (Estensione)

**Existing Table**: `onesi_boxes`

#### New Fields

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `current_media_url` | varchar(500) | Yes | URL del media in riproduzione |
| `current_media_type` | varchar(20) | Yes | Tipo media: 'video' o 'audio' |
| `current_media_title` | varchar(255) | Yes | Titolo estratto o nome file |
| `current_meeting_id` | varchar(50) | Yes | ID meeting Zoom se in chiamata |
| `volume` | tinyint unsigned | No (default 80) | Volume corrente 0-100 |
| `last_system_info_at` | datetime | Yes | Timestamp ultimo system info ricevuto |

#### Updated `status` Values

L'enum `OnesiBoxStatus` rimane invariato:
- `idle` - In attesa
- `playing` - In riproduzione (video o audio)
- `calling` - In chiamata Zoom/Jitsi
- `error` - Errore

La distinzione tra video e audio è data da `current_media_type`.

#### Migration

```php
Schema::table('onesi_boxes', function (Blueprint $table) {
    $table->string('current_media_url', 500)->nullable()->after('status');
    $table->string('current_media_type', 20)->nullable()->after('current_media_url');
    $table->string('current_media_title', 255)->nullable()->after('current_media_type');
    $table->string('current_meeting_id', 50)->nullable()->after('current_media_title');
    $table->unsignedTinyInteger('volume')->default(80)->after('current_meeting_id');
    $table->timestamp('last_system_info_at')->nullable()->after('volume');
});
```

---

### 2. Command (Estensione)

**Existing Table**: `commands`

#### New Status Value

Aggiungere `cancelled` all'enum `CommandStatus`:

```php
enum CommandStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';  // NUOVO
}
```

#### New Command Types

Aggiungere all'enum `CommandType`:

```php
// Diagnostics (nuovi)
case GetSystemInfo = 'get_system_info';
case GetLogs = 'get_logs';
```

#### Command Payloads

**SetVolume** (esistente, documentato):
```json
{
  "level": 80  // 0-100, in questa feature usiamo solo 20, 40, 60, 80, 100
}
```

**GetSystemInfo** (nuovo):
```json
{}  // Nessun payload richiesto
```

**GetLogs** (nuovo):
```json
{
  "lines": 50  // 1-500, default 50
}
```

---

### 3. SystemInfoSnapshot (Nuova Entità - Opzionale)

Per persistere le informazioni di sistema ricevute. **Nota**: Potrebbe non essere necessaria se i dati vengono solo visualizzati e non storicizzati.

**Table**: `system_info_snapshots` (opzionale)

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `id` | bigint | No | PK |
| `onesi_box_id` | bigint | No | FK → onesi_boxes |
| `uptime_seconds` | int unsigned | No | Uptime in secondi |
| `load_average_1m` | decimal(4,2) | Yes | Load average 1 min |
| `load_average_5m` | decimal(4,2) | Yes | Load average 5 min |
| `load_average_15m` | decimal(4,2) | Yes | Load average 15 min |
| `memory_used_bytes` | bigint unsigned | No | Memoria utilizzata |
| `memory_total_bytes` | bigint unsigned | No | Memoria totale |
| `cpu_percent` | tinyint unsigned | No | Utilizzo CPU % |
| `disk_used_bytes` | bigint unsigned | No | Disco utilizzato |
| `disk_total_bytes` | bigint unsigned | No | Disco totale |
| `ip_address` | varchar(45) | Yes | IPv4 o IPv6 |
| `wifi_ssid` | varchar(64) | Yes | Nome rete WiFi |
| `created_at` | datetime | No | Timestamp |

**Decisione**: Per la prima iterazione, NON creiamo questa tabella. I dati system info vengono visualizzati in real-time e non persistiti. Se in futuro serve storico, aggiungeremo.

---

## State Transitions

### OnesiBox Status

```
           ┌──────────────────────────────────────┐
           │                                      │
           ▼                                      │
    ┌──────────┐     playMedia()      ┌─────────────┐
    │   IDLE   │ ──────────────────▶ │   PLAYING   │
    └──────────┘                      └─────────────┘
         ▲  │                              │  ▲
         │  │                              │  │
         │  │     joinZoom()               │  │ stopMedia()
         │  │                              │  │
         │  └──────────┐                   │  │
         │             ▼                   │  │
         │      ┌──────────┐               │  │
         │      │ CALLING  │◀──────────────┘  │
         │      └──────────┘                  │
         │             │                      │
         │             │ leaveZoom()          │
         │             │                      │
         └─────────────┴──────────────────────┘
                       │
                       │ error
                       ▼
                ┌──────────┐
                │  ERROR   │ ──(auto recovery 10s)──▶ IDLE
                └──────────┘
```

### Command Status

```
    ┌─────────────┐
    │   PENDING   │
    └─────────────┘
         │
         ├──────────────────────────────────────────┐
         │                                          │
         │ appliance fetches                        │ caregiver cancels
         ▼                                          ▼
    ┌─────────────┐                          ┌─────────────┐
    │ (executing) │                          │  CANCELLED  │
    └─────────────┘                          └─────────────┘
         │
    ┌────┴────┬────────────┐
    │         │            │
    ▼         ▼            ▼
┌─────────┐ ┌────────┐ ┌─────────┐
│COMPLETED│ │ FAILED │ │ EXPIRED │
└─────────┘ └────────┘ └─────────┘
```

---

## Validation Rules

### SetVolume Command

```php
'level' => ['required', 'integer', Rule::in([20, 40, 60, 80, 100])]
```

### GetLogs Command

```php
'lines' => ['sometimes', 'integer', 'min:1', 'max:500']
```

### Heartbeat (Extended)

```php
'status' => ['required', Rule::enum(OnesiBoxStatus::class)],
'current_media' => ['nullable', 'array'],
'current_media.url' => ['required_with:current_media', 'url', 'max:500'],
'current_media.type' => ['required_with:current_media', Rule::in(['video', 'audio'])],
'current_media.position' => ['nullable', 'integer', 'min:0'],
'current_media.duration' => ['nullable', 'integer', 'min:0'],
'current_meeting' => ['nullable', 'array'],
'current_meeting.meeting_id' => ['required_with:current_meeting', 'string', 'max:50'],
'cpu_usage' => ['required', 'integer', 'min:0', 'max:100'],
'memory_usage' => ['required', 'integer', 'min:0', 'max:100'],
'disk_usage' => ['required', 'integer', 'min:0', 'max:100'],
'temperature' => ['required', 'numeric', 'min:0', 'max:150'],
'uptime' => ['required', 'integer', 'min:0'],
'volume' => ['sometimes', 'integer', 'min:0', 'max:100'],
```

---

## Relationships

### Existing (Unchanged)

- `OnesiBox` hasMany `Command`
- `OnesiBox` hasMany `PlaybackEvent`
- `OnesiBox` belongsTo `Recipient`
- `OnesiBox` belongsToMany `User` (caregivers) via `onesi_box_user`
- `Command` belongsTo `OnesiBox`

### New (If SystemInfoSnapshot implemented)

- `OnesiBox` hasMany `SystemInfoSnapshot`
- `SystemInfoSnapshot` belongsTo `OnesiBox`

---

## Indexes

### Existing (Sufficient)

- `commands.onesi_box_id, status` - Per query pending commands
- `commands.expires_at` - Per pulizia comandi scaduti
- `onesi_boxes.is_active` - Per filtro appliance attive
- `onesi_boxes.last_seen_at` - Per determinare online/offline

### New (Recommended)

Nessun nuovo indice necessario per questa feature. Le query aggiuntive usano gli indici esistenti.

---

## Data Volume Estimates

| Entity | Estimated Records | Growth Rate |
|--------|-------------------|-------------|
| OnesiBox | 10-50 | ~1/mese |
| Command | 100-500/giorno totali | ~100/giorno |
| Cancelled commands | 5-10% dei pending | Dipende da utenti |

---

## Migration Order

1. Aggiungere nuovi campi a `onesi_boxes` (non breaking)
2. Aggiungere `cancelled` a `CommandStatus` enum (non breaking)
3. Aggiungere `get_system_info`, `get_logs` a `CommandType` enum (non breaking)

Tutte le modifiche sono additive e non richiedono data migration.
