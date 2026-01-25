# Quickstart: OnesiBox Caregiver Controls

## Overview

Questa feature estende la dashboard caregiver con controlli avanzati per le appliance OnesiBox. L'implementazione coinvolge due repository:

1. **Onesiforo** (questo repo) - Backend Laravel + UI Livewire
2. **OnesiBox** (`onesi-box/`) - Client Node.js su Raspberry Pi

## Prerequisites

### Onesiforo
- PHP 8.4+
- Composer
- Node.js (per frontend build)
- SQLite (dev) / MySQL/PostgreSQL (prod)

### OnesiBox
- Node.js 20+
- Raspberry Pi OS con PipeWire/PulseAudio

## Development Setup

### 1. Onesiforo - Backend

```bash
# Checkout feature branch
cd onesiforo
git checkout 009-onesibox-caregiver-controls

# Install dependencies
composer install
npm install

# Run migrations
php artisan migrate

# Start development server
composer run dev
# oppure
php artisan serve & npm run dev
```

### 2. OnesiBox - Client

```bash
# Checkout feature branch (se in monorepo) o pull latest
cd onesi-box

# Install dependencies
npm install

# Start in development mode
LOG_LEVEL=debug npm start
```

## Key Files to Modify

### Onesiforo

| File | Action | Description |
|------|--------|-------------|
| `app/Enums/CommandType.php` | Modify | Aggiungere `GetSystemInfo`, `GetLogs` |
| `app/Enums/CommandStatus.php` | Modify | Aggiungere `Cancelled` |
| `app/Models/OnesiBox.php` | Modify | Aggiungere nuovi campi fillable e casts |
| `app/Livewire/Dashboard/Controls/VolumeControl.php` | Create | Componente controllo volume |
| `app/Livewire/Dashboard/Controls/CommandQueue.php` | Create | Componente coda comandi |
| `app/Livewire/Dashboard/Controls/SystemInfo.php` | Create | Componente info sistema |
| `app/Livewire/Dashboard/Controls/LogViewer.php` | Create | Componente visualizzazione log |
| `app/Http/Controllers/Api/V1/HeartbeatController.php` | Modify | Gestire dati estesi |
| `app/Http/Controllers/Api/V1/CommandController.php` | Modify | Aggiungere DELETE per cancel |

### OnesiBox

| File | Action | Description |
|------|--------|-------------|
| `src/commands/handlers/system-info.js` | Create | Handler get_system_info |
| `src/commands/handlers/logs.js` | Create | Handler get_logs |
| `src/logging/log-sanitizer.js` | Create | Filtro dati sensibili |
| `src/logging/logger.js` | Modify | Log più dettagliati |
| `src/main.js` | Modify | Registrare nuovi handler |

## Testing

### Onesiforo

```bash
# Run all tests
php artisan test

# Run specific feature tests
php artisan test --filter=VolumeControl
php artisan test --filter=CommandQueue
php artisan test --filter=SystemInfo
php artisan test --filter=LogViewer

# Run with coverage
php artisan test --coverage
```

### OnesiBox

```bash
# Run tests
npm test

# Run specific test
npm test -- --grep="system-info"
```

## API Usage Examples

### Set Volume (from Caregiver Dashboard)

Il caregiver clicca un bottone volume. Il Livewire component crea un comando:

```php
// In VolumeControl.php
public function setVolume(int $level): void
{
    Command::create([
        'onesi_box_id' => $this->onesiBox->id,
        'type' => CommandType::SetVolume,
        'payload' => ['level' => $level],
        'priority' => 3,
    ]);

    $this->dispatch('notify', message: 'Comando volume inviato');
}
```

### Cancel Command (from Caregiver Dashboard)

```php
// In CommandQueue.php
public function cancelCommand(string $uuid): void
{
    $command = Command::where('uuid', $uuid)
        ->where('onesi_box_id', $this->onesiBox->id)
        ->firstOrFail();

    if ($command->status !== CommandStatus::Pending) {
        $this->dispatch('notify', type: 'error', message: 'Comando non cancellabile');
        return;
    }

    $command->update(['status' => CommandStatus::Cancelled]);
    $this->dispatch('notify', message: 'Comando annullato');
}
```

### Get System Info (from Caregiver Dashboard)

```php
// In SystemInfo.php
public function refreshSystemInfo(): void
{
    Command::create([
        'onesi_box_id' => $this->onesiBox->id,
        'type' => CommandType::GetSystemInfo,
        'priority' => 3,
    ]);

    $this->dispatch('notify', message: 'Richiesta info sistema inviata');
}
```

### OnesiBox - Handle get_system_info

```javascript
// src/commands/handlers/system-info.js
const si = require('systeminformation');
const os = require('os');

async function getSystemInfo(command, browserController) {
  const [time, load, mem, disk, network, wifi] = await Promise.all([
    si.time(),
    si.currentLoad(),
    si.mem(),
    si.fsSize(),
    si.networkInterfaces(),
    si.wifiConnections()
  ]);

  const uptime = os.uptime();
  const days = Math.floor(uptime / 86400);
  const hours = Math.floor((uptime % 86400) / 3600);

  return {
    uptime_seconds: uptime,
    uptime_formatted: `${days} giorni, ${hours} ore`,
    load_average: {
      '1m': load.avgLoad.toFixed(2),
      '5m': load.avgLoad5.toFixed(2),
      '15m': load.avgLoad15.toFixed(2)
    },
    memory: {
      used_bytes: mem.used,
      total_bytes: mem.total,
      percent: Math.round((mem.used / mem.total) * 100)
    },
    cpu_percent: Math.round(load.currentLoad),
    disk: {
      used_bytes: disk[0]?.used || 0,
      total_bytes: disk[0]?.size || 0,
      percent: Math.round(disk[0]?.use || 0)
    },
    network: {
      ip: network.find(n => !n.internal && n.ip4)?.ip4 || '--',
      wifi_ssid: wifi[0]?.ssid || null
    },
    timestamp: new Date().toISOString()
  };
}

module.exports = { getSystemInfo };
```

### OnesiBox - Handle get_logs with Sanitization

```javascript
// src/commands/handlers/logs.js
const fs = require('fs').promises;
const path = require('path');
const { sanitizeLogs } = require('../logging/log-sanitizer');

const LOG_DIR = process.env.LOG_DIR || path.join(__dirname, '../../logs');
const MAX_LINES = 500;

async function getLogs(command, browserController) {
  const requestedLines = Math.min(command.payload?.lines || 50, MAX_LINES);

  // Find today's log file
  const today = new Date().toISOString().split('T')[0];
  const logFile = `onesibox-${today}.log`;
  const logPath = path.join(LOG_DIR, logFile);

  try {
    const content = await fs.readFile(logPath, 'utf8');
    const allLines = content.trim().split('\n');
    const lines = allLines.slice(-requestedLines);

    const parsed = lines.map(line => {
      try {
        return JSON.parse(line);
      } catch {
        return { timestamp: null, level: 'info', message: line };
      }
    });

    const sanitized = sanitizeLogs(parsed);

    return {
      lines: sanitized,
      total_lines: allLines.length,
      returned_lines: sanitized.length,
      log_file: logFile
    };
  } catch (error) {
    throw new Error(`Failed to read logs: ${error.message}`);
  }
}

module.exports = { getLogs };
```

```javascript
// src/logging/log-sanitizer.js
const SENSITIVE_PATTERNS = [
  { pattern: /pwd=[A-Za-z0-9]+/g, replacement: 'pwd=***' },
  { pattern: /Bearer [A-Za-z0-9|]+/g, replacement: 'Bearer ***' },
  { pattern: /\d+\|[A-Za-z0-9]{40,}/g, replacement: '***|***' },
  { pattern: /password=[^&\s]+/g, replacement: 'password=***' },
  { pattern: /token=[^&\s]+/g, replacement: 'token=***' },
];

function sanitizeString(str) {
  let result = str;
  for (const { pattern, replacement } of SENSITIVE_PATTERNS) {
    result = result.replace(pattern, replacement);
  }
  return result;
}

function sanitizeLogs(logs) {
  return logs.map(entry => ({
    ...entry,
    message: sanitizeString(entry.message || ''),
    context: entry.context
      ? JSON.parse(sanitizeString(JSON.stringify(entry.context)))
      : null
  }));
}

module.exports = { sanitizeLogs, sanitizeString };
```

## UI Components Structure

### Volume Control
```blade
{{-- resources/views/livewire/dashboard/controls/volume-control.blade.php --}}
<div class="space-y-2">
    <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Volume</h3>
    <div class="grid grid-cols-5 gap-2">
        @foreach([20, 40, 60, 80, 100] as $level)
            <flux:button
                wire:click="setVolume({{ $level }})"
                :variant="$currentVolume === $level ? 'primary' : 'outline'"
                size="sm"
                :disabled="!$canControl || !$isOnline"
            >
                {{ $level }}%
            </flux:button>
        @endforeach
    </div>
</div>
```

### Command Queue
```blade
{{-- resources/views/livewire/dashboard/controls/command-queue.blade.php --}}
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium">Coda Comandi</h3>
        @if($commands->isNotEmpty() && $canControl)
            <flux:button wire:click="cancelAll" variant="danger" size="xs">
                Elimina tutti
            </flux:button>
        @endif
    </div>

    @forelse($commands as $command)
        <div class="flex items-center justify-between p-2 bg-zinc-50 dark:bg-zinc-800 rounded">
            <div class="flex items-center gap-2">
                <flux:badge :color="$command->type->getColor()">
                    {{ $command->type->getLabel() }}
                </flux:badge>
                <span class="text-xs text-zinc-500">
                    {{ $command->created_at->diffForHumans() }}
                </span>
            </div>
            @if($canControl)
                <flux:button
                    wire:click="cancelCommand('{{ $command->uuid }}')"
                    variant="ghost"
                    size="xs"
                    icon="x-mark"
                />
            @endif
        </div>
    @empty
        <flux:callout variant="info">
            Nessun comando in coda
        </flux:callout>
    @endforelse
</div>
```

## Troubleshooting

### Volume non cambia
1. Verificare che `amixer` sia disponibile: `which amixer`
2. Controllare output: `amixer get Master`
3. Se PipeWire: verificare `pipewire-alsa` installato

### Log non disponibili
1. Verificare directory log: `ls -la ~/onesi-box/logs/`
2. Controllare permessi file
3. Verificare formato data file: `onesibox-YYYY-MM-DD.log`

### Comandi non cancellabili
1. Il comando potrebbe essere già stato prelevato dall'appliance
2. Verificare status nel database: `SELECT * FROM commands WHERE uuid = '...'`

## Next Steps

Dopo aver completato lo sviluppo:

1. Run tests: `php artisan test && npm test`
2. Run Pint: `vendor/bin/pint --dirty`
3. Commit changes
4. Create PR per review
