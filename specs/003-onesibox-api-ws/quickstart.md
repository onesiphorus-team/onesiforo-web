# Quickstart: OnesiBox API Webservices

Guida rapida per l'integrazione delle API OnesiBox-to-Onesiforo.

## Prerequisiti

- OnesiBox registrata nel sistema con token Sanctum valido
- Token memorizzato sull'appliance per le richieste API

## Autenticazione

Tutte le richieste richiedono autenticazione tramite Bearer token:

```bash
curl -X GET "https://onesiforo.test/api/v1/appliances/commands" \
  -H "Authorization: Bearer {your_token}" \
  -H "Accept: application/json"
```

## Endpoint Principali

### 1. Recupero Comandi Pendenti

```bash
GET /api/v1/appliances/commands
```

**Parametri Query:**
- `status`: `pending` (default) | `all`
- `limit`: 1-50 (default: 10)

**Response (200):**
```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "type": "play_media",
      "payload": {
        "url": "https://www.jw.org/...",
        "media_type": "video",
        "autoplay": true
      },
      "priority": 1,
      "status": "pending",
      "created_at": "2026-01-22T10:00:00Z",
      "expires_at": "2026-01-22T11:00:00Z"
    }
  ],
  "meta": {
    "total": 5,
    "pending": 3
  }
}
```

### 2. Conferma Esecuzione Comando

```bash
POST /api/v1/commands/{uuid}/ack
```

**Request Body (successo):**
```json
{
  "status": "success",
  "executed_at": "2026-01-22T10:05:00Z"
}
```

**Request Body (errore):**
```json
{
  "status": "failed",
  "error_code": "E005",
  "error_message": "URL non raggiungibile",
  "executed_at": "2026-01-22T10:05:00Z"
}
```

**Response (200):**
```json
{
  "data": {
    "acknowledged": true,
    "command_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed"
  }
}
```

### 3. Aggiornamento Stato Riproduzione

```bash
POST /api/v1/appliances/playback
```

**Request Body (avvio):**
```json
{
  "event": "started",
  "media_url": "https://www.jw.org/...",
  "media_type": "video",
  "duration": 3600
}
```

**Request Body (pausa):**
```json
{
  "event": "paused",
  "media_url": "https://www.jw.org/...",
  "media_type": "video",
  "position": 1234,
  "duration": 3600
}
```

**Request Body (errore):**
```json
{
  "event": "error",
  "media_url": "https://www.jw.org/...",
  "media_type": "video",
  "error_message": "Codec video non supportato"
}
```

**Response (200):**
```json
{
  "data": {
    "logged": true,
    "event_id": 12345
  }
}
```

## Tipi di Comando

| Tipo | Descrizione | Scadenza |
|------|-------------|----------|
| `play_media` | Riproduce audio/video | 1 ora |
| `stop_media` | Ferma riproduzione | 1 ora |
| `pause_media` | Mette in pausa | 1 ora |
| `resume_media` | Riprende riproduzione | 1 ora |
| `set_volume` | Imposta volume | 1 ora |
| `join_zoom` | Entra in riunione Zoom | 1 ora |
| `leave_zoom` | Esce da riunione Zoom | 1 ora |
| `start_jitsi` | Avvia videochiamata Jitsi | 1 ora |
| `stop_jitsi` | Termina videochiamata | 1 ora |
| `speak_text` | Sintesi vocale TTS | 1 ora |
| `show_message` | Mostra messaggio | 1 ora |
| `reboot` | Riavvia appliance | 5 min |
| `shutdown` | Spegne appliance | 5 min |
| `start_vnc` | Avvia sessione VNC | 5 min |
| `stop_vnc` | Termina VNC | 5 min |
| `update_config` | Aggiorna configurazione | 24 ore |

## Codici di Errore

| Codice | HTTP | Descrizione |
|--------|------|-------------|
| E001 | 401 | Token non valido o mancante |
| E002 | 404 | Risorsa non trovata |
| E003 | 403 | Appliance non autorizzata |
| E004 | 422 | Comando scaduto |
| E005 | 422 | URL media non valido |
| E006 | 422 | Tipo comando non supportato |
| E007 | 503 | Appliance offline |
| E008 | 429 | Rate limit superato |
| E009 | 500 | Errore interno |
| E010 | 504 | Timeout esecuzione |

## Flusso Tipico (Polling)

```
┌─────────────┐                         ┌─────────────┐
│  OnesiBox   │                         │  Onesiforo  │
└──────┬──────┘                         └──────┬──────┘
       │                                       │
       │  1. GET /appliances/commands          │
       │──────────────────────────────────────>│
       │                                       │
       │  2. Commands list                     │
       │<──────────────────────────────────────│
       │                                       │
       │  3. Execute command locally           │
       │                                       │
       │  4. POST /commands/{id}/ack           │
       │──────────────────────────────────────>│
       │                                       │
       │  5. Acknowledgment                    │
       │<──────────────────────────────────────│
       │                                       │
       │  6. POST /appliances/playback         │
       │  (if media command)                   │
       │──────────────────────────────────────>│
       │                                       │
       │  7. Event logged                      │
       │<──────────────────────────────────────│
       │                                       │
       ▼                                       ▼
   (Repeat every 5 seconds)
```

## Rate Limiting

- 120 richieste/minuto per token autenticato
- Header `X-RateLimit-Remaining` indica richieste rimanenti
- HTTP 429 quando limite superato

## Retry Strategy

```
Tentativo 1: immediato
Tentativo 2: dopo 1 secondo
Tentativo 3: dopo 5 secondi
Fallimento: log errore, skip comando
```

## Documentazione API

La documentazione interattiva completa e disponibile su:

```
GET /docs/api
```
