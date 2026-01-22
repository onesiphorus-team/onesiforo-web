# Data Model: OnesiBox API Webservices

**Feature**: 003-onesibox-api-ws
**Date**: 2026-01-22

## Entity Relationship Diagram

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│   OnesiBox      │       │    Command      │       │ PlaybackEvent   │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id (PK)         │───┐   │ id (PK)         │   ┌───│ id (PK)         │
│ name            │   │   │ onesi_box_id(FK)│───┘   │ onesi_box_id(FK)│
│ serial_number   │   │   │ type            │       │ event           │
│ recipient_id    │   │   │ payload (JSON)  │       │ media_url       │
│ firmware_version│   │   │ priority        │       │ media_type      │
│ last_seen_at    │   │   │ status          │       │ position        │
│ is_active       │   └──>│ created_at      │       │ duration        │
│ ...             │       │ expires_at      │       │ error_message   │
└─────────────────┘       │ executed_at     │       │ created_at      │
                          │ error_code      │       └─────────────────┘
                          │ error_message   │
                          └─────────────────┘
```

## Entities

### Command

Rappresenta un'istruzione da eseguire sull'appliance OnesiBox.

#### Table: `commands`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| id | bigint | NO | auto | Primary key |
| uuid | uuid | NO | - | UUID pubblico per API |
| onesi_box_id | bigint | NO | - | FK to onesi_boxes |
| type | varchar(50) | NO | - | CommandType enum value |
| payload | json | YES | NULL | Command-specific data |
| priority | tinyint | NO | 3 | 1=alta, 5=bassa |
| status | varchar(20) | NO | 'pending' | CommandStatus enum |
| created_at | datetime | NO | now() | Timestamp creazione |
| expires_at | datetime | NO | - | Scadenza calcolata |
| executed_at | datetime | YES | NULL | Timestamp esecuzione |
| error_code | varchar(10) | YES | NULL | Codice errore (E001-E010) |
| error_message | text | YES | NULL | Messaggio errore |

#### Indexes

| Name | Columns | Type |
|------|---------|------|
| PRIMARY | id | PRIMARY |
| commands_uuid_unique | uuid | UNIQUE |
| commands_onesi_box_id_status_idx | onesi_box_id, status | INDEX |
| commands_expires_at_idx | expires_at | INDEX |

#### Foreign Keys

| Column | References | On Delete |
|--------|------------|-----------|
| onesi_box_id | onesi_boxes.id | CASCADE |

#### Validation Rules

| Field | Rules |
|-------|-------|
| type | required, enum:CommandType |
| payload | nullable, array, valid for type |
| priority | required, integer, between:1,5 |
| status | required, enum:CommandStatus |

#### State Transitions

```
         ┌──────────┐
         │ PENDING  │
         └────┬─────┘
              │
    ┌─────────┼─────────┐
    │         │         │
    ▼         ▼         ▼
┌────────┐ ┌────────┐ ┌─────────┐
│COMPLETED│ │ FAILED │ │ EXPIRED │
└────────┘ └────────┘ └─────────┘
```

- **PENDING** → **COMPLETED**: Appliance conferma esecuzione con successo
- **PENDING** → **FAILED**: Appliance conferma esecuzione con errore
- **PENDING** → **EXPIRED**: Sistema rileva expires_at < now()

---

### PlaybackEvent

Rappresenta un evento di riproduzione multimediale sull'appliance.

#### Table: `playback_events`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| id | bigint | NO | auto | Primary key |
| onesi_box_id | bigint | NO | - | FK to onesi_boxes |
| event | varchar(20) | NO | - | PlaybackEventType enum |
| media_url | varchar(2048) | NO | - | URL del contenuto |
| media_type | varchar(10) | NO | - | 'audio' o 'video' |
| position | int | YES | NULL | Posizione in secondi |
| duration | int | YES | NULL | Durata totale in secondi |
| error_message | text | YES | NULL | Messaggio errore (se event=error) |
| created_at | datetime | NO | now() | Timestamp evento |

#### Indexes

| Name | Columns | Type |
|------|---------|------|
| PRIMARY | id | PRIMARY |
| playback_events_onesi_box_id_idx | onesi_box_id | INDEX |
| playback_events_created_at_idx | created_at | INDEX |

#### Foreign Keys

| Column | References | On Delete |
|--------|------------|-----------|
| onesi_box_id | onesi_boxes.id | CASCADE |

#### Validation Rules

| Field | Rules |
|-------|-------|
| event | required, enum:PlaybackEventType |
| media_url | required, string, max:2048, url |
| media_type | required, in:audio,video |
| position | nullable, integer, min:0 |
| duration | nullable, integer, min:0 |
| error_message | nullable, string, max:1000 |

#### Retention Policy

- Eventi conservati per 30 giorni
- Job schedulato giornaliero per eliminazione eventi scaduti

---

## Enums

### CommandType

```php
enum CommandType: string
{
    // Media
    case PlayMedia = 'play_media';
    case StopMedia = 'stop_media';
    case PauseMedia = 'pause_media';
    case ResumeMedia = 'resume_media';
    case SetVolume = 'set_volume';

    // Video Calls
    case JoinZoom = 'join_zoom';
    case LeaveZoom = 'leave_zoom';
    case StartJitsi = 'start_jitsi';
    case StopJitsi = 'stop_jitsi';

    // Communication
    case SpeakText = 'speak_text';
    case ShowMessage = 'show_message';

    // System
    case Reboot = 'reboot';
    case Shutdown = 'shutdown';

    // Remote Access
    case StartVnc = 'start_vnc';
    case StopVnc = 'stop_vnc';

    // Configuration
    case UpdateConfig = 'update_config';
}
```

### CommandStatus

```php
enum CommandStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
```

### PlaybackEventType

```php
enum PlaybackEventType: string
{
    case Started = 'started';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Stopped = 'stopped';
    case Completed = 'completed';
    case Error = 'error';
}
```

---

## Relationships

### OnesiBox (existing model - to extend)

```php
// Add to OnesiBox model:

/**
 * Get the commands for this OnesiBox.
 */
public function commands(): HasMany
{
    return $this->hasMany(Command::class);
}

/**
 * Get pending commands for this OnesiBox.
 */
public function pendingCommands(): HasMany
{
    return $this->commands()
        ->where('status', CommandStatus::Pending)
        ->where('expires_at', '>', now())
        ->orderBy('priority')
        ->orderBy('created_at');
}

/**
 * Get playback events for this OnesiBox.
 */
public function playbackEvents(): HasMany
{
    return $this->hasMany(PlaybackEvent::class);
}
```

---

## Command Payload Schemas

### play_media

```json
{
    "url": "string (required, URL to JW.org media)",
    "media_type": "string (required, 'audio' | 'video')",
    "autoplay": "boolean (optional, default: true)"
}
```

### set_volume

```json
{
    "level": "integer (required, 0-100)"
}
```

### join_zoom

```json
{
    "meeting_url": "string (optional)",
    "meeting_id": "string (optional)",
    "password": "string (optional)"
}
```

### start_jitsi

```json
{
    "room_name": "string (required)",
    "display_name": "string (optional)"
}
```

### speak_text

```json
{
    "text": "string (required, max 500 chars)",
    "language": "string (optional, default: 'it')",
    "voice": "string (optional, 'male' | 'female')"
}
```

### show_message

```json
{
    "title": "string (required)",
    "body": "string (required)",
    "duration": "integer (optional, seconds, default: 10)"
}
```

### reboot / shutdown

```json
{
    "delay": "integer (optional, seconds, default: 0)"
}
```

### start_vnc

```json
{
    "server_host": "string (required)",
    "server_port": "integer (required)"
}
```

### update_config

```json
{
    "config_key": "string (required)",
    "config_value": "mixed (required)"
}
```

---

## Migration Pseudo-code

### create_commands_table

```php
Schema::create('commands', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('onesi_box_id')->constrained()->cascadeOnDelete();
    $table->string('type', 50);
    $table->json('payload')->nullable();
    $table->tinyInteger('priority')->default(3);
    $table->string('status', 20)->default('pending');
    $table->timestamp('expires_at');
    $table->timestamp('executed_at')->nullable();
    $table->string('error_code', 10)->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();

    $table->index(['onesi_box_id', 'status']);
    $table->index('expires_at');
});
```

### create_playback_events_table

```php
Schema::create('playback_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('onesi_box_id')->constrained()->cascadeOnDelete();
    $table->string('event', 20);
    $table->string('media_url', 2048);
    $table->string('media_type', 10);
    $table->unsignedInteger('position')->nullable();
    $table->unsignedInteger('duration')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamp('created_at');

    $table->index('onesi_box_id');
    $table->index('created_at');
});
```
