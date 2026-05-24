# OnesiBox Custom Commands — Design

**Date:** 2026-05-24
**Status:** Draft, pending implementation plan
**Scope:** Cross-repo — `onesiforo-web` (Laravel/Filament) + `onesi-box` (Node.js client)

## Problem

Ogni installazione OnesiBox può richiedere automazioni specifiche del sito (es. controllo HDMI della TV LG via `to-box.sh` / `to-tv.sh` sul box di Travaglini). Oggi questi script esistono sul box ma non sono raggiungibili dalla dashboard del caregiver: l'unico modo per invocarli è SSH, fuori dalla portata dell'utente finale.

Serve un meccanismo per **registrare** comandi shell specifici per ogni box, **lanciarli** dalla dashboard del caregiver, **tracciarne** l'esito — senza compromettere la postura di sicurezza del device.

## Goals

- Admin in Filament definisce, per ciascun `OnesiBox`, una lista di comandi custom (`name`, `description`, `script_name`, `static_args`, `icon`).
- Caregiver con permission `Full` vede questi comandi nella dashboard del box e li lancia con un click.
- Il box esegue lo script con tracciamento `Pending/Completed/Failed` e ACK al server.
- Caregiver riceve toast di esito.
- Nessun parametro dinamico, nessun output streaming, nessun upload script in MVP.

## Non-Goals (out of scope)

- Parametri dinamici dall'utente al click (un domani: tabella parameters + form generato).
- Streaming stdout/stderr al frontend.
- Upload `.sh` da backoffice.
- Scheduling (cron-like) per comandi custom.
- Versioning degli script o pacchettizzazione.

## Architecture

I comandi custom **riusano** la pipeline `Command` esistente — non introducono un canale parallelo:

```
Filament (admin)         Dashboard (caregiver)            OnesiBox
─────────────────        ─────────────────────            ─────────
CustomCommand            click bottone                    custom-script.js
CRUD per box        ──►  Command::create()      ──ws──►   execFile(whitelist_dir/script_name, static_args)
                                                          ACK {status, error_code}
                         toast OK/Failed         ◄──ws──
```

Vantaggi del riuso: stato (`CommandStatus`), routing WebSocket, pivot di permission, rate limit, `expires_at`, activity log Spatie sono già funzionanti.

## Data Model

### Nuova tabella `onesi_box_custom_commands`

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint pk | |
| `onesi_box_id` | bigint FK | `onCascadeDelete` |
| `name` | string(100) | mostrato come label nel bottone dashboard |
| `description` | string(500) nullable | tooltip / sottotitolo |
| `script_name` | string(100) | basename, regex `^[a-zA-Z0-9_.\-]+\.sh$`, no slash, no `..` |
| `static_args` | json | array di stringhe, default `[]` |
| `icon` | string(100) nullable | nome Heroicon (es. `heroicon-o-tv`) |
| `sort_order` | int default 0 | ordinamento dashboard |
| `is_enabled` | bool default true | nasconde dal dashboard senza eliminare |
| `created_at` / `updated_at` / `deleted_at` | timestamps + soft delete | coerente con altri modelli |

Indici: `(onesi_box_id, is_enabled, sort_order)` per la query dashboard.

### Relazioni

- `OnesiBox::customCommands(): HasMany<CustomCommand>`.
- `CustomCommand::onesiBox(): BelongsTo<OnesiBox>`.
- Activity log via `LogsActivityAllDirty` (coerente con `OnesiBox`, `OnesiBoxUser`).

### Enum updates

Aggiungere `CommandType::CustomScript` in `App\Enums\CommandType` con:
- `defaultExpiresInMinutes()` → 5
- `label()` → "Comando personalizzato"
- Priorità: equivalente alle azioni media (`COMMAND_PRIORITY = 2` lato box).

Nuovi error code lato box, range E1xx:
- `E114 CUSTOM_SCRIPT_FAILED` — exit code != 0
- `E115 CUSTOM_SCRIPT_NOT_FOUND` — file non esiste o non eseguibile
- `E116 CUSTOM_SCRIPT_INVALID_NAME` — `script_name` non passa la regex / path traversal

## Filament Backoffice

`CustomCommandsRelationManager` registrato in `OnesiBoxResource` come nuovo tab "Comandi personalizzati" (non repeater inline — tabella dedicata abilita search/sort/soft-delete).

### Form

- `name` — `TextInput`, required, max 100
- `description` — `Textarea`, opzionale, rows 2
- `script_name` — `TextInput`, required, validazione regex `^[a-zA-Z0-9_.\-]+\.sh$`, helper text: "Solo basename. Deve esistere sul box in `/opt/onesibox/custom-scripts/`."
- `static_args` — `TagsInput`, stringhe, opzionale
- `icon` — `Select` da lista curata di Heroicon "azione" (~10 voci: tv, play, power, refresh, bolt, signal, …)
- `sort_order` — `TextInput::numeric`, default 0
- `is_enabled` — `Toggle`, default on

### Table

Colonne: `sort_order`, `name`, `script_name`, `is_enabled` (badge). Filtri: `is_enabled`. Riordinamento drag-and-drop sulla colonna `sort_order` (`reorderable('sort_order')`).

### Policy

- Solo utenti con ruolo admin Filament possono vedere/editare. Allineato al resto di `OnesiBoxResource`.
- Filament **non** carica file `.sh`: il deploy degli script è responsabilità di chi ha SSH al box (trust boundary esplicito — vedi §Security).

## Dashboard Utente

In `App\Livewire\Dashboard\OnesiBoxDetail` (esistente), nuova sezione "Comandi personalizzati" collocata sotto i `Controls/` standard.

### Visibilità

Sezione visibile **solo se tutte** le condizioni sono vere:
1. `$this->permission() === OnesiBoxPermission::Full`
2. `$onesiBox->customCommands()->where('is_enabled', true)->exists()`

Se manca anche una sola condizione, la sezione non viene renderizzata (no placeholder vuoto).

### UI

Per ogni `CustomCommand` abilitato (ordinato per `sort_order`, poi `name`):

- Card / pulsante con `icon` (renderizzato con `@svg($icon)`) + `name` + `description` come hint.
- Bottone disabilitato (con tooltip "Box offline") se `$this->isOnline() === false`.
- Click → action Livewire:

```php
public function runCustomCommand(int $customCommandId): void
{
    $cmd = $this->onesiBox->customCommands()->where('is_enabled', true)->findOrFail($customCommandId);
    abort_unless($this->permission() === OnesiBoxPermission::Full, 403);

    Command::create([
        'onesi_box_id' => $this->onesiBox->id,
        'type' => CommandType::CustomScript,
        'payload' => [
            'custom_command_id' => $cmd->id,
            'script_name' => $cmd->script_name,
            'static_args' => $cmd->static_args ?? [],
        ],
        'status' => CommandStatus::Pending,
    ]);
    // Auth user attribution is captured by LogsActivityAllDirty (Spatie causer_id).

    Notification::make()->success()->title(__('Comando inviato'))->body($cmd->name)->send();
}
```

### Esito

L'ACK dal box arriva via WebSocket e aggiorna `Command::status`. Il listener Livewire esistente di `refreshStatus()` deve riconoscere `CommandType::CustomScript` e mostrare:
- Verde "Comando eseguito" + `$cmd->name`.
- Rossa "Comando fallito: <error_code>" se `Failed`.

## OnesiBox Handler

### Directory whitelist

- Path: `/opt/onesibox/custom-scripts/`.
- Configurabile via env `ONESIBOX_CUSTOM_SCRIPTS_DIR` (default invariato).
- Owner: `admin:admin`, mode `0755`. File: mode `0755`.
- Creazione: a cura dello script `install.sh` del box (idempotente).

### Nuovo file `src/commands/handlers/custom-script.js`

```js
const path = require('path');
const fs = require('fs/promises');
const { execFile } = require('child_process');
const { promisify } = require('util');
const logger = require('../../logging/logger');
const { HandlerError } = require('../errors');
const { ERROR_CODES } = require('../validator');

const execFileAsync = promisify(execFile);
const SCRIPTS_DIR = process.env.ONESIBOX_CUSTOM_SCRIPTS_DIR || '/opt/onesibox/custom-scripts';
const NAME_RE = /^[a-zA-Z0-9_.\-]+\.sh$/;
const TIMEOUT_MS = 30_000;
const MAX_OUTPUT_BYTES = 1 * 1024 * 1024;

async function executeCustomScript(command /*, browserController */) {
  const { script_name, static_args = [] } = command.payload || {};
  if (typeof script_name !== 'string' || !NAME_RE.test(script_name)) {
    throw new HandlerError(ERROR_CODES.CUSTOM_SCRIPT_INVALID_NAME, 'invalid script_name');
  }

  const wanted = path.join(SCRIPTS_DIR, script_name);
  let real;
  try {
    real = await fs.realpath(wanted);
  } catch {
    throw new HandlerError(ERROR_CODES.CUSTOM_SCRIPT_NOT_FOUND, `script not found: ${script_name}`);
  }
  if (real !== wanted && !real.startsWith(SCRIPTS_DIR + path.sep)) {
    throw new HandlerError(ERROR_CODES.CUSTOM_SCRIPT_INVALID_NAME, 'symlink escapes whitelist dir');
  }
  try {
    await fs.access(real, fs.constants.X_OK);
  } catch {
    throw new HandlerError(ERROR_CODES.CUSTOM_SCRIPT_NOT_FOUND, 'script not executable');
  }

  const args = Array.isArray(static_args) ? static_args.map(String) : [];
  try {
    const { stdout, stderr } = await execFileAsync(real, args, {
      timeout: TIMEOUT_MS,
      maxBuffer: MAX_OUTPUT_BYTES,
    });
    logger.info('Custom script executed', { script_name, exit_code: 0, stdout_bytes: stdout.length, stderr_bytes: stderr.length });
  } catch (err) {
    logger.error('Custom script failed', { script_name, code: err.code, signal: err.signal, killed: err.killed, message: err.message });
    if (err.killed && err.signal === 'SIGTERM') {
      throw new HandlerError(ERROR_CODES.EXECUTION_TIMEOUT, `script timed out after ${TIMEOUT_MS}ms`);
    }
    throw new HandlerError(ERROR_CODES.CUSTOM_SCRIPT_FAILED, `exit ${err.code}`);
  }
}

module.exports = { executeCustomScript };
```

### Registrazione

`src/main.js` (o `commands/handlers/index.js`): `commandManager.registerHandler('custom_script', executeCustomScript)`.

### Validator

`src/commands/validator.js`:
- Aggiungere `'custom_script'` ai `VALID_COMMAND_TYPES`.
- Schema-check del payload: `script_name` stringa che matcha `NAME_RE`, `static_args` opzionale array di stringhe.
- Mappare `custom_script` in `getErrorCodeForCommandType()`.

### Manager

`src/commands/manager.js`: aggiungere `'custom_script': 2` in `COMMAND_PRIORITY`.

## Security Posture

- **Filesystem boundary**: solo `/opt/onesibox/custom-scripts/`, validato via `realpath` (blocca symlink escape).
- **No shell**: `execFile`, args passati come array Node — niente template stringa, niente expansion.
- **Whitelist regex** sul `script_name` (server PHP + box JS, doppia validazione).
- **Trust del deploy degli script**: chi può scrivere in `custom-scripts/` ha già SSH al box ⇒ può fare qualunque cosa. Il backoffice **non può** aggiungere file, solo registrare quelli già presenti. Compromise del CMS ⇒ può solo lanciare script già installati. Compromise dell'SSH ⇒ già game over indipendentemente da questa feature.
- **Permission**: solo `OnesiBoxPermission::Full` può lanciare. Attribuzione dell'utente che ha invocato il comando catturata via `LogsActivityAllDirty` (Spatie `causer_id`).
- **Timeout** 30s fisso. Se un caso d'uso richiede più tempo, evoluzione futura aggiunge campo `timeout_seconds` (con clamp lato box).
- **Output non esposto**: stdout/stderr loggati lato box ma non ritornati al server ⇒ no leak di dettagli di sistema verso UI.

## Error Handling

| Scenario | Server status | Error code | Toast caregiver |
|---|---|---|---|
| Script eseguito con exit 0 | Completed | — | "Comando eseguito" (verde) |
| Script exit != 0 | Failed | E114 | "Comando fallito: E114" (rossa) |
| Script inesistente / non eseguibile | Failed | E115 | "Comando fallito: E115" (rossa) |
| `script_name` non valido | Failed | E116 | "Comando fallito: E116" (rossa) |
| Timeout 30s | Failed | E010 | "Comando fallito: E010" (rossa) |
| Box offline al click | Pending → Expired | E004 | "Comando scaduto" (grigia) |
| Caregiver ReadOnly | Bloccato in Livewire | 403 | (sezione non visibile a monte) |

## Testing

### Lato server (Laravel)

- Unit `CustomCommand`: cast `static_args` array, scope `enabled()`, validation regex `script_name`.
- Filament feature: admin crea / edita / soft-delete; ordinamento drag rispecchia `sort_order`.
- Livewire feature `OnesiBoxDetail::runCustomCommand`:
  - Full + abilitato → crea `Command` con payload corretto.
  - ReadOnly → 403.
  - Box offline → bottone disabilitato (asserito sul render).
  - `CustomCommand` di un altro box → `ModelNotFoundException`.
- Activity log: una entry per create/update del `CustomCommand`, una per ogni `Command` lanciato.

### Lato box (Node)

- Unit `custom-script.js` con `execFile` mockato:
  - Happy path (exit 0).
  - Exit code != 0 → `E114`.
  - File non esiste → `E115`.
  - File non eseguibile → `E115`.
  - `script_name` con `/`, `..`, char non whitelisted → `E116`.
  - Symlink che punta fuori da `SCRIPTS_DIR` → `E116`.
  - Timeout (process killed by SIGTERM) → `E010`.
- Validator: payload con `script_name` non stringa, `static_args` non array → invalid.
- e2e leggero con `tests/integration/`: crea `echo-test.sh` reale in tmpdir, exec, verifica ACK.

### e2e cross-repo (manuale)

1. SSH sul box, `cp /home/admin/lg-control/to-box.sh /opt/onesibox/custom-scripts/`.
2. Filament: crea `CustomCommand` (name "Box TV", script `to-box.sh`).
3. Dashboard: caregiver Full clicca → toast verde → HDMI commuta.
4. Filament: rinomina script a `nope.sh` → caregiver clicca → toast rossa E115.

## Migration / Rollout

1. Migration `2026_05_24_000000_create_onesi_box_custom_commands_table.php` (additiva, zero downtime).
2. Patch enum `CommandType` (additiva, valori esistenti invariati).
3. Patch `install.sh` del box: creazione idempotente di `/opt/onesibox/custom-scripts/` con `mkdir -p`, owner/permission corretti.
4. Deploy server, deploy update del box.
5. Per ciascun box che vuole comandi custom: deploy manuale degli `.sh` in `custom-scripts/` (es. spostando `to-box.sh` / `to-tv.sh` dal box di Travaglini).
6. Registrazione comandi in Filament.

Nessuna feature flag necessaria: gli amministratori abilitano semplicemente i comandi via Filament; caregivers vedono la sezione solo se ci sono comandi attivi sul loro box.

## Open Questions (per implementation plan)

- Listener WebSocket Livewire: lo `OnesiBoxDetail::refreshStatus()` esistente gestisce già qualunque tipo via payload generico, o serve aggiungere un branch per `CustomScript`? (Da accertare leggendo `refreshStatus` durante il piano.)
- Lista esatta di Heroicon "azione" da offrire nel Select del Filament form.
- Necessità di un `audit log` esposto in Filament (chi ha lanciato cosa, quando) — il dato esiste già via Spatie, decidere se renderizzarlo in dashboard admin.
