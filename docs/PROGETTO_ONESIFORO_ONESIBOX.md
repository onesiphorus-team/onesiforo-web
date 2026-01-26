# Progetto Onesiforo & OnesiBox

## Documento di Progetto Completo

**Versione:** 1.0
**Data:** Gennaio 2026
**Stato:** Approvato
**Repository:**
- Server: `onesiforo`
- Client: `onesi-box`

---

## Indice

1. [Introduzione](#1-introduzione)
2. [Vision e Obiettivi](#2-vision-e-obiettivi)
3. [Architettura del Sistema](#3-architettura-del-sistema)
4. [Componenti del Sistema](#4-componenti-del-sistema)
5. [Modello Dati](#5-modello-dati)
6. [Pattern di Comunicazione](#6-pattern-di-comunicazione)
7. [Specifica API](#7-specifica-api)
8. [Sicurezza](#8-sicurezza)
9. [Requisiti](#9-requisiti)
10. [Stack Tecnologico](#10-stack-tecnologico)
11. [Deployment e Infrastruttura](#11-deployment-e-infrastruttura)
12. [Roadmap](#12-roadmap)
13. [Glossario](#13-glossario)
14. [Appendici](#14-appendici)

---

## 1. Introduzione

### 1.1 Scopo del Documento

Questo documento fornisce una visione completa e integrata del **Progetto Onesiforo**, comprendente sia la piattaforma web centrale (**Onesiforo**) che il client embedded (**OnesiBox**). Il documento descrive l'architettura, i requisiti, le specifiche tecniche e la roadmap di sviluppo.

### 1.2 Il Nome "Onesiforo"

Il nome deriva da **Onesiforo**, cristiano del I secolo che si distinse per la premura e l'assistenza mostrata verso l'apostolo Paolo durante la sua prigionia a Roma (2 Timoteo 1:16-17). Questo riflette la missione del progetto: portare assistenza e compagnia a chi ne ha bisogno.

### 1.3 Contesto del Progetto

Il progetto nasce dall'esigenza di assistere **persone anziane con mobilità ridotta** che non possono partecipare fisicamente alle adunanze religiose o mantenere contatti regolari con familiari e comunità. La soluzione tecnologica permette ai **caregiver** (familiari o volontari) di inviare contenuti multimediali e gestire videochiamate da remoto, senza richiedere alcuna interazione da parte del beneficiario.

### 1.4 Stakeholder

```mermaid
mindmap
  root((Progetto Onesiforo))
    Beneficiari
      Anziani con mobilità ridotta
      Zero competenze tecnologiche
      Zero interazione richiesta
    Caregiver
      Familiari
      Volontari della comunità
      Competenze base smartphone
    Amministratori
      Gestione piattaforma
      Configurazione appliance
      Supporto tecnico
    Sviluppatori
      Team Onesiphorus
      Contributori open source
```

---

## 2. Vision e Obiettivi

### 2.1 Vision

> *"Portare compagnia spirituale e connessione umana a chi, per limiti fisici, non può raggiungerla autonomamente."*

### 2.2 Obiettivi Strategici

| Obiettivo | Descrizione | Indicatore di Successo |
|-----------|-------------|------------------------|
| **Accessibilità** | Zero interazione richiesta dal beneficiario | 100% operazioni remote |
| **Affidabilità** | Sistema funzionante 24/7 | Uptime > 99.5% |
| **Semplicità** | Interfaccia intuitiva per caregiver | < 3 click per azione |
| **Sicurezza** | Protezione dati e comunicazioni | Zero breach |
| **Scalabilità** | Supporto per crescita utenti | 100+ appliance simultanee |

### 2.3 Casi d'Uso Primari

```mermaid
flowchart TB
    subgraph "Casi d'Uso Principali"
        UC1[📹 Riproduzione<br/>Video JW.org]
        UC2[🎵 Riproduzione<br/>Audio JW.org]
        UC3[📞 Videochiamate<br/>Zoom]
        UC4[💬 Videochiamate<br/>Jitsi]
        UC5[🔊 Messaggi<br/>Vocali TTS]
        UC6[📊 Monitoraggio<br/>Remoto]
        UC7[🔧 Assistenza<br/>Tecnica VNC]
    end

    CG[👨‍👩‍👧 Caregiver] --> UC1
    CG --> UC2
    CG --> UC3
    CG --> UC4
    CG --> UC5
    CG --> UC6

    ADMIN[👨‍💼 Admin] --> UC6
    ADMIN --> UC7

    UC1 --> BEN[👴 Beneficiario]
    UC2 --> BEN
    UC3 --> BEN
    UC4 --> BEN
    UC5 --> BEN
```

---

## 3. Architettura del Sistema

### 3.1 Panoramica Architetturale

Il sistema adotta un'architettura **client-server distribuita** con comunicazione bidirezionale real-time.

```mermaid
C4Context
    title Diagramma di Contesto - Sistema Onesiforo

    Person(caregiver, "Caregiver", "Invia comandi e contenuti")
    Person(admin, "Amministratore", "Gestisce piattaforma")
    Person(beneficiario, "Beneficiario", "Riceve contenuti")

    System(onesiforo, "Onesiforo Web", "Piattaforma di controllo centralizzata")

    System_Ext(onesibox, "OnesiBox", "Appliance Raspberry Pi")
    System_Ext(jworg, "JW.org", "Contenuti multimediali")
    System_Ext(zoom, "Zoom", "Videochiamate")
    System_Ext(jitsi, "Jitsi Meet", "Videochiamate dirette")

    Rel(caregiver, onesiforo, "Controlla", "HTTPS")
    Rel(admin, onesiforo, "Amministra", "HTTPS")
    Rel(onesiforo, onesibox, "Comandi", "WSS/HTTPS")
    Rel(onesibox, beneficiario, "Mostra", "HDMI")
    Rel(onesibox, jworg, "Streaming", "HTTPS")
    Rel(onesibox, zoom, "Partecipa", "WebRTC")
    Rel(onesibox, jitsi, "Chiama", "WebRTC")
```

### 3.2 Architettura a Livelli

```mermaid
graph TB
    subgraph "🌐 Livello Presentazione"
        A[Dashboard Caregiver<br/>Livewire 4 + Flux UI]
        B[Pannello Admin<br/>Filament 5]
        C[UI Locale OnesiBox<br/>HTML/CSS/JS]
    end

    subgraph "⚙️ Livello Applicativo"
        D[API REST<br/>Laravel 12]
        E[WebSocket Server<br/>Laravel Reverb]
        F[Queue Workers<br/>Laravel Horizon]
    end

    subgraph "🧠 Livello Dominio"
        G[Command Service]
        H[Appliance Service]
        I[Media Validator]
        J[Activity Logger]
    end

    subgraph "💾 Livello Persistenza"
        K[(Database<br/>SQLite/MySQL)]
        L[(Cache<br/>Redis)]
        M[(File Storage)]
    end

    subgraph "📱 Livello Client"
        N[Node.js Backend]
        O[Chromium Browser]
        P[System Services]
    end

    A --> D
    B --> D
    D --> G
    E --> G
    G --> H
    G --> I
    G --> J
    H --> K
    H --> L
    J --> K

    N --> D
    N --> E
    N --> O
    O --> C
    N --> P
```

### 3.3 Architettura di Rete

```mermaid
flowchart TB
    subgraph "☁️ Cloud Infrastructure"
        subgraph "Server Onesiforo"
            WEB[Web Server<br/>Nginx]
            APP[Laravel App<br/>PHP-FPM]
            WS[WebSocket<br/>Reverb]
            DB[(Database)]
            REDIS[(Redis)]
        end
    end

    subgraph "🏠 Casa del Beneficiario"
        subgraph "OnesiBox"
            PI[Raspberry Pi 5]
            NODE[Node.js Client]
            CHROME[Chromium Browser]
        end
        TV[📺 TV/Monitor]
        SPEAKER[🔊 Casse Audio]
        WEBCAM[📷 Webcam]
    end

    subgraph "📱 Caregiver"
        PHONE[Smartphone/Tablet]
        PC[Computer]
    end

    subgraph "🌍 Servizi Esterni"
        JW[JW.org CDN]
        ZOOM[Zoom]
        JITSI[Jitsi Meet]
    end

    PHONE -->|HTTPS| WEB
    PC -->|HTTPS| WEB
    WEB --> APP
    APP --> DB
    APP --> REDIS
    APP --> WS

    NODE -->|HTTPS/WSS| WEB
    NODE --> CHROME
    CHROME --> TV
    PI --> SPEAKER
    WEBCAM --> PI

    CHROME -->|HTTPS| JW
    CHROME -->|WebRTC| ZOOM
    CHROME -->|WebRTC| JITSI

    style PI fill:#c5f6c5
    style APP fill:#fff3b0
    style WS fill:#b0d4f1
```

---

## 4. Componenti del Sistema

### 4.1 Onesiforo (Server Web)

#### 4.1.1 Descrizione

**Onesiforo** è la piattaforma web centrale che funge da hub di controllo per tutte le appliance OnesiBox. È sviluppata con **Laravel 12** e fornisce:

- Dashboard per caregiver con stato real-time delle appliance
- Pannello amministrativo completo (Filament 5)
- API REST per comunicazione con le appliance
- WebSocket server per notifiche real-time
- Sistema di autenticazione robusto (Fortify + 2FA)

#### 4.1.2 Struttura dei Moduli

```mermaid
graph LR
    subgraph "Onesiforo Web Application"
        subgraph "Core"
            AUTH[Authentication<br/>Fortify + Sanctum]
            USERS[User Management]
            ROLES[Role & Permissions]
        end

        subgraph "Appliance Management"
            BOXES[OnesiBox CRUD]
            BENEF[Beneficiario CRUD]
            ASSIGN[Associazioni]
        end

        subgraph "Command System"
            CMDS[Command Queue]
            EXEC[Command Execution]
            ACK[Acknowledgment]
        end

        subgraph "Monitoring"
            HB[Heartbeat Receiver]
            PLAY[Playback Events]
            LOGS[Activity Logs]
        end

        subgraph "Real-Time"
            WS[WebSocket Channels]
            BROADCAST[Event Broadcasting]
        end
    end

    AUTH --> USERS
    USERS --> ROLES
    BOXES --> BENEF
    BOXES --> ASSIGN
    CMDS --> EXEC
    EXEC --> ACK
    HB --> LOGS
    PLAY --> LOGS
    CMDS --> WS
    HB --> BROADCAST
```

#### 4.1.3 Tecnologie Server

| Componente | Tecnologia | Versione |
|------------|------------|----------|
| Framework | Laravel | 12.x |
| PHP | PHP | 8.4 |
| Admin Panel | Filament | 5.x |
| Frontend | Livewire + Flux UI | 4.x / 2.x |
| WebSocket | Laravel Reverb | 1.x |
| Auth | Laravel Fortify + Sanctum | 1.x / 4.x |
| Testing | Pest | 4.x |
| Database | SQLite (dev) / MySQL (prod) | - |
| Cache | Redis | - |
| Code Style | Laravel Pint | 1.x |
| Static Analysis | Larastan | 3.x |

### 4.2 OnesiBox (Client Raspberry Pi)

#### 4.2.1 Descrizione

**OnesiBox** è l'appliance hardware installata presso il beneficiario. È basata su **Raspberry Pi 5** e esegue un'applicazione **Node.js** che:

- Riceve comandi dal server Onesiforo
- Controlla un browser Chromium in modalità kiosk
- Riproduce contenuti multimediali
- Gestisce videochiamate automaticamente
- Monitora lo stato del sistema
- Si auto-ripara in caso di errori

#### 4.2.2 Architettura Client

```mermaid
flowchart TB
    subgraph "OnesiBox Client Architecture"
        subgraph "📡 Communication Layer"
            POLL[Polling Client<br/>HTTP every 5s]
            WS_C[WebSocket Client<br/>Real-time]
            HB[Heartbeat Service<br/>Every 30s]
        end

        subgraph "🎯 Application Layer"
            CM[Command Manager<br/>Dispatcher + Queue]
            SM[State Manager<br/>FSM + Persistence]
            MM[Media Manager<br/>Playback Control]
        end

        subgraph "🔧 System Layer"
            SYS[System Monitor<br/>CPU/RAM/Temp]
            WD[Watchdog<br/>Auto-recovery]
            TTS[TTS Engine<br/>espeak-ng]
            VNC[VNC Service<br/>Reverse tunnel]
        end

        subgraph "🖥️ Presentation Layer"
            BROWSER[Chromium Kiosk<br/>Fullscreen]
            UI[Local Web UI<br/>Status + Messages]
        end

        subgraph "⚙️ Handlers"
            H_MEDIA[Media Handler]
            H_ZOOM[Zoom Handler]
            H_VOL[Volume Handler]
            H_SYS[System Handler]
        end
    end

    POLL --> CM
    WS_C --> CM
    HB --> SYS

    CM --> SM
    CM --> H_MEDIA
    CM --> H_ZOOM
    CM --> H_VOL
    CM --> H_SYS

    H_MEDIA --> MM
    MM --> BROWSER
    H_ZOOM --> BROWSER

    WD --> SYS
    WD --> BROWSER

    BROWSER --> UI
```

#### 4.2.3 Tecnologie Client

| Componente | Tecnologia | Versione |
|------------|------------|----------|
| Runtime | Node.js | 20 LTS |
| HTTP Client | axios | ^1.x |
| Logging | winston | ^3.x |
| System Info | systeminformation | ^5.x |
| Browser | Chromium | 120+ |
| TTS | espeak-ng | - |
| OS | Raspberry Pi OS Lite 64-bit | Bookworm |
| Testing | Jest | ^30.x |

#### 4.2.4 Hardware Supportato

| Componente | Specifica | Note |
|------------|-----------|------|
| **CPU** | Raspberry Pi 5 (4GB/8GB RAM) | Pi 4 supportato come legacy |
| **Storage** | microSD 32GB classe A2 | Consigliata A2 per velocità |
| **Display** | Qualsiasi TV/Monitor HDMI | Risoluzione auto-detect |
| **Audio** | Casse USB o audio HDMI | USB consigliato |
| **Webcam** | USB compatibile V4L2 | Es. Logitech C920 |
| **Rete** | Ethernet o WiFi/LTE | LTE via dongle USB |

---

## 5. Modello Dati

### 5.1 Diagramma Entity-Relationship

```mermaid
erDiagram
    USER ||--o{ ROLE_USER : "ha"
    ROLE ||--o{ ROLE_USER : "assegnato a"
    USER ||--o{ ONESI_BOX_USER : "gestisce"
    ONESI_BOX ||--o{ ONESI_BOX_USER : "gestito da"
    ONESI_BOX ||--o{ COMMAND : "riceve"
    ONESI_BOX ||--o{ PLAYBACK_EVENT : "genera"
    ONESI_BOX }o--|| BENEFICIARIO : "assegnato a"
    USER ||--o{ ACTIVITY_LOG : "genera"
    ONESI_BOX ||--o{ PERSONAL_ACCESS_TOKEN : "possiede"

    USER {
        bigint id PK
        string name
        string email UK
        string password
        timestamp email_verified_at
        boolean two_factor_enabled
        timestamps created_at
        timestamps updated_at
    }

    ROLE {
        bigint id PK
        string name UK
        string guard_name
        timestamps created_at
        timestamps updated_at
    }

    ROLE_USER {
        bigint id PK
        bigint user_id FK
        bigint role_id FK
    }

    BENEFICIARIO {
        bigint id PK
        string nome
        string cognome
        string telefono
        string via
        string citta
        string cap
        string provincia
        json contatti_emergenza
        text note
        timestamps created_at
        timestamps updated_at
        timestamp deleted_at
    }

    ONESI_BOX {
        bigint id PK
        string nome
        string serial_number UK
        bigint beneficiario_id FK
        string firmware_version
        timestamp last_seen_at
        boolean is_active
        enum status
        text note
        timestamps created_at
        timestamps updated_at
        timestamp deleted_at
    }

    ONESI_BOX_USER {
        bigint id PK
        bigint onesi_box_id FK
        bigint user_id FK
        enum permission
        timestamps created_at
        timestamps updated_at
    }

    COMMAND {
        bigint id PK
        uuid uuid UK
        bigint onesi_box_id FK
        enum type
        json payload
        int priority
        enum status
        timestamp expires_at
        timestamp executed_at
        string error_code
        text error_message
        timestamps created_at
        timestamps updated_at
    }

    PLAYBACK_EVENT {
        bigint id PK
        bigint onesi_box_id FK
        enum event
        string media_url
        string media_type
        int position
        int duration
        text error_message
        timestamps created_at
    }

    ACTIVITY_LOG {
        bigint id PK
        string log_name
        string description
        string subject_type
        bigint subject_id
        string causer_type
        bigint causer_id
        json properties
        timestamps created_at
        timestamps updated_at
    }

    PERSONAL_ACCESS_TOKEN {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        string name
        string token UK
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamps created_at
        timestamps updated_at
    }
```

### 5.2 Enumerazioni

#### OnesiBoxStatus

| Valore | Descrizione | Icona | Colore |
|--------|-------------|-------|--------|
| `idle` | Dispositivo inattivo/standby | ⏸️ | Grigio |
| `playing` | Riproduzione media in corso | ▶️ | Verde |
| `calling` | Videochiamata attiva | 📞 | Blu |
| `error` | Errore rilevato | ⚠️ | Rosso |

#### CommandType

| Categoria | Tipo | Descrizione | Scadenza |
|-----------|------|-------------|----------|
| **Media** | `play_media` | Riproduce audio/video | 60 min |
| **Media** | `stop_media` | Interrompe riproduzione | 60 min |
| **Media** | `pause_media` | Mette in pausa | 60 min |
| **Media** | `resume_media` | Riprende riproduzione | 60 min |
| **Media** | `set_volume` | Regola volume 0-100 | 60 min |
| **Videochiamata** | `join_zoom` | Entra in riunione Zoom | 60 min |
| **Videochiamata** | `leave_zoom` | Esce da Zoom | 60 min |
| **Videochiamata** | `start_jitsi` | Avvia chiamata Jitsi | 60 min |
| **Videochiamata** | `stop_jitsi` | Termina chiamata Jitsi | 60 min |
| **Comunicazione** | `speak_text` | Sintesi vocale TTS | 60 min |
| **Comunicazione** | `show_message` | Mostra messaggio | 60 min |
| **Sistema** | `reboot` | Riavvia dispositivo | 5 min |
| **Sistema** | `shutdown` | Spegne dispositivo | 5 min |
| **Remoto** | `start_vnc` | Avvia sessione VNC | 5 min |
| **Remoto** | `stop_vnc` | Termina sessione VNC | 5 min |
| **Config** | `update_config` | Aggiorna configurazione | 24 ore |

#### CommandStatus

| Valore | Descrizione | Processabile |
|--------|-------------|--------------|
| `pending` | In attesa di esecuzione | ✅ Sì |
| `completed` | Eseguito con successo | ❌ No |
| `failed` | Esecuzione fallita | ❌ No |
| `expired` | Scaduto prima dell'esecuzione | ❌ No |

#### OnesiBoxPermission

| Valore | Descrizione | Può Visualizzare | Può Comandare |
|--------|-------------|------------------|---------------|
| `full` | Accesso completo | ✅ | ✅ |
| `readonly` | Solo lettura | ✅ | ❌ |

---

## 6. Pattern di Comunicazione

### 6.1 Modalità di Comunicazione

Il sistema supporta due modalità di comunicazione, con fallback automatico:

```mermaid
stateDiagram-v2
    [*] --> WebSocket: Avvio sistema

    WebSocket --> WebSocket: Heartbeat OK
    WebSocket --> Polling: Connessione persa
    WebSocket --> Polling: Errore WSS

    Polling --> WebSocket: Retry ogni 60s
    Polling --> Polling: Server raggiungibile
    Polling --> Offline: 3 fallimenti

    Offline --> Polling: Retry con backoff

    state WebSocket {
        [*] --> Connesso
        Connesso --> Riconnessione: Disconnesso
        Riconnessione --> Connesso: Successo
        Riconnessione --> [*]: Fallimento (→ Polling)
    }
```

| Modalità | Protocollo | Latenza | Utilizzo |
|----------|------------|---------|----------|
| **WebSocket** | WSS (Laravel Reverb) | < 1 secondo | Primaria |
| **Polling** | HTTPS REST | 5-30 secondi | Fallback |

### 6.2 Flusso Ciclo di Vita Appliance

```mermaid
sequenceDiagram
    participant SD as systemd
    participant NODE as Node.js Client
    participant CHROME as Chromium
    participant API as Onesiforo API
    participant WS as WebSocket Server
    participant DB as Database

    Note over SD: Boot Raspberry Pi

    SD->>NODE: Avvia servizio onesibox
    NODE->>NODE: Carica configurazione
    NODE->>CHROME: Avvia Chromium kiosk
    CHROME->>CHROME: Mostra schermata standby

    NODE->>API: POST /heartbeat
    API->>DB: Aggiorna last_seen_at
    API-->>NODE: 200 OK {server_time, next_heartbeat}

    alt WebSocket disponibile
        NODE->>WS: Connessione WSS
        WS->>WS: Autentica token
        WS-->>NODE: Connessione stabilita
        NODE->>WS: Subscribe appliance.{id}
        WS-->>NODE: Subscribed
    else Fallback Polling
        loop Ogni 5 secondi
            NODE->>API: GET /commands?status=pending
            API-->>NODE: Lista comandi
        end
    end

    loop Ogni 30 secondi
        NODE->>API: POST /heartbeat
        API-->>NODE: 200 OK
    end
```

### 6.3 Flusso Esecuzione Comando

```mermaid
sequenceDiagram
    participant CG as Caregiver
    participant WEB as Dashboard
    participant API as Onesiforo API
    participant DB as Database
    participant WS as WebSocket
    participant NODE as OnesiBox Client
    participant CHROME as Chromium

    CG->>WEB: Click "Riproduci Video"
    WEB->>API: POST /commands
    API->>DB: Salva comando (status=pending)
    API->>WS: Broadcast NewCommandAvailable
    API-->>WEB: 201 Created

    WS-->>NODE: Event: NewCommand
    Note over NODE: {uuid, type, priority}

    NODE->>API: GET /commands?status=pending
    API-->>NODE: Lista comandi ordinata

    NODE->>NODE: Valida payload
    NODE->>CHROME: Naviga a URL media
    CHROME->>CHROME: Riproduzione fullscreen

    NODE->>API: POST /commands/{uuid}/ack
    Note over NODE: {status: "success", executed_at}
    API->>DB: Aggiorna comando (status=completed)
    API-->>NODE: 200 OK

    NODE->>API: POST /playback
    Note over NODE: {event: "started", media_url}
    API->>DB: Registra evento
    API->>WS: Broadcast StatusUpdated
    WS-->>WEB: Event: StatusUpdated
    WEB->>WEB: Aggiorna UI real-time
```

### 6.4 Stati del Dispositivo

```mermaid
stateDiagram-v2
    [*] --> Idle: Avvio completato

    Idle --> Playing: play_media
    Idle --> Calling: join_zoom / start_jitsi
    Idle --> Error: Errore sistema

    Playing --> Idle: stop_media / completato
    Playing --> Playing: pause_media / resume_media
    Playing --> Error: Errore riproduzione

    Calling --> Idle: leave_zoom / stop_jitsi
    Calling --> Error: Errore chiamata

    Error --> Idle: Recovery automatico
    Error --> [*]: shutdown

    Idle --> [*]: shutdown
```

---

## 7. Specifica API

### 7.1 Autenticazione

Tutte le API richiedono autenticazione tramite **Laravel Sanctum**:

```http
Authorization: Bearer {appliance_token}
Content-Type: application/json
Accept: application/json
```

Il token viene generato dal pannello amministrativo al momento della registrazione dell'appliance e ha validità di 365 giorni.

### 7.2 Base URL

```
https://api.onesiforo.it/api/v1
```

### 7.3 Endpoint API

#### 7.3.1 Heartbeat

| Attributo | Valore |
|-----------|--------|
| **Endpoint** | `POST /appliances/heartbeat` |
| **Frequenza** | Ogni 30 secondi |
| **Autenticazione** | Bearer Token |

**Request:**
```json
{
  "status": "idle|playing|calling|error",
  "cpu_usage": 45,
  "memory_usage": 62,
  "disk_usage": 30,
  "temperature": 52.5,
  "uptime": 86400,
  "current_media": {
    "url": "https://...",
    "type": "video",
    "position": 120,
    "duration": 300
  }
}
```

**Response 200:**
```json
{
  "data": {
    "server_time": "2026-01-22T10:30:00+00:00",
    "next_heartbeat": 30
  }
}
```

#### 7.3.2 Recupero Comandi

| Attributo | Valore |
|-----------|--------|
| **Endpoint** | `GET /appliances/commands` |
| **Query Params** | `status=pending`, `limit=10` |
| **Autenticazione** | Bearer Token |

**Response 200:**
```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "type": "play_media",
      "payload": {
        "url": "https://www.jw.org/...",
        "type": "video"
      },
      "priority": 3,
      "status": "pending",
      "created_at": "2026-01-22T10:00:00+00:00",
      "expires_at": "2026-01-22T11:00:00+00:00"
    }
  ],
  "meta": {
    "total": 15,
    "pending": 2
  }
}
```

#### 7.3.3 Acknowledgment Comando

| Attributo | Valore |
|-----------|--------|
| **Endpoint** | `POST /commands/{uuid}/ack` |
| **Idempotente** | Sì |
| **Autenticazione** | Bearer Token |

**Request:**
```json
{
  "status": "success|failed|skipped",
  "error_code": "MEDIA_ERR",
  "error_message": "Formato non supportato",
  "executed_at": "2026-01-22T10:30:15+00:00"
}
```

**Response 200:**
```json
{
  "data": {
    "acknowledged": true,
    "command_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed"
  }
}
```

#### 7.3.4 Evento Riproduzione

| Attributo | Valore |
|-----------|--------|
| **Endpoint** | `POST /appliances/playback` |
| **Autenticazione** | Bearer Token |

**Request:**
```json
{
  "event": "started|paused|resumed|stopped|completed|error",
  "media_url": "https://...",
  "media_type": "audio|video",
  "position": 120,
  "duration": 300,
  "error_message": null
}
```

### 7.4 WebSocket Events

#### Canali

| Canale | Tipo | Sottoscrittori |
|--------|------|----------------|
| `private-appliance.{id}` | Privato | OnesiBox Client |
| `private-onesibox.{id}` | Privato | Dashboard Caregiver |

#### Eventi Server → Client

| Evento | Descrizione | Payload |
|--------|-------------|---------|
| `NewCommand` | Nuovo comando disponibile | `{uuid, type, priority, expires_at}` |
| `ConfigUpdated` | Configurazione aggiornata | `{key, value}` |

#### Eventi Client → Server (via API)

| Evento | Descrizione | Endpoint |
|--------|-------------|----------|
| `StatusUpdated` | Cambio stato appliance | POST /heartbeat |
| `CommandExecuted` | Comando completato | POST /commands/{uuid}/ack |
| `PlaybackChanged` | Evento playback | POST /playback |

---

## 8. Sicurezza

### 8.1 Panoramica Sicurezza

```mermaid
flowchart TB
    subgraph "🔐 Autenticazione"
        AUTH1[Caregiver Login<br/>Email + Password]
        AUTH2[2FA TOTP<br/>Obbligatorio Admin]
        AUTH3[Appliance Token<br/>Bearer 64 chars]
    end

    subgraph "🛡️ Autorizzazione"
        AUTHZ1[Role-Based Access<br/>Admin / Caregiver]
        AUTHZ2[Associazione User-Box<br/>Full / ReadOnly]
        AUTHZ3[Policy Laravel<br/>Verifica ownership]
    end

    subgraph "🔒 Protezione Dati"
        PROT1[HTTPS/TLS 1.3<br/>Tutte le comunicazioni]
        PROT2[WSS<br/>WebSocket sicure]
        PROT3[Argon2id<br/>Password hashing]
        PROT4[AES-256<br/>Dati sensibili at-rest]
    end

    subgraph "🚫 Difese"
        DEF1[Rate Limiting<br/>60 req/min IP]
        DEF2[Input Validation<br/>Form Requests]
        DEF3[URL Whitelist<br/>Solo JW.org]
        DEF4[CSRF Protection<br/>Token per form]
    end

    AUTH1 --> AUTH2
    AUTH2 --> AUTHZ1
    AUTH3 --> AUTHZ2
    AUTHZ1 --> AUTHZ3
    AUTHZ2 --> AUTHZ3

    PROT1 --> DEF1
    PROT2 --> DEF2
    DEF3 --> PROT3
```

### 8.2 Misure di Sicurezza

| Area | Misura | Implementazione |
|------|--------|-----------------|
| **Trasporto** | Crittografia | HTTPS/TLS 1.3 obbligatorio |
| **Autenticazione Web** | Multi-fattore | TOTP via Google Authenticator |
| **Autenticazione API** | Token Bearer | Sanctum, 64 caratteri |
| **Rate Limiting** | Protezione DDoS | 60 req/min per IP |
| **Input Validation** | Sanitizzazione | Form Request classes |
| **XSS** | Protezione | Auto-escape Blade |
| **CSRF** | Protezione | Token Laravel |
| **SQL Injection** | Protezione | Prepared statements (Eloquent) |
| **Password** | Hashing | Argon2id |
| **Session** | Sicurezza | HttpOnly, Secure, SameSite=Strict |
| **Audit** | Logging | spatie/laravel-activitylog |

### 8.3 Domini Autorizzati (URL Whitelist)

```
# Contenuti JW.org
jw.org
www.jw.org
wol.jw.org
*.jw-cdn.org
download-a.akamaihd.net

# Videochiamate
zoom.us
*.zoom.us
meet.jit.si
*.jitsi.net
```

### 8.4 Hardening Raspberry Pi

- SSH disabilitato (accesso solo via Cloudflare Tunnel)
- Firewall (ufw) con regole minime
- Utente dedicato non-root per l'applicazione
- Partizione /tmp in RAM (tmpfs)
- Log rotation automatica
- Watchdog hardware abilitato

---

## 9. Requisiti

### 9.1 Requisiti Funzionali Prioritari

```mermaid
pie title Distribuzione Requisiti per Priorità
    "Alta" : 31
    "Media" : 13
    "Bassa" : 6
```

#### Server (Onesiforo)

| ID | Priorità | Requisito |
|----|----------|-----------|
| RF-001 | Alta | Riproduzione audio da JW.org |
| RF-002 | Alta | Riproduzione video da JW.org |
| RF-003 | Alta | Controllo riproduzione (play/pause/stop/volume) |
| RF-004 | Alta | Validazione URL JW.org |
| RF-005 | Alta | Visualizzazione stato online/offline |
| RF-006 | Alta | Visualizzazione stato riproduzione |
| RF-008 | Alta | Avvio riunione Zoom |
| RF-014 | Alta | CRUD Caregiver |
| RF-015 | Alta | CRUD Appliance |
| RF-016 | Alta | Associazione Caregiver-Appliance |
| RF-019 | Alta | Login Caregiver |
| RF-020 | Alta | Autenticazione 2FA |
| RF-022 | Alta | Activity Log audit |

#### Client (OnesiBox)

| ID | Priorità | Requisito |
|----|----------|-----------|
| RFC-001 | Alta | Connessione al server |
| RFC-002 | Alta | Ricezione comandi via polling |
| RFC-003 | Alta | Ricezione comandi via WebSocket |
| RFC-004 | Alta | Invio heartbeat |
| RFC-005 | Alta | Conferma esecuzione comandi |
| RFC-006 | Alta | Riproduzione video JW.org |
| RFC-010 | Alta | Partecipazione riunione Zoom |
| RFC-014 | Alta | Riavvio dispositivo |
| RFC-020 | Alta | Raccolta metriche sistema |
| RFC-023 | Alta | Watchdog applicativo |
| RFC-024 | Alta | Watchdog hardware |
| RFC-026 | Alta | Schermata di standby |

### 9.2 Requisiti Non Funzionali

| Categoria | Requisito | Specifica |
|-----------|-----------|-----------|
| **Prestazioni** | Tempo risposta UI | < 3 secondi |
| **Prestazioni** | Latenza comandi (WS) | < 1 secondo |
| **Prestazioni** | Latenza comandi (Polling) | < 10 secondi |
| **Prestazioni** | Capacità | 100+ appliance simultanee |
| **Affidabilità** | Disponibilità | 99.5% uptime |
| **Affidabilità** | Recovery | < 2 minuti dopo crash |
| **Sicurezza** | Crittografia | TLS 1.3 |
| **Sicurezza** | Password | Argon2id |
| **Usabilità** | Click per azione | Max 3 |
| **Usabilità** | Zero interazione beneficiario | 100% |
| **Manutenibilità** | Test coverage | > 80% |
| **Scalabilità** | Orizzontale | Multi-server ready |

---

## 10. Stack Tecnologico

### 10.1 Stack Completo

```mermaid
graph TB
    subgraph "🖥️ Server Stack"
        PHP[PHP 8.4]
        LARAVEL[Laravel 12]
        FILAMENT[Filament 5]
        LIVEWIRE[Livewire 4]
        REVERB[Laravel Reverb]
        SANCTUM[Laravel Sanctum]
        FORTIFY[Laravel Fortify]
    end

    subgraph "🗄️ Data Stack"
        SQLITE[(SQLite Dev)]
        MYSQL[(MySQL Prod)]
        REDIS[(Redis Cache)]
    end

    subgraph "🎨 Frontend Stack"
        FLUX[Flux UI 2]
        TAILWIND[Tailwind CSS 4]
        ALPINE[Alpine.js]
    end

    subgraph "📱 Client Stack"
        NODEJS[Node.js 20 LTS]
        CHROMIUM[Chromium 120+]
        AXIOS[axios]
        WINSTON[winston]
        SYSINFO[systeminformation]
    end

    subgraph "🧪 Testing Stack"
        PEST[Pest 4]
        JEST[Jest 30]
        LARASTAN[Larastan 3]
        PINT[Laravel Pint]
    end

    subgraph "🖥️ Infrastructure"
        RPI[Raspberry Pi 5]
        RPIOS[RPi OS Bookworm 64-bit]
        SYSTEMD[systemd]
        NGINX[Nginx]
    end

    LARAVEL --> PHP
    FILAMENT --> LARAVEL
    LIVEWIRE --> LARAVEL
    REVERB --> LARAVEL
    SANCTUM --> LARAVEL
    FORTIFY --> LARAVEL

    LARAVEL --> MYSQL
    LARAVEL --> REDIS
    LARAVEL --> SQLITE

    LIVEWIRE --> FLUX
    FLUX --> TAILWIND
    LIVEWIRE --> ALPINE

    NODEJS --> RPI
    CHROMIUM --> RPI
    RPI --> RPIOS
    RPIOS --> SYSTEMD
```

### 10.2 Dipendenze Principali

#### Server (composer.json)

| Package | Versione | Scopo |
|---------|----------|-------|
| `laravel/framework` | ^12.0 | Core framework |
| `filament/filament` | ^5.0 | Admin panel |
| `livewire/livewire` | ^4.0 | Reactive components |
| `livewire/flux` | ^2.0 | UI components |
| `laravel/reverb` | ^1.0 | WebSocket server |
| `laravel/sanctum` | ^4.0 | API authentication |
| `laravel/fortify` | ^1.0 | Auth scaffolding |
| `spatie/laravel-activitylog` | ^4.0 | Audit logging |
| `larastan/larastan` | ^3.0 | Static analysis |
| `pestphp/pest` | ^4.0 | Testing |
| `laravel/pint` | ^1.0 | Code style |

#### Client (package.json)

| Package | Versione | Scopo |
|---------|----------|-------|
| `axios` | ^1.x | HTTP client |
| `winston` | ^3.x | Logging |
| `winston-daily-rotate-file` | ^5.x | Log rotation |
| `systeminformation` | ^5.x | System metrics |
| `jest` | ^30.x | Testing |
| `eslint` | ^9.x | Linting |

---

## 11. Deployment e Infrastruttura

### 11.1 Architettura di Deployment

```mermaid
flowchart TB
    subgraph "☁️ Cloud (Produzione)"
        subgraph "Server Web"
            LB[Load Balancer<br/>Nginx]
            APP1[App Server 1<br/>Laravel]
            APP2[App Server 2<br/>Laravel]
            WS1[WebSocket 1<br/>Reverb]
            WS2[WebSocket 2<br/>Reverb]
        end

        subgraph "Database Cluster"
            DB_M[(MySQL Primary)]
            DB_R[(MySQL Replica)]
        end

        subgraph "Cache"
            REDIS_C[(Redis Cluster)]
        end

        subgraph "Storage"
            S3[Object Storage<br/>S3-compatible]
        end
    end

    subgraph "🏠 Edge (OnesiBox)"
        BOX1[OnesiBox #1]
        BOX2[OnesiBox #2]
        BOXN[OnesiBox #N]
    end

    subgraph "👤 Users"
        CG[Caregiver Browser]
        ADMIN[Admin Browser]
    end

    CG --> LB
    ADMIN --> LB
    LB --> APP1
    LB --> APP2
    APP1 --> DB_M
    APP2 --> DB_R
    APP1 --> REDIS_C
    APP2 --> REDIS_C
    APP1 --> S3
    APP2 --> S3
    WS1 --> REDIS_C
    WS2 --> REDIS_C

    BOX1 --> LB
    BOX2 --> LB
    BOXN --> LB
    BOX1 --> WS1
    BOX2 --> WS2
```

### 11.2 Setup Raspberry Pi

#### Script di Installazione

```bash
#!/bin/bash
# scripts/setup.sh

# Installa dipendenze
sudo apt update
sudo apt install -y chromium-browser x11-xserver-utils \
    unclutter espeak-ng x11vnc

# Installa Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Clona repository
cd /opt
sudo git clone https://github.com/onesiphorus-team/onesibox-client.git onesibox
cd onesibox
sudo npm install --production

# Configura servizio systemd
sudo cp scripts/onesibox.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable onesibox
sudo systemctl start onesibox

# Abilita watchdog hardware
echo "dtparam=watchdog=on" | sudo tee -a /boot/config.txt
```

#### Servizio systemd

```ini
[Unit]
Description=OnesiBox Client
After=network-online.target graphical.target
Wants=network-online.target

[Service]
Type=simple
User=pi
WorkingDirectory=/opt/onesibox
ExecStart=/usr/bin/node main.js
Restart=always
RestartSec=10
WatchdogSec=60
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

### 11.3 Configurazione Chromium Kiosk

```bash
chromium-browser \
  --kiosk \
  --noerrdialogs \
  --disable-infobars \
  --disable-session-crashed-bubble \
  --disable-restore-session-state \
  --autoplay-policy=no-user-gesture-required \
  --use-fake-ui-for-media-stream \
  --enable-features=WebRTCPipeWireCapturer \
  --disable-features=TranslateUI \
  --check-for-update-interval=31536000 \
  --disable-component-update \
  --disable-background-networking \
  --disable-sync \
  --disable-default-apps \
  --no-first-run \
  --start-fullscreen \
  http://localhost:3000
```

---

## 12. Roadmap

### 12.1 Timeline di Sviluppo

```mermaid
gantt
    title Roadmap Progetto Onesiforo
    dateFormat  YYYY-MM-DD

    section Fase 1 - MVP
    Setup infrastruttura           :done, 2026-01-01, 7d
    Auth + User Management         :done, 2026-01-08, 7d
    OnesiBox Management            :done, 2026-01-15, 7d
    API Heartbeat                  :done, 2026-01-20, 5d
    API Commands (polling)         :active, 2026-01-25, 7d
    Client MVP                     :2026-01-25, 14d

    section Fase 2 - Stabilizzazione
    WebSocket Integration          :2026-02-08, 14d
    Dashboard Caregiver            :2026-02-15, 14d
    Watchdog System                :2026-02-22, 7d
    Testing & Bug Fixing           :2026-03-01, 14d

    section Fase 3 - Funzionalità Avanzate
    Jitsi Meet Integration         :2026-03-15, 14d
    TTS (Text-to-Speech)           :2026-03-22, 7d
    VNC Reverse                    :2026-03-29, 7d
    Scheduled Playback             :2026-04-05, 14d

    section Fase 4 - Polish
    OTA Updates                    :2026-04-19, 14d
    Mobile Optimization            :2026-04-26, 7d
    Documentation                  :2026-05-03, 7d
    Beta Testing                   :2026-05-10, 21d
    Production Release             :milestone, 2026-06-01, 0d
```

### 12.2 Milestone e Deliverable

#### Fase 1 - MVP (Gennaio 2026)

| Deliverable | Stato | Note |
|-------------|-------|------|
| ✅ Setup Laravel 12 + Filament 5 | Completato | - |
| ✅ User Management con ruoli | Completato | Admin, Caregiver |
| ✅ CRUD Beneficiario | Completato | Con soft delete |
| ✅ CRUD OnesiBox | Completato | Con token generation |
| ✅ API Heartbeat | Completato | Con metriche sistema |
| 🔄 API Commands (GET/ACK) | In corso | Polling mode |
| 🔄 API Playback Events | In corso | - |
| ⏳ Client Node.js MVP | Pianificato | - |

#### Fase 2 - Stabilizzazione (Febbraio 2026)

| Deliverable | Stato |
|-------------|-------|
| ⏳ Integrazione Laravel Reverb | Pianificato |
| ⏳ Dashboard Caregiver Livewire | Pianificato |
| ⏳ Watchdog multi-livello | Pianificato |
| ⏳ Test coverage > 80% | Pianificato |

#### Fase 3 - Funzionalità Avanzate (Marzo-Aprile 2026)

| Deliverable | Stato |
|-------------|-------|
| ⏳ Jitsi Meet integration | Pianificato |
| ⏳ TTS (espeak-ng + Web Speech) | Pianificato |
| ⏳ VNC Reverse | Pianificato |
| ⏳ Programmazione automatica | Pianificato |

#### Fase 4 - Production Ready (Maggio-Giugno 2026)

| Deliverable | Stato |
|-------------|-------|
| ⏳ OTA Updates | Pianificato |
| ⏳ Ottimizzazione mobile | Pianificato |
| ⏳ Documentazione completa | Pianificato |
| ⏳ Beta testing | Pianificato |
| ⏳ Release produzione | Pianificato |

---

## 13. Glossario

| Termine | Definizione |
|---------|-------------|
| **Appliance** | Dispositivo OnesiBox basato su Raspberry Pi |
| **Beneficiario** | Persona anziana assistita che utilizza l'OnesiBox |
| **Caregiver** | Operatore autorizzato a controllare una o più appliance |
| **Command** | Istruzione inviata dal server all'appliance |
| **Heartbeat** | Segnale periodico per confermare connettività e stato |
| **Kiosk Mode** | Modalità browser fullscreen senza controlli utente |
| **Polling** | Tecnica di recupero dati basata su richieste periodiche |
| **TTS** | Text-to-Speech, sintesi vocale |
| **VNC** | Virtual Network Computing, controllo remoto desktop |
| **Watchdog** | Componente di monitoraggio e auto-riparazione |
| **WebSocket** | Protocollo per comunicazione bidirezionale real-time |

---

## 14. Appendici

### 14.1 Codici di Errore

#### Server

| Codice | HTTP | Descrizione |
|--------|------|-------------|
| E001 | 401 | Token non valido |
| E002 | 404 | Risorsa non trovata |
| E003 | 403 | Non autorizzato |
| E004 | 410 | Comando scaduto |
| E005 | 422 | URL media non valido |
| E006 | 422 | Tipo comando non supportato |
| E007 | 503 | Appliance offline |
| E008 | 429 | Rate limit superato |
| E009 | 500 | Errore interno |
| E010 | 408 | Timeout esecuzione |

#### Client

| Codice | Descrizione |
|--------|-------------|
| C001 | Errore connessione server |
| C002 | Token non valido |
| C003 | Comando non riconosciuto |
| C004 | Payload non valido |
| C005 | URL non raggiungibile |
| C006 | Errore riproduzione |
| C007 | Errore Zoom |
| C008 | Errore TTS |
| C009 | Errore VNC |
| C010 | Risorse insufficienti |

### 14.2 Struttura Payload Comandi

#### play_media

```json
{
  "url": "https://www.jw.org/...",
  "type": "video|audio",
  "title": "Titolo opzionale",
  "loop": false,
  "volume": 80
}
```

#### join_zoom

```json
{
  "meeting_id": "123456789",
  "password": "abc123",
  "display_name": "Nome visualizzato"
}
```

#### speak_text

```json
{
  "text": "Messaggio da leggere",
  "language": "it-IT",
  "speed": 1.0,
  "voice": "female"
}
```

#### show_message

```json
{
  "title": "Titolo",
  "body": "Corpo del messaggio",
  "duration": 30,
  "type": "info|warning|alert",
  "sound": true
}
```

### 14.3 Configurazione Client

```json
{
  "server_url": "https://api.onesiforo.it/api/v1",
  "appliance_id": "uuid-here",
  "appliance_token": "token-here",
  "polling_interval_seconds": 5,
  "heartbeat_interval_seconds": 30,
  "websocket_enabled": true,
  "default_volume": 80,
  "watchdog_enabled": true,
  "watchdog_timeout_seconds": 60,
  "tts_engine": "espeak",
  "tts_language": "it"
}
```

### 14.4 Riferimenti

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Filament 5 Documentation](https://filamentphp.com/docs)
- [Livewire 4 Documentation](https://livewire.laravel.com/docs)
- [Laravel Reverb Documentation](https://laravel.com/docs/reverb)
- [Raspberry Pi Documentation](https://www.raspberrypi.com/documentation/)
- [Chromium Command Line Switches](https://peter.sh/experiments/chromium-command-line-switches/)

---

## Storico Revisioni

| Versione | Data | Autore | Modifiche |
|----------|------|--------|-----------|
| 1.0 | Gennaio 2026 | Team Onesiphorus | Prima stesura completa |

---

**Onesiphorus Team** - *"Per la premura mostrata"*
