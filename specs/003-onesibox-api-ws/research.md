# Research: OnesiBox API Webservices

**Feature**: 003-onesibox-api-ws
**Date**: 2026-01-22

## Research Topics

### 1. API Authentication Pattern (Sanctum with OnesiBox)

**Decision**: Utilizzare il pattern esistente di HeartbeatRequest dove l'autenticazione Sanctum identifica direttamente l'OnesiBox tramite il token.

**Rationale**: Il modello OnesiBox implementa gia `AuthenticatableContract` e usa il trait `HasApiTokens`. L'HeartbeatRequest dimostra come verificare che il token appartenga a un'istanza OnesiBox (non User).

**Pattern da replicare**:
```php
public function authorize(): bool
{
    $tokenable = $this->user();
    return $tokenable instanceof OnesiBox;
}

public function onesiBox(): OnesiBox
{
    return $this->user();
}
```

**Alternatives considered**:
- Middleware custom per validazione token → Piu complesso, duplica logica esistente
- Route model binding con token in header → Non standard per Laravel

### 2. Command Expiration Strategy

**Decision**: Scadenza variabile per tipo comando, gestita durante la query GET con marcatura automatica come "expired".

**Rationale**:
- Comandi urgenti (reboot, shutdown): 5 minuti
- Comandi media (play, pause, etc.): 1 ora
- Comandi di configurazione: 24 ore

**Implementation approach**:
1. Colonna `expires_at` calcolata al momento della creazione basata su `CommandType`
2. Scope `pending()` che filtra automaticamente i comandi scaduti e li marca come expired
3. Nessun job schedulato necessario - la pulizia avviene on-demand

**Alternatives considered**:
- Job schedulato per marcare expired → Overhead non necessario
- Scadenza fissa per tutti i comandi → Non adatto per comandi critici

### 3. Idempotent Acknowledgment Pattern

**Decision**: Risposta 200 OK per acknowledgment di comandi gia processati (completed/failed/expired).

**Rationale**: L'idempotenza e fondamentale per la resilienza in caso di:
- Retry dopo timeout di rete
- Duplicazione di richieste
- Race conditions

**Implementation approach**:
```php
public function acknowledge(Command $command): JsonResponse
{
    // Se gia processato, ritorna successo senza modificare
    if ($command->status !== CommandStatus::Pending) {
        return CommandResource::success($command);
    }

    // Processa normalmente
    $command->update([
        'status' => $request->status,
        'executed_at' => now(),
        // ...
    ]);

    return CommandResource::success($command);
}
```

**Alternatives considered**:
- 409 Conflict → Confonde l'appliance, richiede logica extra client-side
- 422 Unprocessable → Semanticamente scorretto

### 4. PlaybackEvent Storage Pattern

**Decision**: Storico completo persistito per 30 giorni con soft delete automatico.

**Rationale**:
- Supporta la funzionalita "cronologia riproduzioni" per il caregiver
- 30 giorni e il periodo di retention definito nei requisiti (RF-007)
- Consente analisi e debug

**Implementation approach**:
1. Tabella `playback_events` con FK a `onesi_boxes`
2. Job schedulato giornaliero per eliminare eventi > 30 giorni
3. Indici su `onesi_box_id` e `created_at` per query efficienti

**Alternatives considered**:
- Solo stato corrente → Non soddisfa requisiti di cronologia
- Storage infinito → Crescita database incontrollata

### 5. API Resource Structure

**Decision**: Seguire il pattern Laravel standard con `data` wrapper.

**Rationale**: Coerenza con HeartbeatResource esistente e convenzioni Laravel.

**Response structure**:
```json
{
    "data": {
        "id": "uuid",
        "type": "play_media",
        "payload": {...},
        "priority": 1,
        "created_at": "2026-01-22T10:00:00Z",
        "expires_at": "2026-01-22T11:00:00Z"
    }
}
```

**Collection structure**:
```json
{
    "data": [
        {...command1...},
        {...command2...}
    ],
    "meta": {
        "total": 5,
        "pending": 3
    }
}
```

### 6. Error Code Mapping

**Decision**: Utilizzare i codici di errore definiti nel documento di architettura (E001-E010).

| Code | HTTP | Scenario |
|------|------|----------|
| E001 | 401 | Token non valido o mancante |
| E002 | 404 | Appliance non trovata |
| E003 | 403 | Token non autorizzato per questa appliance |
| E004 | 422 | Comando scaduto |
| E005 | 422 | URL media non valido |
| E006 | 422 | Tipo comando non supportato |
| E007 | 503 | Appliance offline |
| E008 | 429 | Rate limit superato |
| E009 | 500 | Errore interno |
| E010 | 504 | Timeout esecuzione |

### 7. Scramble Documentation

**Decision**: Utilizzare PHPDoc annotations per documentazione automatica Scramble.

**Rationale**: Scramble genera automaticamente OpenAPI spec da PHPDoc, minimizzando duplicazione.

**Pattern**:
```php
/**
 * Retrieve pending commands for the authenticated appliance.
 *
 * @response array{data: array<CommandResource>, meta: array{total: int, pending: int}}
 * @response 401 array{message: string, error_code: string}
 * @response 403 array{message: string, error_code: string}
 */
public function index(GetCommandsRequest $request): CommandCollection
```

### 8. Command Type Enum

**Decision**: Creare enum `CommandType` con tutti i tipi definiti nell'architettura.

**Values**:
- `PlayMedia`, `StopMedia`, `PauseMedia`, `ResumeMedia`, `SetVolume`
- `JoinZoom`, `LeaveZoom`, `StartJitsi`, `StopJitsi`
- `SpeakText`, `ShowMessage`
- `Reboot`, `Shutdown`
- `StartVnc`, `StopVnc`
- `UpdateConfig`

**Expiration mapping** (metodo statico sull'enum):
```php
public function defaultExpiresInMinutes(): int
{
    return match($this) {
        self::Reboot, self::Shutdown => 5,
        self::PlayMedia, self::StopMedia, ... => 60,
        self::UpdateConfig => 1440, // 24 hours
    };
}
```

## Research Conclusions

Tutti gli aspetti tecnici sono stati chiariti. L'implementazione puo procedere seguendo i pattern esistenti nel codebase con le decisioni documentate sopra.

### Key Implementation Notes

1. **Autenticazione**: Replicare pattern HeartbeatRequest
2. **Scadenza comandi**: Logica in scope Eloquent, non job schedulati
3. **Idempotenza**: 200 OK per operazioni gia completate
4. **Retention**: Job schedulato solo per cleanup eventi > 30 giorni
5. **Documentazione**: PHPDoc per Scramble auto-generation
