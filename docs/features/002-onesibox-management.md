# Feature 002: OnesiBox Management

**Versione:** 1.0
**Data:** Gennaio 2026
**Branch:** `feature/002-onesibox-management`
**Stato:** Implementato

---

## 1. Panoramica

Questa feature implementa la gestione delle appliance OnesiBox e il primo endpoint API per la comunicazione polling (heartbeat). Include:

- Modelli dati per Beneficiario e OnesiBox
- Sistema di autenticazione API tramite Laravel Sanctum
- Endpoint heartbeat per il monitoraggio dello stato delle appliance
- Pannello amministrativo Filament per la gestione

---

## 2. Modelli Dati

### 2.1 Beneficiario

Rappresenta la persona anziana assistita che utilizza l'OnesiBox.

**Tabella:** `beneficiarios`

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `id` | bigint | Chiave primaria |
| `nome` | string | Nome del beneficiario |
| `cognome` | string | Cognome del beneficiario |
| `telefono` | string (nullable) | Numero di telefono |
| `via` | string (nullable) | Indirizzo - via |
| `citta` | string (nullable) | Indirizzo - citta |
| `cap` | string(5) (nullable) | Codice postale |
| `provincia` | string(2) (nullable) | Sigla provincia |
| `contatti_emergenza` | json (nullable) | Array di contatti di emergenza |
| `note` | text (nullable) | Note aggiuntive |
| `created_at` | timestamp | Data creazione |
| `updated_at` | timestamp | Data ultimo aggiornamento |
| `deleted_at` | timestamp (nullable) | Soft delete |

**Relazioni:**
- `hasOne` OnesiBox

**Attributi Computati:**
- `nome_completo`: Nome e cognome concatenati
- `indirizzo_completo`: Via, CAP, Citta (Provincia) formattato

### 2.2 OnesiBox

Rappresenta l'appliance hardware installata presso il beneficiario.

**Tabella:** `onesi_boxes`

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `id` | bigint | Chiave primaria |
| `nome` | string | Nome identificativo dell'appliance |
| `serial_number` | string (unique) | Numero seriale univoco |
| `beneficiario_id` | bigint (nullable) | FK al beneficiario associato |
| `firmware_version` | string (nullable) | Versione firmware installata |
| `last_seen_at` | timestamp (nullable) | Ultimo heartbeat ricevuto |
| `is_active` | boolean | Se l'appliance e abilitata |
| `note` | text (nullable) | Note aggiuntive |
| `created_at` | timestamp | Data creazione |
| `updated_at` | timestamp | Data ultimo aggiornamento |
| `deleted_at` | timestamp (nullable) | Soft delete |

**Relazioni:**
- `belongsTo` Beneficiario
- `belongsToMany` User (tramite pivot `onesi_box_user`)

**Metodi:**
- `isOnline()`: Ritorna `true` se `last_seen_at` e entro gli ultimi 5 minuti
- `recordHeartbeat()`: Aggiorna `last_seen_at` al timestamp corrente
- `userHasFullPermission(User $user)`: Verifica se l'utente ha permessi completi
- `userCanView(User $user)`: Verifica se l'utente puo visualizzare l'appliance

### 2.3 Tabella Pivot onesi_box_user

Associazione many-to-many tra User (caregiver) e OnesiBox.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `id` | bigint | Chiave primaria |
| `user_id` | bigint | FK all'utente caregiver |
| `onesi_box_id` | bigint | FK all'appliance |
| `permission` | string | Livello permesso: `full` o `readonly` |
| `created_at` | timestamp | Data creazione |
| `updated_at` | timestamp | Data ultimo aggiornamento |

---

## 3. Autenticazione API

### 3.1 Laravel Sanctum

L'autenticazione delle appliance utilizza Laravel Sanctum con token personali. Ogni OnesiBox genera il proprio token tramite il trait `HasApiTokens`.

**Generazione Token:**
```php
$onesiBox = OnesiBox::find($id);
$token = $onesiBox->createToken('onesibox-api-token');
// $token->plainTextToken contiene il token da configurare sull'appliance
```

### 3.2 Middleware EnsureOnesiBoxOwnsToken

Middleware custom che verifica:
1. Presenza del Bearer token
2. Validita del token
3. Corrispondenza tra token e OnesiBox nella route

**Errori restituiti:**

| Codice HTTP | Error Code | Messaggio |
|-------------|------------|-----------|
| 401 | E001 | Token di autenticazione non fornito |
| 401 | E001 | Token di autenticazione non valido |
| 404 | E002 | Appliance non trovata |
| 403 | E003 | Token non autorizzato per questa appliance |

---

## 4. API Heartbeat

### 4.1 Endpoint

| Attributo | Valore |
|-----------|--------|
| **Metodo** | `POST` |
| **Path** | `/api/v1/appliances/{onesiBox}/heartbeat` |
| **Route Name** | `api.v1.appliances.heartbeat` |
| **Middleware** | `EnsureOnesiBoxOwnsToken` |

### 4.2 Request

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Body:**

| Campo | Tipo | Obbligatorio | Validazione |
|-------|------|--------------|-------------|
| `status` | enum | Si | `idle`, `playing`, `calling`, `error` |
| `cpu_usage` | integer | No | 0-100 |
| `memory_usage` | integer | No | 0-100 |
| `disk_usage` | integer | No | 0-100 |
| `temperature` | numeric | No | 0-150 |
| `uptime` | integer | No | >= 0 |
| `current_media` | object | No | Vedi sotto |

**Struttura current_media:**

| Campo | Tipo | Obbligatorio | Validazione |
|-------|------|--------------|-------------|
| `url` | string | Si (se current_media presente) | URL valido |
| `type` | string | Si (se current_media presente) | `audio` o `video` |
| `position` | integer | No | >= 0 |
| `duration` | integer | No | >= 0 |

### 4.3 Response

**Success (200 OK):**
```json
{
    "data": {
        "server_time": "2026-01-21T10:30:00+00:00",
        "next_heartbeat": 30
    }
}
```

**Errore - Appliance Disabilitata (403 Forbidden):**
```json
{
    "message": "Appliance disabilitata.",
    "error_code": "E002"
}
```

**Errore - Validazione (422 Unprocessable Entity):**
```json
{
    "message": "Lo stato dell'appliance e obbligatorio.",
    "errors": {
        "status": ["Lo stato dell'appliance e obbligatorio."]
    }
}
```

---

## 5. Pannello Amministrativo

### 5.1 BeneficiarioResource

**Path:** `/admin/beneficiarios`

**Funzionalita:**
- Lista con ricerca per nome/cognome
- Filtro per soft delete
- Creazione/modifica beneficiari
- Visualizzazione contatti emergenza
- Soft delete e restore

### 5.2 OnesiBoxResource

**Path:** `/admin/onesi-boxes`

**Funzionalita:**
- Lista con indicatore stato online/offline
- Ricerca per nome/seriale
- Filtri per stato attivazione e connessione
- Creazione appliance con generazione token automatica
- Rigenerazione token (con conferma)
- Associazione a beneficiario
- Conteggio caregiver associati
- Soft delete e restore

**Azione Genera Token:**
Quando si crea un'appliance o si rigenera il token, viene mostrata una notifica persistente con il token in chiaro. Il token e visibile solo in quel momento e deve essere copiato immediatamente.

---

## 6. Enumerazioni

### 6.1 OnesiBoxStatus

```php
enum OnesiBoxStatus: string
{
    case Idle = 'idle';       // Inattivo
    case Playing = 'playing'; // In riproduzione
    case Calling = 'calling'; // In chiamata
    case Error = 'error';     // Errore
}
```

### 6.2 OnesiBoxPermission

```php
enum OnesiBoxPermission: string
{
    case Full = 'full';         // Controllo completo
    case ReadOnly = 'readonly'; // Solo visualizzazione
}
```

---

## 7. File Implementati

### Migrazioni
- `database/migrations/2026_01_21_164346_create_personal_access_tokens_table.php`
- `database/migrations/2026_01_21_164437_create_beneficiarios_table.php`
- `database/migrations/2026_01_21_164442_create_onesi_boxes_table.php`
- `database/migrations/2026_01_21_164514_create_onesi_box_user_table.php`

### Modelli
- `app/Models/Beneficiario.php`
- `app/Models/OnesiBox.php`

### Enumerazioni
- `app/Enums/OnesiBoxStatus.php`
- `app/Enums/OnesiBoxPermission.php`

### API
- `app/Http/Controllers/Api/V1/HeartbeatController.php`
- `app/Http/Requests/Api/V1/HeartbeatRequest.php`
- `app/Http/Resources/Api/V1/HeartbeatResource.php`
- `app/Http/Middleware/EnsureOnesiBoxOwnsToken.php`
- `routes/api.php`

### Filament Resources
- `app/Filament/Resources/Beneficiarios/BeneficiarioResource.php`
- `app/Filament/Resources/Beneficiarios/Pages/*.php`
- `app/Filament/Resources/Beneficiarios/Schemas/BeneficiarioForm.php`
- `app/Filament/Resources/Beneficiarios/Tables/BeneficiariosTable.php`
- `app/Filament/Resources/OnesiBoxes/OnesiBoxResource.php`
- `app/Filament/Resources/OnesiBoxes/Pages/*.php`
- `app/Filament/Resources/OnesiBoxes/Schemas/OnesiBoxForm.php`
- `app/Filament/Resources/OnesiBoxes/Tables/OnesiBoxesTable.php`

### Factories e Seeders
- `database/factories/BeneficiarioFactory.php`
- `database/factories/OnesiBoxFactory.php`
- `database/seeders/BeneficiarioSeeder.php`
- `database/seeders/OnesiBoxSeeder.php`

### Test
- `tests/Feature/Api/V1/HeartbeatApiTest.php`

---

## 8. Test Coverage

Il file `tests/Feature/Api/V1/HeartbeatApiTest.php` include 21 test che coprono:

**Autenticazione:**
- Heartbeat con token valido
- Rifiuto senza token
- Rifiuto con token invalido
- Rifiuto quando token appartiene ad altra appliance

**Business Logic:**
- Aggiornamento timestamp last_seen_at
- Rifiuto quando appliance disabilitata
- Rifiuto per appliance inesistente

**Validazione:**
- Campo status obbligatorio
- Status deve essere enum valido
- Tutti gli status enum accettati
- Range cpu_usage (0-100)
- Range memory_usage (0-100)
- Range temperature (0-150)
- Uptime non negativo
- Payload completo con tutti i campi
- current_media richiede url e type
- current_media.url deve essere URL valido
- current_media.type deve essere audio o video

---

## 9. Prossimi Passi

1. **Fase 1 - Completamento MVP:**
   - Endpoint GET commands per polling
   - Endpoint POST commands/{id}/ack
   - Endpoint POST playback

2. **Fase 2 - WebSocket:**
   - Integrazione Laravel Reverb
   - Canali privati per appliance
   - Eventi real-time
