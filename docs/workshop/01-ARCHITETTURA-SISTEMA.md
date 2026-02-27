# Architettura del Sistema Onesiforo

**Workshop Introduttivo - Febbraio 2026**
**Versione:** 2.0

---

## 1. Visione d'Insieme

Onesiforo è una piattaforma per il **controllo remoto di appliance OnesiBox**, destinate all'assistenza di persone anziane. Il sistema è composto da due applicazioni:

| Componente | Tecnologia | Ruolo |
|-----------|-----------|-------|
| **Onesiforo Web** | Laravel 12 + Livewire 4 + Filament 5 | Backend, dashboard caregiver, pannello admin |
| **OnesiBox Client** | Node.js 20 + Playwright | Client su Raspberry Pi, esegue comandi, riproduce media |

### 1.1 Diagramma di Contesto

```mermaid
graph TB
    subgraph Utenti
        CG[👤 Caregiver]
        AD[👤 Admin]
        BN[👴 Beneficiario]
    end

    subgraph "Onesiforo Web (Laravel 12)"
        DASH[Dashboard Livewire]
        API[API REST v1]
        WS[WebSocket Reverb]
        ADMIN[Filament Admin]
    end

    subgraph "OnesiBox Client (Node.js)"
        POLL[Polling Engine]
        CMD[Command Manager]
        BROWSER[Chromium Kiosk]
    end

    subgraph "Servizi Esterni"
        JW[JW.org CDN]
        ZOOM[Zoom Web Client]
    end

    CG -->|HTTPS| DASH
    AD -->|HTTPS| ADMIN
    DASH -->|Crea comandi| API
    API -->|REST| POLL
    WS -->|Push eventi| CMD
    CMD -->|Controlla| BROWSER
    BROWSER -->|Mostra contenuti| BN
    BROWSER -->|Carica video| JW
    BROWSER -->|Videochiamata| ZOOM
    POLL -->|Heartbeat + ACK| API
```

---

## 2. Architettura Backend (Onesiforo Web)

### 2.1 Stack Tecnologico

| Layer | Tecnologia | Versione |
|-------|-----------|---------|
| Framework | Laravel | 12.47.0 |
| PHP | PHP | 8.4.17 |
| Reactive UI | Livewire | 4.x |
| UI Components | Flux UI Free | 2.x |
| Admin Panel | Filament | 5.0 |
| Auth API | Sanctum | 4.x |
| Auth Web | Fortify | 1.x |
| WebSocket | Reverb | 1.x |
| Testing | Pest | 4.x |
| Monitoring | Pulse | 1.x |
| DB (dev) | SQLite | - |
| DB (prod) | MySQL/PostgreSQL | - |

### 2.2 Struttura Directory

```
app/
├── Actions/              # Business logic (13 action classes)
│   ├── AcknowledgeCommandAction
│   ├── AdvancePlaybackSessionAction
│   ├── CancelCommandAction
│   ├── CreatePlaylistAction
│   ├── CreateVolumeCommandAction
│   ├── ExtractJwOrgVideosAction
│   ├── StartPlaybackSessionAction
│   ├── StopPlaybackSessionAction
│   ├── StorePlaybackEventAction
│   └── ProcessHeartbeatAction
├── Concerns/             # Traits riutilizzabili (5)
├── Console/Commands/     # Artisan commands (3)
├── Enums/                # PHP 8.1 enums (11)
├── Events/               # Broadcast events (3)
├── Filament/             # Admin panel resources (4)
├── Http/
│   ├── Controllers/Api/V1/   # API controllers (3)
│   ├── Middleware/            # Custom middleware (1)
│   ├── Requests/Api/V1/      # Form requests (4)
│   └── Resources/Api/V1/     # API resources (5)
├── Livewire/             # Componenti Livewire (17)
│   ├── Dashboard/Controls/   # Controlli OnesiBox (14)
│   └── Settings/             # Impostazioni utente (5)
├── Models/               # Eloquent models (9)
├── Services/             # Service classes (2 + 1 interface)
├── Policies/             # Authorization policies (2)
└── Providers/            # Service providers (3)
```

### 2.3 Domain Model

```mermaid
erDiagram
    User ||--o{ OnesiBoxUser : "gestisce"
    OnesiBox ||--o{ OnesiBoxUser : "assegnata a"
    OnesiBox ||--|| Recipient : "appartiene a"
    OnesiBox ||--o{ Command : "riceve"
    OnesiBox ||--o{ PlaybackEvent : "genera"
    OnesiBox ||--o{ Playlist : "ha"
    OnesiBox ||--o{ PlaybackSession : "esegue"
    Playlist ||--o{ PlaylistItem : "contiene"
    Playlist ||--o{ PlaybackSession : "usata in"
    User ||--o{ Role : "ha ruoli"

    User {
        bigint id PK
        string name
        string email
        string password
        timestamp last_login_at
        timestamp deleted_at
    }

    OnesiBox {
        bigint id PK
        string serial_number UK
        string name
        boolean is_active
        timestamp last_seen_at
        string status
        json system_metrics
        json network_info
    }

    Recipient {
        bigint id PK
        bigint onesi_box_id FK
        string first_name
        string last_name
        string phone
        text address
    }

    Command {
        bigint id PK
        uuid uuid UK
        bigint onesi_box_id FK
        enum type
        json payload
        int priority
        enum status
        timestamp expires_at
        timestamp executed_at
    }

    Playlist {
        bigint id PK
        bigint onesi_box_id FK
        string name
        boolean is_saved
        enum source_type
    }

    PlaylistItem {
        bigint id PK
        bigint playlist_id FK
        string url
        string title
        int position
        int duration_seconds
    }

    PlaybackSession {
        bigint id PK
        uuid uuid UK
        bigint onesi_box_id FK
        bigint playlist_id FK
        enum status
        int duration_minutes
        int current_position
        timestamp started_at
    }

    PlaybackEvent {
        bigint id PK
        bigint onesi_box_id FK
        enum event
        string media_url
        string session_id
    }

    OnesiBoxUser {
        bigint onesi_box_id FK
        bigint user_id FK
        enum permission
    }
```

### 2.4 Pattern Architetturali

#### Action Pattern

Tutta la business logic è incapsulata in **Action classes** (`app/Actions/`). I controller e i componenti Livewire delegano alle Action.

```mermaid
graph LR
    LC[Livewire Component] --> A[Action]
    AC[API Controller] --> A
    A --> M[Model/Eloquent]
    A --> S[Service]
    A --> E[Event]
    A --> J[Job]
```

**Vantaggi:**
- Singola responsabilità per operazione
- Facilmente testabili in isolamento
- Riutilizzabili da controller, Livewire, Artisan

#### Service Pattern

I servizi (`app/Services/`) gestiscono logiche trasversali. L'`OnesiBoxCommandService` è il punto centrale per la creazione e dispatch di comandi.

```mermaid
graph TD
    LW[Livewire Component] -->|dispatchCommand| SVC[OnesiBoxCommandService]
    SVC -->|1. Verifica online| OB[OnesiBox Model]
    SVC -->|2. Crea record| CMD[Command Model]
    SVC -->|3. Dispatch job| JOB[SendOnesiBoxCommand Job]
    SVC -->|4. Fire event| EVT[OnesiBoxCommandSent Event]
    JOB -->|Broadcast| WS[NewCommandAvailable via Reverb]
```

---

## 3. Architettura Client (OnesiBox)

### 3.1 Stack Tecnologico

| Layer | Tecnologia | Versione |
|-------|-----------|---------|
| Runtime | Node.js | >=20 |
| HTTP Client | Axios | 1.13 |
| Browser Control | Playwright | 1.52 |
| WebSocket | pusher-js | 8.4 |
| System Info | systeminformation | 5.30 |
| Logging | Winston + daily-rotate | 3.19 |
| Testing | Jest | 30.2 |
| Target HW | Raspberry Pi | ARM64 |
| Display | Wayland (labwc) | Kiosk mode |

### 3.2 Struttura Directory

```
onesi-box/
├── src/
│   ├── main.js                    # Bootstrap, HTTP server, polling, heartbeat
│   ├── config/config.js           # Configurazione + validazione
│   ├── communication/
│   │   ├── api-client.js          # HTTP client (Axios)
│   │   └── websocket-manager.js   # WebSocket (Pusher/Reverb)
│   ├── commands/
│   │   ├── manager.js             # Pipeline comandi + priorità
│   │   ├── validator.js           # Validazione URL + struttura
│   │   └── handlers/
│   │       ├── media.js           # play/stop/pause/resume + video-ended detection
│   │       ├── zoom.js            # Zoom join/leave via Playwright
│   │       ├── volume.js          # wpctl/pactl/amixer
│   │       ├── system.js          # reboot/shutdown
│   │       ├── service.js         # systemctl restart
│   │       ├── system-info.js     # Diagnostica sistema
│   │       └── logs.js            # Log remoti (sanitizzati)
│   ├── browser/controller.js      # Chromium kiosk (Playwright + fallback spawn)
│   ├── state/state-manager.js     # Stato centralizzato (EventEmitter)
│   ├── logging/
│   │   ├── logger.js              # Winston con rotazione giornaliera
│   │   └── log-sanitizer.js       # Redazione dati sensibili
│   └── watchdog.js                # Integrazione systemd watchdog
├── web/
│   ├── index.html                 # Schermata standby (orologio + stato)
│   ├── player.html                # Player video JW.org
│   ├── app.js                     # Logica standby screen
│   └── styles.css                 # Stili standby
├── config/
│   ├── config.json.example        # Configurazione di esempio
│   └── labwc/                     # Config Wayland compositor
├── scripts/
│   ├── start-kiosk.sh             # Launcher sessione kiosk
│   └── onesibox.service           # Systemd unit template
├── install.sh                     # Script installazione interattivo
├── update.sh                      # Auto-update via git pull
└── tests/unit/                    # Jest unit tests (7 file)
```

### 3.3 Diagramma Componenti

```mermaid
graph TB
    subgraph "OnesiBox Client"
        MAIN[main.js<br/>Bootstrap & Orchestrator]

        subgraph "Communication"
            API[ApiClient<br/>Axios HTTP]
            WSM[WebSocketManager<br/>Pusher/Reverb]
        end

        subgraph "Command Pipeline"
            CM[CommandManager<br/>Dispatch & Priority]
            CV[CommandValidator<br/>URL Whitelist & Schema]
            subgraph "Handlers"
                MH[MediaHandler<br/>play/stop/pause/resume]
                ZH[ZoomHandler<br/>join/leave meeting]
                VH[VolumeHandler<br/>wpctl/pactl/amixer]
                SH[SystemHandler<br/>reboot/shutdown]
                SVH[ServiceHandler<br/>systemctl restart]
                SIH[SystemInfoHandler<br/>diagnostica]
                LH[LogsHandler<br/>log retrieval]
            end
        end

        BC[BrowserController<br/>Playwright Chromium]
        SM[StateManager<br/>EventEmitter Singleton]
        LOG[Logger<br/>Winston + Rotation]
        WD[Watchdog<br/>systemd notify]
    end

    MAIN --> API
    MAIN --> WSM
    MAIN --> CM
    MAIN --> BC
    MAIN --> SM
    MAIN --> WD

    WSM -->|NewCommand| CM
    API -->|Poll commands| CM
    CM --> CV
    CM --> MH & ZH & VH & SH & SVH & SIH & LH
    MH --> BC
    ZH --> BC
    MH & ZH & VH --> SM
    CM -->|ACK| API
```

---

## 4. Flusso di Comunicazione

### 4.1 Comunicazione Ibrida (HTTP + WebSocket)

```mermaid
sequenceDiagram
    participant CG as Caregiver (Browser)
    participant LW as Livewire Component
    participant SVC as CommandService
    participant DB as Database
    participant JOB as Queue Job
    participant REV as Reverb (WebSocket)
    participant OB as OnesiBox Client
    participant API as API Controller

    Note over CG, OB: === INVIO COMANDO ===
    CG->>LW: Click "Play Video"
    LW->>SVC: dispatchCommand(type, payload)
    SVC->>DB: Create Command (pending)
    SVC->>JOB: Dispatch SendOnesiBoxCommand
    SVC-->>LW: Command created
    LW-->>CG: UI feedback

    JOB->>REV: Broadcast NewCommandAvailable
    REV->>OB: WebSocket push (full payload)

    Note over OB: Esecuzione immediata via WS

    alt WebSocket connesso
        OB->>OB: processCommand(data)
    else Fallback: Polling ogni 5s
        OB->>API: GET /commands?status=pending
        API->>DB: Query pending commands
        DB-->>API: Commands[]
        API-->>OB: JSON response
        OB->>OB: processCommands(batch)
    end

    Note over OB, API: === ACKNOWLEDGMENT ===
    OB->>API: POST /commands/{uuid}/ack
    API->>DB: Update command status
    API-->>OB: 200 OK
```

### 4.2 Heartbeat Flow

```mermaid
sequenceDiagram
    participant OB as OnesiBox Client
    participant API as HeartbeatController
    participant ACT as ProcessHeartbeatAction
    participant DB as Database
    participant REV as Reverb
    participant DASH as Dashboard Livewire

    loop Ogni 30 secondi
        OB->>OB: Raccolta metriche (CPU, RAM, disco, rete, WiFi)
        OB->>API: POST /appliances/heartbeat
        API->>ACT: ProcessHeartbeatAction
        ACT->>DB: Update OnesiBox (status, metrics, last_seen_at)
        ACT->>ACT: expireActiveSessionOnIdle()
        ACT->>REV: Broadcast OnesiBoxStatusUpdated
        REV->>DASH: Real-time status update
        DASH->>DASH: Aggiorna UI caregiver
        API-->>OB: 200 OK
    end
```

### 4.3 Playlist Session Flow

```mermaid
sequenceDiagram
    participant CG as Caregiver
    participant SM as SessionManager (Livewire)
    participant START as StartPlaybackSessionAction
    participant ADV as AdvancePlaybackSessionAction
    participant STOP as StopPlaybackSessionAction
    participant DB as Database
    participant OB as OnesiBox Client
    participant API as PlaybackController

    CG->>SM: Avvia sessione (playlist + durata)
    SM->>START: execute(onesiBox, playlist, duration)
    START->>STOP: Ferma sessione attiva (se presente)
    START->>DB: Crea PlaybackSession (active)
    START->>OB: Comando play_media (item #1)

    Note over OB: Riproduce video #1

    OB->>API: POST /playback {event: "completed", media_url: "..."}
    API->>ADV: AdvancePlaybackSessionAction
    ADV->>DB: Lock session (lockForUpdate)
    ADV->>ADV: Verifica media_url == item corrente
    ADV->>DB: Incrementa current_position

    alt Ci sono altri item E non scaduta
        ADV->>OB: Comando play_media (item #2)
    else Playlist finita O sessione scaduta
        ADV->>STOP: StopPlaybackSessionAction
        STOP->>DB: Session status = completed
        STOP->>OB: Comando stop_media
    end
```

---

## 5. Sicurezza

### 5.1 Modello di Autenticazione

```mermaid
graph TB
    subgraph "Web Authentication (Fortify)"
        USER[User] -->|Login + 2FA| FORTIFY[Laravel Fortify]
        FORTIFY -->|Session| SESS[Encrypted Session Cookie]
    end

    subgraph "API Authentication (Sanctum)"
        OBOX[OnesiBox] -->|Bearer Token| SANC[Laravel Sanctum]
        SANC -->|Identify| SANC_DB[(personal_access_tokens)]
    end

    subgraph "Authorization"
        ROLES[Roles: SuperAdmin, Admin, Caregiver]
        PERM[OnesiBox Permissions: Full, ReadOnly]
        POLICY_U[UserPolicy]
        POLICY_O[OnesiBoxPolicy]
    end

    SESS --> ROLES
    ROLES --> POLICY_U & POLICY_O
    SANC --> PERM
```

### 5.2 Sicurezza Client

| Protezione | Implementazione |
|-----------|----------------|
| URL Whitelist | Solo domini jw.org, *.jw-cdn.org, *.zoom.us |
| HTTPS Only | Tutte le URL esterne devono essere HTTPS |
| No Shell Injection | Usa `execFile` invece di `exec` |
| Log Sanitization | Redazione token, password, chiavi, email |
| File Permissions | config.json 600 (solo owner) |
| Timing-safe Auth | `crypto.timingSafeEqual` per API key locale |
| Systemd Hardening | ProtectSystem=strict, PrivateTmp=true |
| CSP Headers | Content-Security-Policy su HTTP server locale |

---

## 6. Infrastruttura e Deploy

### 6.1 Architettura di Deploy

```mermaid
graph TB
    subgraph "Cloud / Server"
        subgraph "Onesiforo Web"
            NGINX[Nginx/Herd]
            LARAVEL[Laravel App]
            QUEUE[Queue Worker]
            REVERB[Reverb WS Server :8080]
            DB[(SQLite/MySQL)]
        end
    end

    subgraph "Rete Domestica Beneficiario"
        subgraph "Raspberry Pi"
            NODE[Node.js OnesiBox]
            CHROMIUM[Chromium Kiosk]
            SYSTEMD[systemd service]
            WATCHDOG[Watchdog]
        end
        TV[📺 TV/Monitor]
    end

    NGINX -->|HTTPS| LARAVEL
    LARAVEL --> DB
    LARAVEL --> QUEUE
    QUEUE --> REVERB

    NODE <-->|HTTPS REST| NGINX
    NODE <-->|WSS| REVERB
    NODE --> CHROMIUM
    CHROMIUM --> TV
    SYSTEMD -->|Gestisce| NODE
    WATCHDOG -->|Monitora| SYSTEMD
```

### 6.2 Flusso di Update

```mermaid
graph LR
    CRON[Cron giornaliero] --> UPDATE[update.sh]
    UPDATE --> GIT[git pull origin main]
    GIT --> CHECK{package.json<br/>cambiato?}
    CHECK -->|Sì| NPM[npm install]
    CHECK -->|No| SKIP[Skip]
    NPM --> MIG[Esegui migrazioni<br/>updates/00X-*.sh]
    SKIP --> MIG
    MIG --> RESTART[systemctl restart onesibox]
```

---

## 7. Tipi di Comando Supportati

| Tipo | Priorità | Scadenza | Backend | Client | Note |
|------|----------|----------|---------|--------|------|
| `play_media` | 2 | 60 min | ✅ | ✅ | Video/audio playback |
| `stop_media` | 2 | 5 min | ✅ | ✅ | Ferma riproduzione |
| `pause_media` | 2 | 5 min | ✅ | ✅ | Pausa (solo Playwright) |
| `resume_media` | 2 | 5 min | ✅ | ✅ | Riprendi da pausa |
| `set_volume` | 3 | 5 min | ✅ | ✅ | Volume 0-100 |
| `join_zoom` | 1 | 5 min | ✅ | ✅ | Entra in videochiamata |
| `leave_zoom` | 1 | 5 min | ✅ | ✅ | Esci da videochiamata |
| `reboot` | 1 | 24h | ✅ | ✅ | Riavvio sistema |
| `shutdown` | 1 | 24h | ✅ | ✅ | Spegnimento |
| `restart_service` | 1 | 5 min | ✅ | ✅ | Restart servizio Node |
| `get_system_info` | 4 | 5 min | ✅ | ✅ | Info diagnostiche |
| `get_logs` | 4 | 5 min | ✅ | ✅ | Log remoti |
| `start_jitsi` | - | - | ✅ | ❌ | Non implementato client |
| `stop_jitsi` | - | - | ✅ | ❌ | Non implementato client |
| `speak_text` | - | - | ✅ | ❌ | Non implementato client |
| `show_message` | - | - | ✅ | ❌ | Non implementato client |
| `start_vnc` | - | - | ✅ | ❌ | Non implementato client |
| `stop_vnc` | - | - | ✅ | ❌ | Non implementato client |
| `update_config` | - | - | ✅ | ❌ | Non implementato client |
