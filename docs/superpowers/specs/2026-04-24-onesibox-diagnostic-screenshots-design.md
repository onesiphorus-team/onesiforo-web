# OnesiBox — Diagnostica via screenshot periodici

- **Data**: 2026-04-24
- **Stato**: design approvato, in attesa di piano di implementazione
- **Repo coinvolti**: `onesiforo` (server Laravel/Filament) + `onesi-box` (daemon Node.js su Raspberry Pi)

## 1. Sommario

Feature diagnostica: ogni ~60 secondi ciascuna OnesiBox cattura uno screenshot del proprio monitor fisico (Wayland/labwc), lo comprime in WebP e lo invia al server centrale. Le immagini sono consultabili da:

- Pannello amministrativo Filament (Page dedicata per-box, controlli di attivazione e intervallo, viewer con timeline).
- Dashboard utente (caregiver/recipient): carosello delle ultime 10 immagini della box a cui l'utente ha accesso, sola visualizzazione, senza controlli.

Il server applica una politica di retention a rollup: vengono conservate sempre le ultime 10 cattura per box più una cattura per ogni ora entro le ultime 24 ore. Oltre 24 ore le immagini vengono cancellate.

## 2. Obiettivi / Non-obiettivi

**Obiettivi**
- Fornire all'operatore tecnico uno strumento visivo per verificare cosa sta mostrando una box in un dato momento e negli ultimi minuti/ore.
- Permettere al caregiver di vedere a colpo d'occhio la schermata recente del proprio dispositivo.
- Mantenere ingombro di banda e storage trascurabili.
- Gestire on/off e intervallo da interfaccia admin senza dover accedere in SSH alla box.

**Non-obiettivi**
- Non è un sistema di sorveglianza continua ad alta frequenza (no <10s).
- Non conserva storico oltre 24 ore.
- Non fornisce editing, annotazioni, OCR o analisi automatica.
- Non gestisce deploy multi-nodo del server (assunzione: single-node).
- Non è una feature soggetta a validazione GDPR nel contesto d'uso corrente (decisione committente).

## 3. Architettura complessiva

```
                     ┌─────────────────────────────────┐
                     │       RASPBERRY PI (onesi-box)  │
                     │                                 │
  HDMI monitor ◄─────┤  labwc (Wayland)                │
                     │     │                           │
                     │     ├── Chromium (Playwright)   │
                     │     │                           │
                     │     └── node daemon             │
                     │          ├─ HeartbeatScheduler  │
                     │          ├─ PollingScheduler    │
                     │          └─ ScreenshotScheduler ├──┐
                     └──────────────────────────────────┘ │
                                                          │  multipart POST
                                                          │  /api/v1/appliances/screenshot
                                                          ▼
                     ┌────────────────────────────────────────────────┐
                     │       ONESIFORO-WEB (Laravel)                  │
                     │                                                │
                     │  ScreenshotController@store                    │
                     │       └─► ProcessScreenshotAction              │
                     │                ├─► Storage::disk('local')      │
                     │                │    storage/app/private/       │
                     │                │    onesi-boxes/{id}/…/{uuid}.webp │
                     │                └─► appliance_screenshots INSERT│
                     │                                                │
                     │  HeartbeatResource (risposta /heartbeat)       │
                     │       └─► include screenshot_enabled,          │
                     │               screenshot_interval_seconds      │
                     │                                                │
                     │  PruneScreenshotsCommand (schedule:run /5min)  │
                     │       └─► rollup: top10 + 1/ora entro 24h      │
                     │                                                │
                     │  Filament Page (admin)                         │
                     │       /admin/onesi-boxes/{id}/screenshots      │
                     │                                                │
                     │  Livewire component (dashboard caregiver)      │
                     │       carosello ultime 10, var. compact+full   │
                     │                                                │
                     │  Reverb broadcast canale private-appliance.{id}│
                     │       ApplianceScreenshotReceived              │
                     │                                                │
                     │  GET /api/v1/screenshots/{id}  (signed URL)    │
                     │       policy: admin OR caregiver OR recipient  │
                     └────────────────────────────────────────────────┘
```

## 4. Data model

### 4.1 Nuova tabella `appliance_screenshots`

| Colonna         | Tipo                      | Note |
|-----------------|---------------------------|------|
| `id`            | BIGINT PK                 | |
| `onesi_box_id`  | FK → `onesi_boxes.id`     | `ON DELETE CASCADE`, indicizzato |
| `captured_at`   | TIMESTAMP                 | istante di cattura lato box (non server) |
| `width`         | SMALLINT UNSIGNED         | px |
| `height`        | SMALLINT UNSIGNED         | px |
| `bytes`         | INT UNSIGNED              | dimensione file |
| `storage_path`  | VARCHAR(512)              | path relativo al disk `local` |
| `created_at`    | TIMESTAMP                 | |

Indici:
- `INDEX (onesi_box_id, captured_at DESC)` — query "ultime N per box"
- `INDEX (captured_at)` — pruning globale

Nessuna colonna `updated_at`: record immutabile.

### 4.2 Modifiche a `onesi_boxes`

Migration aggiuntiva:

| Colonna                         | Tipo               | Default |
|---------------------------------|--------------------|---------|
| `screenshot_enabled`            | BOOLEAN NOT NULL   | `true`  |
| `screenshot_interval_seconds`   | SMALLINT UNSIGNED NOT NULL | `60` |

Validation range runtime: `screenshot_interval_seconds` ∈ [10, 3600].

### 4.3 Layout su filesystem

Disk: `local` (`storage/app/private`).

```
storage/app/private/onesi-boxes/{onesi_box_id}/screenshots/
    {ISO_captured_at}_{uuid4-short}.webp
```

Esempio: `2026-04-24T14-32-11_a4f2e9d1.webp`.

## 5. Box-side (repo onesi-box)

### 5.1 Dipendenze di sistema (nuove)

Da aggiungere all'installazione via `install.sh` (pacchetti Debian):
- `grim` — screenshot tool Wayland compatibile con wlroots/labwc.
- `webp` — fornisce il binario `cwebp`.

Nessuna nuova dipendenza npm.

### 5.2 Nuovo modulo `src/diagnostics/screenshot-scheduler.js`

```
class ScreenshotScheduler {
    constructor(apiClient, config, logger)
    start()                 // avvia con interval=config.screenshot_interval_seconds
    stop()                  // clearInterval, svuota pending
    applyServerConfig({ enabled, intervalSeconds })
                            // chiamato dopo ogni risposta heartbeat
                            // restart se interval cambia; on/off se enabled cambia
    async captureAndSend()  // executor del tick
}
```

Istanziato in `main.js` accanto a heartbeat e polling scheduler esistenti.
Il componente `HeartbeatScheduler` invoca `screenshotScheduler.applyServerConfig({...})` dopo ogni heartbeat andato a buon fine, passando i due campi nuovi della response.

### 5.3 Capture pipeline

Pipe shell eseguita via `child_process.spawn`:

```
grim -t ppm -          →  stdout  →  cwebp -q 75 -o - -   →  stdout  →  Buffer
```

Env vars passate al processo `grim`:

- `WAYLAND_DISPLAY=wayland-0` (default labwc)
- `XDG_RUNTIME_DIR=/run/user/{uid}` (uid letto via `id -u` dell'utente corrente a startup, cached)

**Timeout**: 8 secondi complessivi sulla pipeline. Oltre → kill di entrambi i child process, log `warn`, skip tick (nessun retry sincrono).

### 5.4 Invio HTTP

```
POST {server_url}/api/v1/appliances/screenshot
Content-Type: multipart/form-data
Authorization: Bearer {appliance_token}

Campi:
  captured_at : ISO-8601 (timestamp appena prima dello spawn)
  width       : 1920
  height      : 1080
  screenshot  : <binario WebP>
```

Body costruito con `form-data` (transitivo di axios). Riutilizza `ApiClient` esistente — circuit breaker 401/403, backoff esponenziale 429, logging winston strutturato.

### 5.5 Error handling

| Evento                           | Reazione |
|----------------------------------|----------|
| `grim` exit ≠ 0                  | log `warn`, skip tick |
| `cwebp` exit ≠ 0                 | log `warn`, skip tick |
| Timeout pipeline                 | kill children, log `warn` |
| `spawn ENOENT` (binario mancante)| log `error` **una sola volta**, disable scheduler finché non arriva un nuovo `applyServerConfig` |
| HTTP 5xx                         | delegato ad `ApiClient` (`consecutiveFailures`); screenshot scartato, **nessuna coda su disco** |
| HTTP 413                         | log `warn`, no retry |
| Buffer > 2 MB (sanity check)     | log `warn`, non inviato |

### 5.6 Config locale (override dev/test)

In `config.json` e via env var override:

- `screenshot_enabled` (bool, default `true`) — stato iniziale, sovrascritto dal server.
- `screenshot_interval_seconds` (int, default `60`) — idem.

Validazione in `validateConfig()`: interval ∈ [10, 3600].

## 6. Server-side (repo onesiforo)

### 6.1 Route

In `routes/api.php`, dentro il gruppo `auth:sanctum` + `appliance.active`:

```
Route::post('appliances/screenshot', [ScreenshotController::class, 'store'])
    ->middleware('throttle:screenshot-upload')
    ->name('api.v1.appliances.screenshot.store');
```

Rate limiter in `AppServiceProvider::boot()`:

```
RateLimiter::for('screenshot-upload', fn ($req) =>
    Limit::perMinute(12)->by($req->user()?->id ?: $req->ip())
);
```

Route download, fuori dal gruppo appliance:

```
Route::get('screenshots/{screenshot}', [ScreenshotController::class, 'show'])
    ->middleware(['auth:sanctum', 'signed'])
    ->name('api.v1.screenshots.show');
```

### 6.2 Request validation — `StoreScreenshotRequest`

```
rules():
  captured_at : ['required', 'date', 'before_or_equal:now', 'after:-5 minutes']
  width       : ['required', 'integer', 'between:320,4096']
  height      : ['required', 'integer', 'between:180,2160']
  screenshot  : ['required', 'file', 'mimes:webp', 'max:2048']   // 2MB cap
```

### 6.3 `ScreenshotController`

```
store(StoreScreenshotRequest $request, ProcessScreenshotAction $action)
    $screenshot = $action->execute(
        $request->user(),                    // OnesiBox autenticata
        $request->date('captured_at'),
        $request->integer('width'),
        $request->integer('height'),
        $request->file('screenshot')
    );
    return response()->json(['id' => $screenshot->id], 201);

show(ApplianceScreenshot $screenshot)
    $this->authorize('view', $screenshot);
    return Storage::disk('local')->download(
        $screenshot->storage_path,
        null,
        ['Content-Type' => 'image/webp',
         'Cache-Control' => 'private, max-age=60']
    );
```

### 6.4 `ProcessScreenshotAction`

Responsabilità: genera path, persiste file, crea record, dispatcha evento broadcast.

```
execute(OnesiBox $box, Carbon $capturedAt, int $w, int $h, UploadedFile $file): ApplianceScreenshot
{
    $uuid     = Str::uuid()->toString();
    $filename = $capturedAt->format('Y-m-d\TH-i-s') . '_' . substr($uuid, 0, 8) . '.webp';
    $path     = "onesi-boxes/{$box->id}/screenshots/{$filename}";

    Storage::disk('local')->putFileAs(
        dirname($path),
        $file,
        basename($path),
        ['visibility' => 'private']
    );

    $screenshot = ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at'  => $capturedAt,
        'width'        => $w,
        'height'       => $h,
        'bytes'        => $file->getSize(),
        'storage_path' => $path,
    ]);

    event(new ApplianceScreenshotReceived($screenshot));

    return $screenshot;
}
```

Rischio orfani file↔record se il `create` fallisce dopo il `putFileAs`: mitigato dal sweep orfani periodico (§7.2).

### 6.5 Modello `ApplianceScreenshot`

- `belongsTo(OnesiBox::class, 'onesi_box_id')` (alias `onesiBox`).
- Fillable: colonne visibili in §4.1 eccetto `id`/`created_at`.
- Cast: `captured_at => datetime`.
- Model event:
  ```
  static::deleting(fn (self $s) =>
      Storage::disk('local')->delete($s->storage_path)
  );
  ```
- Accessor `signedUrl()` che genera `URL::signedRoute('api.v1.screenshots.show', ['screenshot' => $this->id], now()->addMinutes(5))`.

**Nota operativa**: il cleanup tramite `PruneScreenshotsCommand` deve usare `->get()->each->delete()` e **mai** `whereX()->delete()`, altrimenti il model event non scatta e i file restano orfani.

### 6.6 Evento di broadcast

`App\Events\ApplianceScreenshotReceived` implementa `ShouldBroadcast`:

- Canale: `new PrivateChannel("appliance.{$this->screenshot->onesi_box_id}")`
- Payload: `['id' => $this->screenshot->id, 'captured_at' => $this->screenshot->captured_at]`

In `routes/channels.php`:

```
Broadcast::channel('appliance.{onesiBoxId}', function (User $user, int $onesiBoxId) {
    $box = OnesiBox::find($onesiBoxId);
    if (!$box) return false;
    return $user->isAdmin()
        || OnesiBox::userCanView($user, $box)
        || $box->caregivers()->where('users.id', $user->id)->exists();
});
```

La logica precisa va allineata alla firma di `OnesiBox::userCanView` (da verificare in fase di plan) e al trait `ChecksOnesiBoxPermission` esistente.

### 6.7 Relazioni su `OnesiBox`

Aggiungere:

```
screenshots(): HasMany(ApplianceScreenshot::class, 'onesi_box_id')
latestScreenshot(): HasOne(ApplianceScreenshot::class, 'onesi_box_id')
    ->latestOfMany('captured_at')
```

E ai `fillable`/`casts` i due nuovi campi.

### 6.8 Policy `ApplianceScreenshotPolicy`

```
view(User $user, ApplianceScreenshot $screenshot): bool
{
    return $user->isAdmin()
        || OnesiBox::userCanView($user, $screenshot->onesiBox);
}
```

Registrata in `AuthServiceProvider`.

### 6.9 `HeartbeatResource` — estensione

Aggiungere al `toArray()`:

```
'screenshot_enabled'          => (bool) $this->resource->screenshot_enabled,
'screenshot_interval_seconds' => (int)  $this->resource->screenshot_interval_seconds,
```

Propagazione al client: ≤ 30s (intervallo heartbeat default).

## 7. Retention / Pruning

### 7.1 `PruneScreenshotsCommand`

`app/Console/Commands/PruneScreenshotsCommand.php`

- **Signature**: `onesibox:prune-screenshots {--sweep-orphans}`
- **Default run**: applica il rollup a tutte le box.
- **Con flag `--sweep-orphans`**: esegue solo la scansione file orfani.

Logica rollup per singola box (ciclo su `OnesiBox::chunk(100, ...)`):

1. **Taglio duro 24h** — fetch dei record `captured_at < now()->subHours(24)`, `->each->delete()`.
2. Fetch (`id`, `captured_at`) di tutti i residui per la box, ordine `captured_at DESC`.
3. Calcolo `keep`:
   - Primi 10 id → sempre conservati.
   - Rimanenti raggruppati per bucket orario `captured_at->format('Y-m-d H:00')`; per ogni bucket il primo (più recente) → conservato.
4. `ApplianceScreenshot::where('onesi_box_id', $box->id)->whereNotIn('id', $keep)->get()->each->delete()`.

Log a livello `info` a fine run:
```
prune-screenshots completed: boxes=N, deleted_total=M (older_24h=X, rollup=Y), duration_ms=D
```

### 7.2 Sweep orfani (difesa in profondità)

Scan di `storage/app/private/onesi-boxes/*/screenshots/` via `Storage::disk('local')->allFiles()` a chunk di directory. Per ogni file controllo `ApplianceScreenshot::where('storage_path', $path)->exists()` (bulk a chunk di 500 via `whereIn`). File senza match → `Storage::delete()` + log `warning` se count > 0 (il caso normale atteso è 0).

### 7.3 Schedulazione

In `routes/console.php`:

```
Schedule::command('onesibox:prune-screenshots')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('onesibox:prune-screenshots --sweep-orphans')
    ->dailyAt('03:15')
    ->withoutOverlapping();
```

Prerequisito ambiente: cron di sistema invoca `php artisan schedule:run` ogni minuto (standard Laravel).

### 7.4 Assunzioni operative

- **Single node**: nessun `->onOneServer()` necessario. Se in futuro si passa a multi-nodo va aggiunto (richiede driver cache condivisa, es. Redis).

## 8. UI admin (Filament)

### 8.1 Custom Page `ManageScreenshots`

`app/Filament/Resources/OnesiBoxes/Pages/ManageScreenshots.php`:

```
class ManageScreenshots extends \Filament\Pages\Page
{
    protected static string $resource = OnesiBoxResource::class;
    protected static string $view     = 'filament.onesi-boxes.screenshots';

    public OnesiBox $record;

    public static function getRoutePath(): string { return '{record}/screenshots'; }
    public function getTitle(): string   { return "Diagnostica — {$this->record->name}"; }
    public function getHeading(): string { return 'Screenshot diagnostici'; }
}
```

Registrata in `OnesiBoxResource::getPages()`:

```
'screenshots' => Pages\ManageScreenshots::route('/{record}/screenshots'),
```

### 8.2 Entry point

- Sulla `EditOnesiBox`: action header "Diagnostica schermo" (icon heroicon `camera`) → link alla Page.
- Sulla `ListOnesiBoxes`: analoga row-action per ciascuna riga.

### 8.3 Layout della view

Tre zone:
1. **Header**: stato feature (enabled/disabled), intervallo corrente, timestamp ultimo scatto. Toggle + input intervallo per modifica inline.
2. **Preview grande**: screenshot selezionato (default = più recente). Mostra `captured_at`, `width×height`, `bytes`. Pulsante "Scarica originale".
3. **Timeline / griglia**: due sezioni raggruppate.
   - "Ultimi 10 minuti (realtime)" — i 10 top ordinati desc.
   - "24 ore (una per ora)" — i bucket orari residui dopo rollup.

### 8.4 Componente Livewire embedded `ScreenshotsViewer`

```
class ScreenshotsViewer extends Component
{
    public OnesiBox $record;
    public ?int $selectedId = null;

    #[Computed] public function screenshots(): Collection { ... }

    public function select(int $id): void { $this->selectedId = $id; }

    public function toggleEnabled(): void { /* update field, persist */ }
    public function updateInterval(int $seconds): void { /* validate 10-3600, persist */ }

    #[On('echo-private:appliance.{record.id},ApplianceScreenshotReceived')]
    public function onNewScreenshot(): void { unset($this->screenshots); }
}
```

Hint UX visibile a fianco dei controlli: *"La box applicherà il cambio al prossimo heartbeat (entro 30s)."*

### 8.5 Thumbnail e preview

Signed URL verso `api.v1.screenshots.show`, expiry 5 min. Nessuna pre-generazione varianti: gli `<img>` usano attributi `width`/`height` + `loading="lazy"`.

Modal full-screen per zoom 1:1 (componente Filament/Alpine).

### 8.6 Empty state

- `screenshot_enabled = false` → "Diagnostica disabilitata. Abilitala per iniziare la cattura."
- `screenshot_enabled = true` + nessun record → "In attesa del primo screenshot… (entro ~{interval}s dall'abilitazione)"
- `latestScreenshot->captured_at < now()-5min` con feature on → warning "Ultimo scatto più di 5 minuti fa — box offline o errore cattura."

### 8.7 Controlli anche nel form principale

I campi `screenshot_enabled` e `screenshot_interval_seconds` vanno esposti in una **nuova sezione "Diagnostica"** nella `OnesiBoxForm`, in modo che siano gestibili sia dalla Page sia dal form di Edit standard. Source of truth: il database; i due entry point agiscono sullo stesso campo.

## 9. UI caregiver (Livewire dashboard utente)

### 9.1 Componente `ScreenshotCarousel`

`app/Livewire/OnesiBox/ScreenshotCarousel.php`:

```
class ScreenshotCarousel extends Component
{
    public OnesiBox $box;
    public string $variant = 'full';       // 'full' | 'compact'
    public int $limit = 10;

    #[Computed]
    public function screenshots(): Collection
    {
        return $this->box->screenshots()
            ->orderByDesc('captured_at')
            ->limit($this->limit)
            ->get();
    }

    #[On('echo-private:appliance.{box.id},ApplianceScreenshotReceived')]
    public function refresh(): void { unset($this->screenshots); }
}
```

Nessun controllo visibile al caregiver (né toggle né intervallo).

### 9.2 Empty state

- `screenshots->isNotEmpty()` → rendi il carosello.
- `screenshots->isEmpty()` e `!$box->screenshot_enabled` → label discreto "Diagnostica non attiva".
- `screenshots->isEmpty()` e `$box->screenshot_enabled` → nessun rendering (spazio vuoto).

### 9.3 Inserimento

Due punti, variante diversa:

1. **`/dashboard`** (`App\Livewire\Dashboard\OnesiBoxList`, view `livewire/dashboard/onesi-box-list.blade.php`)
   Per ciascuna card box, variante `compact` (thumbnail ~80×45 px, scroll orizzontale):

   ```blade
   <livewire:onesi-box.screenshot-carousel
       :box="$box"
       variant="compact"
       :key="'carousel-compact-'.$box->id" />
   ```

2. **`/dashboard/{onesiBox}`** (`App\Livewire\Dashboard\OnesiBoxDetail`, view `livewire/dashboard/onesi-box-detail.blade.php`)
   Dopo l'hero card, sezione "Diagnostica schermo" solo se ci sono screenshot; variante `full` (thumbnail ~160×90 px, lightbox su click).

### 9.4 Multi-box

Un caregiver può avere più box assegnate (many-to-many via `caregivers` pivot) o una come `recipient`. Il template della list view itera su `$this->onesiBoxes` (computed già esistente nel componente padre). Ciascun carosello si sottoscrive al proprio canale `appliance.{id}` — nessuna interferenza cross-box.

### 9.5 Performance

Query per carosello: `SELECT ... LIMIT 10`, indicizzata da `(onesi_box_id, captured_at DESC)`. Con 3 box per caregiver: 3 query brevi + 3 sottoscrizioni Reverb. Lazy loading immagini via `loading="lazy"`.

## 10. Sicurezza e autorizzazione

- **Upload box→server**: `auth:sanctum` (token Bearer appliance) + middleware `appliance.active`. Rate limit 12/min.
- **Download immagine**: route `signed` + `auth:sanctum` + policy `ApplianceScreenshotPolicy@view`. Expiry link 5 minuti.
- **Canale broadcast**: `Broadcast::channel('appliance.{id}')` con la stessa logica della policy.
- **Admin Filament**: protezione ereditata dal pannello `/admin`.
- **Caregiver dashboard**: middleware `auth` + `verified`, `OnesiBox::userCanView($user, $box)` riusa la logica permessi esistente.
- **Contenuto schermo**: considerato dato personale; committente ha esplicitamente confermato (2026-04-24) che non sussistono adempimenti GDPR aggiuntivi nel contesto d'uso. Da ridiscutere se il prodotto viene esteso a target diversi.

## 11. Testing strategy

### 11.1 Server (Laravel)

Feature tests (framework di test del repo da allineare, presumibilmente Pest/PHPUnit):

- `ScreenshotUploadTest`
  - Rifiuto non autenticato (401).
  - Rifiuto appliance non attiva (403).
  - Upload valido: file persistito, record creato, evento dispatchato (`Event::fake`, `Storage::fake('local')`).
  - MIME non-WebP → 422.
  - File > 2MB → 422.
  - `captured_at` > 5 min fa → 422.
  - Rate limit 13° richiesta in 1 min → 429.
- `ScreenshotDownloadTest`
  - Signed URL con admin → 200, `image/webp`.
  - Signed URL con caregiver senza permesso → 403.
  - URL non firmato → 403.
  - URL firmato scaduto (travel 6 min) → 403.
- `HeartbeatResourceTest`
  - La response include `screenshot_enabled` e `screenshot_interval_seconds`.
- `BroadcastAuthTest`
  - `POST /broadcasting/auth` per canale `private-appliance.{id}`: admin/caregiver autorizzato → 200; utente sconosciuto → 403.

Unit tests:

- `ProcessScreenshotActionTest` — path corretto, tutti i campi nel record.
- `PruneScreenshotsCommandTest`
  - Cancellazione record >24h.
  - Top 10 sempre conservati.
  - Rollup: 3 record stessa ora → resta 1 (il più recente).
  - File cancellato fisicamente al `delete()` del model.
  - Sweep orfani: file senza record rimossi, file con record mantenuti.
- `ApplianceScreenshotPolicyTest` — matrice admin/caregiver-auth/caregiver-non-auth/recipient/estraneo × box.

### 11.2 Box (Node.js)

Con `child_process.spawn` mockato tramite EventEmitter controllabili:

- `screenshot-scheduler.test.js`
  - Lifecycle start/stop.
  - `applyServerConfig` riavvia interval se cambia il valore.
  - `applyServerConfig` spegne/riaccende se cambia enabled.
  - Flag `isCapturing` previene sovrapposizione tick.
  - `spawn ENOENT grim` → disable + log error una volta.
  - Timeout 8s → kill + log warn.
  - HTTP 5xx → nessuna coda, screenshot scartato.

### 11.3 Esplicitamente non testato

- Pipeline end-to-end `grim→cwebp→POST` su hardware reale: verifica manuale su box di staging dopo merge.
- Render visivo delle view (Filament Page, caroselli): verifica visiva su staging.

## 12. Deployment / operational

- Modifica `install.sh` (repo onesi-box): aggiunta `grim` e `webp` alla lista `apt-get install`.
- Box già installate: script aggiuntivo/manuale `apt install grim webp` (oppure riesecuzione idempotente dell'installer).
- Lato server: due migrations, registrazione comando, registrazione schedule, registrazione policy, registrazione rate limiter, registrazione event listener broadcast.
- Assunzione cron `schedule:run` già operativa in produzione (da verificare al deploy).
- Assunzione Reverb già operativo in produzione (confermato da committente).

## 13. Out of scope / future work

- Pre-generazione thumbnail server-side (conversion automatica) — rimandato finché banda dashboard non diventa un problema.
- Cattura on-demand a risoluzione/frequenza elevate ("burst mode") — fuori scope v1.
- Retention configurabile via admin UI — per ora hard-coded (24h / 10 / 1 per ora).
- Supporto multi-nodo del cleanup (`onOneServer`) — rimandato finché single-node resta valido.
- Purge manuale "dimentica tutti gli screenshot di una box" da admin — aggiungibile in futuro se emerge necessità.

## 14. Riepilogo decisioni chiave

- **Cattura**: `grim` Wayland (non Playwright) per catturare lo schermo fisico reale, inclusi crash/overlay di sistema.
- **Config feature**: server-driven via response heartbeat, granularità propagazione ≤ 30s.
- **Retention**: rollup top-10 + 1/ora entro 24h, via Artisan command schedulato.
- **UI admin**: custom Filament Page per box + anche campi nel form di edit.
- **UI caregiver**: solo visualizzazione (no controlli), carosello nelle due pagine dashboard esistenti.
- **Storage**: disk locale privato, signed URL per download.
- **Realtime**: Reverb broadcast su canale privato per-box (Reverb già in stack).
- **Qualità**: Full HD 1920×1080 WebP q=75, ~150KB/frame.
- **Single-node**: assunzione operativa v1.
