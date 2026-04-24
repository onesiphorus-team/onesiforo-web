# OnesiBox Diagnostic Screenshots — Server Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementare il lato server (Laravel + Filament + Livewire) della diagnostica screenshot OnesiBox: endpoint di upload, storage privato, retention a rollup, Filament Page admin, carosello caregiver in dashboard.

**Architecture:** Endpoint multipart autenticato via Sanctum che riceve WebP dalla box, li persiste su disk `local` e traccia in tabella `appliance_screenshots`. Retention tramite Artisan command schedulato (rollup top-10 + 1/ora entro 24h). Admin gestisce toggle/intervallo via Filament Page dedicata, propagati alla box nella response heartbeat. Caregiver vede un carosello Livewire in `/dashboard` e `/dashboard/{box}`. Reverb broadcast per realtime.

**Tech Stack:** Laravel 11+, Pest 4, Filament v5, Livewire v4, Sanctum, Reverb, WebP storage su disk `local`.

**Spec di riferimento:** `docs/superpowers/specs/2026-04-24-onesibox-diagnostic-screenshots-design.md`

**Piano complementare:** le modifiche lato Node.js daemon sono in `onesi-box/docs/superpowers/plans/2026-04-24-onesibox-diagnostic-screenshots.md` (da eseguire **dopo** aver completato Fase A di questo piano, così l'endpoint esiste).

---

## Fase A — Fondamenta API e storage

### Task 1: Migration `appliance_screenshots`

**Files:**
- Create: `database/migrations/2026_04_24_100000_create_appliance_screenshots_table.php`

- [ ] **Step 1: Creare la migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appliance_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onesi_box_id')
                ->constrained('onesi_boxes')
                ->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->string('storage_path', 512);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['onesi_box_id', 'captured_at'], 'ascr_box_captured_idx');
            $table->index('captured_at', 'ascr_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appliance_screenshots');
    }
};
```

- [ ] **Step 2: Applicare la migration in dev**

Run: `php artisan migrate`
Expected: `Migrating: 2026_04_24_100000_create_appliance_screenshots_table` seguito da `Migrated`.

- [ ] **Step 3: Verificare la struttura**

Run: `php artisan tinker --execute="dump(Schema::getColumnListing('appliance_screenshots'));"`
Expected: array con `id, onesi_box_id, captured_at, width, height, bytes, storage_path, created_at`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_24_100000_create_appliance_screenshots_table.php
git commit -m "feat(db): create appliance_screenshots table"
```

---

### Task 2: Migration + fillable per campi screenshot su `onesi_boxes`

**Files:**
- Create: `database/migrations/2026_04_24_100100_add_screenshot_fields_to_onesi_boxes_table.php`
- Modify: `app/Models/OnesiBox.php`

- [ ] **Step 1: Creare la migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table) {
            $table->boolean('screenshot_enabled')->default(true)->after('is_active');
            $table->unsignedSmallInteger('screenshot_interval_seconds')->default(60)->after('screenshot_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table) {
            $table->dropColumn(['screenshot_enabled', 'screenshot_interval_seconds']);
        });
    }
};
```

- [ ] **Step 2: Aggiornare `$fillable` e `$casts` di `OnesiBox`**

Aggiungere ai campi fillable le due nuove colonne e al casts `screenshot_enabled` come `boolean` e `screenshot_interval_seconds` come `integer`. Localizzare le property `$fillable` e `$casts` esistenti in `app/Models/OnesiBox.php` e aggiungere le chiavi:

```php
// dentro $fillable aggiungi:
'screenshot_enabled',
'screenshot_interval_seconds',

// dentro $casts aggiungi:
'screenshot_enabled' => 'boolean',
'screenshot_interval_seconds' => 'integer',
```

- [ ] **Step 3: Applicare la migration**

Run: `php artisan migrate`
Expected: `Migrating: 2026_04_24_100100_add_screenshot_fields_to_onesi_boxes_table` → `Migrated`.

- [ ] **Step 4: Verificare via tinker**

Run: `php artisan tinker --execute="dump(App\Models\OnesiBox::first()?->only(['screenshot_enabled', 'screenshot_interval_seconds']));"`
Expected: `['screenshot_enabled' => true, 'screenshot_interval_seconds' => 60]` (o `null` se il DB è vuoto — fine per la verifica).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_24_100100_add_screenshot_fields_to_onesi_boxes_table.php app/Models/OnesiBox.php
git commit -m "feat(db): add screenshot_enabled and interval fields to onesi_boxes"
```

---

### Task 3: Modello `ApplianceScreenshot`

**Files:**
- Create: `app/Models/ApplianceScreenshot.php`
- Create: `tests/Feature/Models/ApplianceScreenshotTest.php`

- [ ] **Step 1: Scrivere il test (model event cancella il file)**

```php
<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('deleting a screenshot removes the file from disk', function (): void {
    Storage::fake('local');
    $box = OnesiBox::factory()->create();

    $file = UploadedFile::fake()->create('s.webp', 50, 'image/webp');
    $path = "onesi-boxes/{$box->id}/screenshots/test.webp";
    Storage::disk('local')->put($path, $file->getContent());

    $screenshot = ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920,
        'height' => 1080,
        'bytes' => 1234,
        'storage_path' => $path,
    ]);

    expect(Storage::disk('local')->exists($path))->toBeTrue();

    $screenshot->delete();

    expect(Storage::disk('local')->exists($path))->toBeFalse();
});

test('screenshot belongs to onesiBox', function (): void {
    $box = OnesiBox::factory()->create();
    $screenshot = ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920,
        'height' => 1080,
        'bytes' => 100,
        'storage_path' => 'fake/path.webp',
    ]);

    expect($screenshot->onesiBox->is($box))->toBeTrue();
});
```

- [ ] **Step 2: Eseguire il test (deve fallire)**

Run: `php artisan test --filter=ApplianceScreenshotTest`
Expected: FAIL con errore `Class "App\Models\ApplianceScreenshot" not found`.

- [ ] **Step 3: Creare il modello**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ApplianceScreenshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'onesi_box_id',
        'captured_at',
        'width',
        'height',
        'bytes',
        'storage_path',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'width' => 'integer',
        'height' => 'integer',
        'bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $screenshot): void {
            Storage::disk('local')->delete($screenshot->storage_path);
        });
    }

    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class, 'onesi_box_id');
    }

    public function signedUrl(int $minutes = 5): string
    {
        return URL::signedRoute(
            'api.v1.screenshots.show',
            ['screenshot' => $this->id],
            now()->addMinutes($minutes)
        );
    }
}
```

- [ ] **Step 4: Eseguire il test (deve passare — la route signed non è ancora definita ma non è invocata dai test di questo task)**

Run: `php artisan test --filter=ApplianceScreenshotTest`
Expected: 2 test PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ApplianceScreenshot.php tests/Feature/Models/ApplianceScreenshotTest.php
git commit -m "feat(model): add ApplianceScreenshot with file cleanup on delete"
```

---

### Task 4: Relazioni `screenshots` e `latestScreenshot` su `OnesiBox`

**Files:**
- Modify: `app/Models/OnesiBox.php`
- Create: `tests/Feature/Models/OnesiBoxScreenshotRelationTest.php`

- [ ] **Step 1: Scrivere il test**

```php
<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;

test('onesiBox has screenshots relation', function (): void {
    $box = OnesiBox::factory()->create();
    ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now()->subMinutes(2),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => 'p1.webp',
    ]);
    ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => 'p2.webp',
    ]);

    expect($box->screenshots)->toHaveCount(2);
});

test('latestScreenshot returns the most recent by captured_at', function (): void {
    $box = OnesiBox::factory()->create();
    $older = ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now()->subMinutes(10),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => 'old.webp',
    ]);
    $newer = ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => 'new.webp',
    ]);

    expect($box->fresh()->latestScreenshot->is($newer))->toBeTrue();
});
```

- [ ] **Step 2: Eseguire (fallisce)**

Run: `php artisan test --filter=OnesiBoxScreenshotRelationTest`
Expected: FAIL — relazione non esiste.

- [ ] **Step 3: Aggiungere le relazioni a `OnesiBox`**

Aggiungere ai metodi del modello `app/Models/OnesiBox.php`, dopo le relazioni esistenti:

```php
public function screenshots(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(ApplianceScreenshot::class, 'onesi_box_id');
}

public function latestScreenshot(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(ApplianceScreenshot::class, 'onesi_box_id')
        ->latestOfMany('captured_at');
}
```

E aggiungere `use App\Models\ApplianceScreenshot;` se non è già importato (non è necessario se usi FQCN inline, ma preferibile per coerenza col resto del modello).

- [ ] **Step 4: Eseguire (deve passare)**

Run: `php artisan test --filter=OnesiBoxScreenshotRelationTest`
Expected: 2 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/OnesiBox.php tests/Feature/Models/OnesiBoxScreenshotRelationTest.php
git commit -m "feat(model): add screenshots and latestScreenshot relations to OnesiBox"
```

---

### Task 5: `StoreScreenshotRequest` validation

**Files:**
- Create: `app/Http/Requests/Api/V1/StoreScreenshotRequest.php`
- Create: `tests/Feature/Api/V1/StoreScreenshotRequestTest.php`

- [ ] **Step 1: Scrivere test di validation (feature test che istanzia il request)**

```php
<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\StoreScreenshotRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

function validateScreenshotPayload(array $data): \Illuminate\Contracts\Validation\Validator {
    $request = new StoreScreenshotRequest();
    return Validator::make($data, $request->rules());
}

test('valid payload passes', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->subSeconds(10)->toIso8601String(),
        'width' => 1920,
        'height' => 1080,
        'screenshot' => UploadedFile::fake()->create('s.webp', 100, 'image/webp'),
    ]);
    expect($v->passes())->toBeTrue();
});

test('stale captured_at is rejected', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->subMinutes(10)->toIso8601String(),
        'width' => 1920, 'height' => 1080,
        'screenshot' => UploadedFile::fake()->create('s.webp', 100, 'image/webp'),
    ]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('captured_at'))->toBeTrue();
});

test('non-webp mime is rejected', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->toIso8601String(),
        'width' => 1920, 'height' => 1080,
        'screenshot' => UploadedFile::fake()->image('s.png'),
    ]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('screenshot'))->toBeTrue();
});

test('oversized file is rejected', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->toIso8601String(),
        'width' => 1920, 'height' => 1080,
        'screenshot' => UploadedFile::fake()->create('s.webp', 2100, 'image/webp'),
    ]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('screenshot'))->toBeTrue();
});

test('width out of range is rejected', function (): void {
    $v = validateScreenshotPayload([
        'captured_at' => now()->toIso8601String(),
        'width' => 100, 'height' => 1080,
        'screenshot' => UploadedFile::fake()->create('s.webp', 100, 'image/webp'),
    ]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('width'))->toBeTrue();
});
```

- [ ] **Step 2: Eseguire (fallisce — classe non esiste)**

Run: `php artisan test --filter=StoreScreenshotRequestTest`
Expected: FAIL.

- [ ] **Step 3: Creare il Form Request**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreScreenshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'captured_at' => ['required', 'date', 'before_or_equal:now', 'after:-5 minutes'],
            'width'       => ['required', 'integer', 'between:320,4096'],
            'height'      => ['required', 'integer', 'between:180,2160'],
            'screenshot'  => ['required', 'file', 'mimes:webp', 'max:2048'],
        ];
    }
}
```

- [ ] **Step 4: Eseguire (deve passare)**

Run: `php artisan test --filter=StoreScreenshotRequestTest`
Expected: 5 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Api/V1/StoreScreenshotRequest.php tests/Feature/Api/V1/StoreScreenshotRequestTest.php
git commit -m "feat(api): add StoreScreenshotRequest validation rules"
```

---

### Task 6: `ProcessScreenshotAction`

**Files:**
- Create: `app/Actions/ProcessScreenshotAction.php`
- Create: `tests/Feature/Actions/ProcessScreenshotActionTest.php`

- [ ] **Step 1: Scrivere il test**

```php
<?php

declare(strict_types=1);

use App\Actions\ProcessScreenshotAction;
use App\Events\ApplianceScreenshotReceived;
use App\Models\OnesiBox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('action persists file and record and dispatches event', function (): void {
    Storage::fake('local');
    Event::fake([ApplianceScreenshotReceived::class]);

    $box = OnesiBox::factory()->create();
    $file = UploadedFile::fake()->create('s.webp', 120, 'image/webp');
    $capturedAt = now()->subSeconds(5);

    $action = app(ProcessScreenshotAction::class);
    $screenshot = $action->execute($box, $capturedAt, 1920, 1080, $file);

    expect($screenshot->onesi_box_id)->toBe($box->id)
        ->and($screenshot->width)->toBe(1920)
        ->and($screenshot->height)->toBe(1080)
        ->and($screenshot->storage_path)->toStartWith("onesi-boxes/{$box->id}/screenshots/")
        ->and($screenshot->storage_path)->toEndWith('.webp');

    Storage::disk('local')->assertExists($screenshot->storage_path);
    Event::assertDispatched(ApplianceScreenshotReceived::class, fn ($e) => $e->screenshot->is($screenshot));
});
```

- [ ] **Step 2: Eseguire (fallisce)**

Run: `php artisan test --filter=ProcessScreenshotActionTest`
Expected: FAIL su `App\Actions\ProcessScreenshotAction` o `App\Events\ApplianceScreenshotReceived` mancanti.

- [ ] **Step 3: Creare evento placeholder (per ora non-broadcast, lo faremo nel Task 7)**

`app/Events/ApplianceScreenshotReceived.php`:

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApplianceScreenshot;
use Illuminate\Foundation\Events\Dispatchable;

class ApplianceScreenshotReceived
{
    use Dispatchable;

    public function __construct(public readonly ApplianceScreenshot $screenshot)
    {
    }
}
```

- [ ] **Step 4: Creare l'Action**

`app/Actions/ProcessScreenshotAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\ApplianceScreenshotReceived;
use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessScreenshotAction
{
    public function execute(
        OnesiBox $box,
        CarbonInterface $capturedAt,
        int $width,
        int $height,
        UploadedFile $file,
    ): ApplianceScreenshot {
        $uuid = substr(Str::uuid()->toString(), 0, 8);
        $filename = $capturedAt->format('Y-m-d\TH-i-s') . "_{$uuid}.webp";
        $directory = "onesi-boxes/{$box->id}/screenshots";
        $path = "{$directory}/{$filename}";

        Storage::disk('local')->putFileAs(
            $directory,
            $file,
            $filename,
            ['visibility' => 'private']
        );

        $screenshot = ApplianceScreenshot::create([
            'onesi_box_id' => $box->id,
            'captured_at'  => $capturedAt,
            'width'        => $width,
            'height'       => $height,
            'bytes'        => $file->getSize(),
            'storage_path' => $path,
        ]);

        event(new ApplianceScreenshotReceived($screenshot));

        return $screenshot;
    }
}
```

- [ ] **Step 5: Eseguire (deve passare)**

Run: `php artisan test --filter=ProcessScreenshotActionTest`
Expected: 1 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/ProcessScreenshotAction.php app/Events/ApplianceScreenshotReceived.php tests/Feature/Actions/ProcessScreenshotActionTest.php
git commit -m "feat(action): add ProcessScreenshotAction to persist and dispatch"
```

---

### Task 7: Promuovere l'evento a broadcast

**Files:**
- Modify: `app/Events/ApplianceScreenshotReceived.php`
- Modify: `tests/Feature/Actions/ProcessScreenshotActionTest.php` (se necessario)

- [ ] **Step 1: Aggiungere `ShouldBroadcast` all'evento**

Sovrascrivere il file con:

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApplianceScreenshot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplianceScreenshotReceived implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly ApplianceScreenshot $screenshot)
    {
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("appliance.{$this->screenshot->onesi_box_id}");
    }

    public function broadcastAs(): string
    {
        return 'ApplianceScreenshotReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->screenshot->id,
            'captured_at' => $this->screenshot->captured_at->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 2: Rieseguire test dell'Action (deve continuare a passare con `Event::fake`)**

Run: `php artisan test --filter=ProcessScreenshotActionTest`
Expected: 1 PASS.

- [ ] **Step 3: Commit**

```bash
git add app/Events/ApplianceScreenshotReceived.php
git commit -m "feat(event): make ApplianceScreenshotReceived a broadcast event"
```

---

### Task 8: Autorizzazione canale broadcast

**Files:**
- Modify: `routes/channels.php`
- Create: `tests/Feature/Broadcasting/ApplianceChannelAuthTest.php`

- [ ] **Step 1: Scrivere il test**

```php
<?php

declare(strict_types=1);

use App\Models\OnesiBox;
use App\Models\User;

test('admin can subscribe to appliance channel', function (): void {
    $box = OnesiBox::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin');  // oppure la logica `isAdmin` esistente del progetto

    $this->actingAs($admin)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => "private-appliance.{$box->id}",
        ])
        ->assertOk();
});

test('unrelated user cannot subscribe to appliance channel', function (): void {
    $box = OnesiBox::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => "private-appliance.{$box->id}",
        ])
        ->assertForbidden();
});
```

**NOTA PER L'EXECUTOR:** se `User::factory()` + `assignRole('admin')` non è la primitiva corretta nel repo, sostituire con l'equivalente esistente (es. flag `is_admin`, enum di ruoli, Gate). Verificare in `app/Models/User.php` come si marca un utente admin e adeguare il test.

- [ ] **Step 2: Eseguire (fallisce — il canale non è autorizzato)**

Run: `php artisan test --filter=ApplianceChannelAuthTest`
Expected: FAIL su entrambi i test (canale non registrato → 403 per tutti).

- [ ] **Step 3: Aggiungere la registrazione del canale**

In `routes/channels.php`, aggiungere in coda:

```php
use App\Models\OnesiBox;
use App\Models\User;

Broadcast::channel('appliance.{onesiBoxId}', function (User $user, int $onesiBoxId) {
    $box = OnesiBox::find($onesiBoxId);
    if ($box === null) {
        return false;
    }
    if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
        return true;
    }
    if (OnesiBox::userCanView($user, $box)) {
        return true;
    }
    return $box->caregivers()->where('users.id', $user->id)->exists();
});
```

**NOTA PER L'EXECUTOR:** `OnesiBox::userCanView($user, $box)` è la primitiva esistente usata nel trait `ChecksOnesiBoxPermission`. Verificare la firma esatta in `app/Models/OnesiBox.php` / `app/Traits/ChecksOnesiBoxPermission.php` e adeguare la chiamata. Se la firma è `userCanView($user)` (senza box), riadattare.

- [ ] **Step 4: Rieseguire il test**

Run: `php artisan test --filter=ApplianceChannelAuthTest`
Expected: 2 PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/channels.php tests/Feature/Broadcasting/ApplianceChannelAuthTest.php
git commit -m "feat(broadcast): authorize appliance private channel"
```

---

### Task 9: Policy `ApplianceScreenshotPolicy`

**Files:**
- Create: `app/Policies/ApplianceScreenshotPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php` (o `app/Providers/AppServiceProvider.php` se il progetto usa Laravel 11 senza `AuthServiceProvider.php` dedicato)
- Create: `tests/Feature/Policies/ApplianceScreenshotPolicyTest.php`

- [ ] **Step 1: Scrivere il test matrice**

```php
<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use App\Models\User;

function makeScreenshot(OnesiBox $box): ApplianceScreenshot {
    return ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => "onesi-boxes/{$box->id}/screenshots/test.webp",
    ]);
}

test('admin can view any screenshot', function (): void {
    $box = OnesiBox::factory()->create();
    $s = makeScreenshot($box);
    $admin = User::factory()->create();
    // Adeguare alla primitiva admin del progetto:
    $admin->assignRole('admin');

    expect($admin->can('view', $s))->toBeTrue();
});

test('caregiver of the box can view', function (): void {
    $box = OnesiBox::factory()->create();
    $s = makeScreenshot($box);
    $caregiver = User::factory()->create();
    $box->caregivers()->attach($caregiver->id);

    expect($caregiver->can('view', $s))->toBeTrue();
});

test('stranger cannot view', function (): void {
    $box = OnesiBox::factory()->create();
    $s = makeScreenshot($box);
    $stranger = User::factory()->create();

    expect($stranger->can('view', $s))->toBeFalse();
});
```

- [ ] **Step 2: Eseguire (fallisce — policy non registrata, ritorna false sempre)**

Run: `php artisan test --filter=ApplianceScreenshotPolicyTest`
Expected: FAIL sui primi due test.

- [ ] **Step 3: Creare la policy**

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use App\Models\User;

class ApplianceScreenshotPolicy
{
    public function view(User $user, ApplianceScreenshot $screenshot): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return OnesiBox::userCanView($user, $screenshot->onesiBox);
    }
}
```

**NOTA PER L'EXECUTOR:** stessa nota del Task 8 su `userCanView` + `isAdmin`. Il pattern interno del progetto può divergere — allineare alla realtà.

- [ ] **Step 4: Registrare la policy**

Se esiste `app/Providers/AuthServiceProvider.php`, aggiungere alla mappa `$policies`:

```php
protected $policies = [
    // ...
    \App\Models\ApplianceScreenshot::class => \App\Policies\ApplianceScreenshotPolicy::class,
];
```

Altrimenti (Laravel 11 senza AuthServiceProvider), registrare nel metodo `boot` di `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::policy(
    \App\Models\ApplianceScreenshot::class,
    \App\Policies\ApplianceScreenshotPolicy::class,
);
```

- [ ] **Step 5: Rieseguire**

Run: `php artisan test --filter=ApplianceScreenshotPolicyTest`
Expected: 3 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Policies/ApplianceScreenshotPolicy.php app/Providers/AuthServiceProvider.php app/Providers/AppServiceProvider.php tests/Feature/Policies/ApplianceScreenshotPolicyTest.php 2>/dev/null || true
git add -A app/Policies app/Providers tests/Feature/Policies
git commit -m "feat(policy): add ApplianceScreenshotPolicy with admin/caregiver access"
```

---

### Task 10: Rate limiter `screenshot-upload`

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Registrare il rate limiter nel metodo `boot`**

In `app/Providers/AppServiceProvider.php` dentro il metodo `boot()`, dopo gli altri RateLimiter esistenti, aggiungere:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('screenshot-upload', function (Request $request) {
    return Limit::perMinute(12)->by(
        $request->user()?->getAuthIdentifier() ?: $request->ip()
    );
});
```

- [ ] **Step 2: Verifica che il container carica senza errori**

Run: `php artisan about` (o `php artisan --version`)
Expected: comando termina senza eccezioni.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat(api): register screenshot-upload rate limiter (12/min)"
```

---

### Task 11: `ScreenshotController@store` + route

**Files:**
- Create: `app/Http/Controllers/Api/V1/ScreenshotController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/V1/StoreScreenshotApiTest.php`

- [ ] **Step 1: Scrivere i test dell'endpoint**

```php
<?php

declare(strict_types=1);

use App\Events\ApplianceScreenshotReceived;
use App\Models\OnesiBox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('valid screenshot upload creates record and file', function (): void {
    Storage::fake('local');
    Event::fake([ApplianceScreenshotReceived::class]);

    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        [
            'captured_at' => now()->subSeconds(5)->toIso8601String(),
            'width' => 1920,
            'height' => 1080,
            'screenshot' => UploadedFile::fake()->create('s.webp', 120, 'image/webp'),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertCreated()
        ->assertJsonStructure(['id']);

    $this->assertDatabaseHas('appliance_screenshots', [
        'onesi_box_id' => $box->id,
        'width' => 1920,
    ]);

    Event::assertDispatched(ApplianceScreenshotReceived::class);
});

test('unauthenticated request is rejected', function (): void {
    $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        ['captured_at' => now()->toIso8601String(), 'width' => 1920, 'height' => 1080]
    )->assertUnauthorized();
});

test('non-webp upload returns 422', function (): void {
    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        [
            'captured_at' => now()->toIso8601String(),
            'width' => 1920, 'height' => 1080,
            'screenshot' => UploadedFile::fake()->image('s.png'),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    )->assertStatus(422);
});

test('oversized file returns 422', function (): void {
    $box = OnesiBox::factory()->create();
    $token = $box->createToken('onesibox-api-token');

    $this->postJson(
        route('api.v1.appliances.screenshot.store'),
        [
            'captured_at' => now()->toIso8601String(),
            'width' => 1920, 'height' => 1080,
            'screenshot' => UploadedFile::fake()->create('s.webp', 2100, 'image/webp'),
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    )->assertStatus(422);
});
```

- [ ] **Step 2: Eseguire (fallisce)**

Run: `php artisan test --filter=StoreScreenshotApiTest`
Expected: FAIL — route e controller non esistono.

- [ ] **Step 3: Creare il controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessScreenshotAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreScreenshotRequest;
use App\Models\ApplianceScreenshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenshotController extends Controller
{
    public function store(
        StoreScreenshotRequest $request,
        ProcessScreenshotAction $action,
    ): JsonResponse {
        $screenshot = $action->execute(
            $request->user(),
            $request->date('captured_at'),
            $request->integer('width'),
            $request->integer('height'),
            $request->file('screenshot'),
        );

        return response()->json(['id' => $screenshot->id], 201);
    }

    public function show(ApplianceScreenshot $screenshot): StreamedResponse
    {
        $this->authorize('view', $screenshot);

        return Storage::disk('local')->download(
            $screenshot->storage_path,
            basename($screenshot->storage_path),
            [
                'Content-Type' => 'image/webp',
                'Cache-Control' => 'private, max-age=60',
            ],
        );
    }
}
```

- [ ] **Step 4: Aggiungere la route**

In `routes/api.php`, dentro il gruppo `auth:sanctum` + `appliance.active` già esistente (quello che contiene heartbeat/commands/playback), aggiungere:

```php
Route::post('appliances/screenshot', [\App\Http\Controllers\Api\V1\ScreenshotController::class, 'store'])
    ->middleware('throttle:screenshot-upload')
    ->name('api.v1.appliances.screenshot.store');
```

- [ ] **Step 5: Rieseguire i test**

Run: `php artisan test --filter=StoreScreenshotApiTest`
Expected: 4 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/ScreenshotController.php routes/api.php tests/Feature/Api/V1/StoreScreenshotApiTest.php
git commit -m "feat(api): add screenshot upload endpoint"
```

---

### Task 12: `ScreenshotController@show` con signed URL

**Files:**
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/V1/ShowScreenshotApiTest.php`

- [ ] **Step 1: Scrivere i test**

```php
<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Storage::fake('local');
});

function createScreenshotWithFile(OnesiBox $box): ApplianceScreenshot {
    $path = "onesi-boxes/{$box->id}/screenshots/test.webp";
    Storage::disk('local')->put($path, 'binary-webp-placeholder');
    return ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => $path,
    ]);
}

test('admin with signed url can download', function (): void {
    $box = OnesiBox::factory()->create();
    $s = createScreenshotWithFile($box);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $url = URL::signedRoute('api.v1.screenshots.show',
        ['screenshot' => $s->id],
        now()->addMinutes(5));

    $this->actingAs($admin)
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});

test('unauthorized user gets 403 even with signed url', function (): void {
    $box = OnesiBox::factory()->create();
    $s = createScreenshotWithFile($box);
    $stranger = User::factory()->create();

    $url = URL::signedRoute('api.v1.screenshots.show',
        ['screenshot' => $s->id],
        now()->addMinutes(5));

    $this->actingAs($stranger)
        ->get($url)
        ->assertForbidden();
});

test('unsigned url is rejected', function (): void {
    $box = OnesiBox::factory()->create();
    $s = createScreenshotWithFile($box);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('api.v1.screenshots.show', ['screenshot' => $s->id]))
        ->assertForbidden();
});

test('expired signed url is rejected', function (): void {
    $box = OnesiBox::factory()->create();
    $s = createScreenshotWithFile($box);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $url = URL::signedRoute('api.v1.screenshots.show',
        ['screenshot' => $s->id],
        now()->addMinutes(5));

    $this->travel(6)->minutes();

    $this->actingAs($admin)
        ->get($url)
        ->assertForbidden();
});
```

- [ ] **Step 2: Eseguire (fallisce — route non registrata)**

Run: `php artisan test --filter=ShowScreenshotApiTest`
Expected: FAIL su tutti.

- [ ] **Step 3: Registrare la route di download**

In `routes/api.php` (fuori dal gruppo appliance, in una sezione nuova):

```php
Route::get('screenshots/{screenshot}', [\App\Http\Controllers\Api\V1\ScreenshotController::class, 'show'])
    ->middleware(['auth:sanctum', 'signed'])
    ->name('api.v1.screenshots.show');
```

- [ ] **Step 4: Rieseguire i test**

Run: `php artisan test --filter=ShowScreenshotApiTest`
Expected: 4 PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/api.php tests/Feature/Api/V1/ShowScreenshotApiTest.php
git commit -m "feat(api): add signed route for screenshot download"
```

---

### Task 13: Verifica configurazione Sanctum SPA + `HeartbeatResource` estensione

**Files:**
- Modify: `app/Http/Resources/Api/V1/HeartbeatResource.php`
- Possibly modify: `config/sanctum.php` + `bootstrap/app.php` + `.env`
- Create: `tests/Feature/Api/V1/HeartbeatResourceScreenshotFieldsTest.php`

- [ ] **Step 1: Ispezionare la configurazione Sanctum**

Run: `cat config/sanctum.php | grep -A2 'stateful'`
Verificare se il middleware `EnsureFrontendRequestsAreStateful` è attivo nel gruppo `api`: `grep -r "EnsureFrontendRequestsAreStateful" bootstrap/ app/Http/`.

Se **non è** configurato in modalità SPA, il caricamento delle immagini nel carosello caregiver fallirebbe con 401. Due opzioni:

**Opzione A (preferita):** abilitare SPA stateful.
- In `bootstrap/app.php`, dentro `withMiddleware(function (\Illuminate\Foundation\Configuration\Middleware $m)`:
  ```php
  $m->statefulApi();
  ```
- In `.env` aggiungere `SANCTUM_STATEFUL_DOMAINS=onesiforo.a80.it` (o il dominio reale di produzione) + host locale dev (`localhost`, `127.0.0.1:8000`).

**Opzione B (compromesso):** cambiare la route `api.v1.screenshots.show` da `['auth:sanctum', 'signed']` a `['auth:sanctum,web', 'signed']` in `routes/api.php`.

- [ ] **Step 2: Scegliere l'opzione e applicarla, poi testare il download da sessione web**

Dopo la modifica, creare un test aggiuntivo in `tests/Feature/Api/V1/ShowScreenshotApiTest.php`:

```php
test('web-session authenticated caregiver can download via signed url', function (): void {
    Storage::fake('local');
    $box = OnesiBox::factory()->create();
    $path = "onesi-boxes/{$box->id}/screenshots/test.webp";
    Storage::disk('local')->put($path, 'payload');
    $s = App\Models\ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => now(),
        'width' => 1920, 'height' => 1080, 'bytes' => 100,
        'storage_path' => $path,
    ]);
    $caregiver = User::factory()->create();
    $box->caregivers()->attach($caregiver->id);

    $url = URL::signedRoute('api.v1.screenshots.show',
        ['screenshot' => $s->id],
        now()->addMinutes(5));

    $this->actingAs($caregiver, 'web')
        ->get($url)
        ->assertOk();
});
```

Run: `php artisan test --filter=ShowScreenshotApiTest`
Expected: PASS anche il nuovo test.

- [ ] **Step 3: Estendere `HeartbeatResource`**

Ispezionare `app/Http/Resources/Api/V1/HeartbeatResource.php` e aggiungere al `toArray()` i due campi (allineare il posizionamento alla struttura `data` già esistente — verificare se è wrap dentro `data` o top-level):

```php
'screenshot_enabled'          => (bool) $this->resource->screenshot_enabled,
'screenshot_interval_seconds' => (int)  $this->resource->screenshot_interval_seconds,
```

- [ ] **Step 4: Scrivere il test**

`tests/Feature/Api/V1/HeartbeatResourceScreenshotFieldsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;

test('heartbeat response includes screenshot config fields', function (): void {
    $box = OnesiBox::factory()->create([
        'screenshot_enabled' => true,
        'screenshot_interval_seconds' => 45,
    ]);
    $token = $box->createToken('onesibox-api-token');

    $response = $this->postJson(
        route('api.v1.appliances.heartbeat'),
        ['status' => OnesiBoxStatus::Idle->value],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response->assertOk()
        ->assertJsonPath('data.screenshot_enabled', true)
        ->assertJsonPath('data.screenshot_interval_seconds', 45);
});
```

**NOTA PER L'EXECUTOR:** se la response heartbeat non è wrap dentro `data` nel repo reale, adattare il path (`assertJsonPath('screenshot_enabled', ...)`). Verificare guardando il test esistente `HeartbeatApiTest.php`.

- [ ] **Step 5: Eseguire**

Run: `php artisan test --filter=HeartbeatResourceScreenshotFieldsTest`
Expected: 1 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Resources/Api/V1/HeartbeatResource.php tests/Feature/Api/V1/HeartbeatResourceScreenshotFieldsTest.php config/sanctum.php bootstrap/app.php routes/api.php .env.example 2>/dev/null || true
git add -A
git commit -m "feat(api): expose screenshot config in heartbeat response and ensure SPA auth"
```

---

## Fase B — Retention

### Task 14: `PruneScreenshotsCommand` — rollup

**Files:**
- Create: `app/Console/Commands/PruneScreenshotsCommand.php`
- Create: `tests/Feature/Console/PruneScreenshotsCommandTest.php`

- [ ] **Step 1: Scrivere i test del rollup**

```php
<?php

declare(strict_types=1);

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function makeSs(OnesiBox $box, string $capturedAt, string $suffix = ''): ApplianceScreenshot {
    $path = "onesi-boxes/{$box->id}/screenshots/" . str_replace([':', ' '], '-', $capturedAt) . "_{$suffix}.webp";
    Storage::disk('local')->put($path, 'p');
    return ApplianceScreenshot::create([
        'onesi_box_id' => $box->id,
        'captured_at' => $capturedAt,
        'width' => 1920, 'height' => 1080, 'bytes' => 1,
        'storage_path' => $path,
    ]);
}

test('records older than 24h are deleted (record + file)', function (): void {
    $box = OnesiBox::factory()->create();
    $stale = makeSs($box, now()->subHours(25)->toDateTimeString(), 'stale');
    $fresh = makeSs($box, now()->toDateTimeString(), 'fresh');

    $this->artisan('onesibox:prune-screenshots')->assertSuccessful();

    expect(ApplianceScreenshot::find($stale->id))->toBeNull();
    expect(ApplianceScreenshot::find($fresh->id))->not->toBeNull();
    expect(Storage::disk('local')->exists($stale->storage_path))->toBeFalse();
});

test('keeps top 10 most recent verbatim', function (): void {
    $box = OnesiBox::factory()->create();
    for ($i = 0; $i < 15; $i++) {
        makeSs($box, now()->subMinutes($i)->toDateTimeString(), (string) $i);
    }

    $this->artisan('onesibox:prune-screenshots')->assertSuccessful();

    expect(ApplianceScreenshot::where('onesi_box_id', $box->id)->count())->toBe(10);
});

test('rollup keeps one per hour bucket for records beyond top 10', function (): void {
    $box = OnesiBox::factory()->create();
    // 10 recenti nell'ultimo minuto
    for ($i = 0; $i < 10; $i++) {
        makeSs($box, now()->subSeconds($i * 5)->toDateTimeString(), "top{$i}");
    }
    // 3 record nella stessa ora (2 ore fa) — dopo rollup deve restarne 1
    $two_hours_ago = now()->subHours(2);
    makeSs($box, $two_hours_ago->copy()->addMinutes(5)->toDateTimeString(), 'h2-a');
    makeSs($box, $two_hours_ago->copy()->addMinutes(25)->toDateTimeString(), 'h2-b');
    makeSs($box, $two_hours_ago->copy()->addMinutes(45)->toDateTimeString(), 'h2-c');

    $this->artisan('onesibox:prune-screenshots')->assertSuccessful();

    $beyondTop10 = ApplianceScreenshot::where('onesi_box_id', $box->id)
        ->where('captured_at', '<', now()->subMinute())
        ->count();
    expect($beyondTop10)->toBe(1);
    expect(ApplianceScreenshot::where('onesi_box_id', $box->id)->count())->toBe(11);
});
```

- [ ] **Step 2: Eseguire (fallisce — comando non esiste)**

Run: `php artisan test --filter=PruneScreenshotsCommandTest`
Expected: FAIL su tutti.

- [ ] **Step 3: Creare il comando**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneScreenshotsCommand extends Command
{
    protected $signature = 'onesibox:prune-screenshots {--sweep-orphans}';

    protected $description = 'Apply rollup retention (top 10 + 1 per hour within 24h) to appliance screenshots';

    public function handle(): int
    {
        $startedAt = microtime(true);

        if ($this->option('sweep-orphans')) {
            $orphans = $this->sweepOrphans();
            $this->info("Orphan sweep: {$orphans} files removed.");
            return self::SUCCESS;
        }

        $stats = ['boxes' => 0, 'older_24h' => 0, 'rollup' => 0];

        OnesiBox::query()->chunkById(100, function (Collection $boxes) use (&$stats): void {
            foreach ($boxes as $box) {
                $stats['boxes']++;

                $stats['older_24h'] += $this->deleteOlderThan24h($box);
                $stats['rollup']    += $this->rollupBeyondTop10($box);
            }
        });

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        Log::info('prune-screenshots completed', [
            'boxes' => $stats['boxes'],
            'deleted_total' => $stats['older_24h'] + $stats['rollup'],
            'older_24h' => $stats['older_24h'],
            'rollup' => $stats['rollup'],
            'duration_ms' => $durationMs,
        ]);

        $this->info(sprintf(
            'Pruned screenshots: boxes=%d, older_24h=%d, rollup=%d, duration_ms=%d',
            $stats['boxes'], $stats['older_24h'], $stats['rollup'], $durationMs
        ));

        return self::SUCCESS;
    }

    private function deleteOlderThan24h(OnesiBox $box): int
    {
        $toDelete = ApplianceScreenshot::query()
            ->where('onesi_box_id', $box->id)
            ->where('captured_at', '<', now()->subHours(24))
            ->get();

        $count = $toDelete->count();
        $toDelete->each->delete();

        return $count;
    }

    private function rollupBeyondTop10(OnesiBox $box): int
    {
        $all = ApplianceScreenshot::query()
            ->where('onesi_box_id', $box->id)
            ->orderByDesc('captured_at')
            ->get(['id', 'captured_at', 'storage_path']);

        if ($all->count() <= 10) {
            return 0;
        }

        $top10 = $all->take(10);
        $rest  = $all->slice(10);

        $keepIds = $top10->pluck('id')->all();

        $byHour = $rest->groupBy(fn (ApplianceScreenshot $s) => $s->captured_at->format('Y-m-d H:00'));
        foreach ($byHour as $bucket) {
            $keepIds[] = $bucket->first()->id; // first = most recent (già ordinati desc)
        }

        $toDelete = ApplianceScreenshot::query()
            ->where('onesi_box_id', $box->id)
            ->whereNotIn('id', $keepIds)
            ->get();

        $count = $toDelete->count();
        $toDelete->each->delete();

        return $count;
    }

    private function sweepOrphans(): int
    {
        $disk = Storage::disk('local');
        $base = 'onesi-boxes';

        if (! $disk->exists($base)) {
            return 0;
        }

        $removed = 0;
        foreach ($disk->directories($base) as $boxDir) {
            $screenshotsDir = "{$boxDir}/screenshots";
            if (! $disk->exists($screenshotsDir)) {
                continue;
            }
            foreach (array_chunk($disk->files($screenshotsDir), 500) as $chunk) {
                $existing = ApplianceScreenshot::query()
                    ->whereIn('storage_path', $chunk)
                    ->pluck('storage_path')
                    ->all();
                $orphans = array_diff($chunk, $existing);
                foreach ($orphans as $path) {
                    $disk->delete($path);
                    $removed++;
                }
            }
        }

        if ($removed > 0) {
            Log::warning("prune-screenshots orphan sweep removed {$removed} files");
        }

        return $removed;
    }
}
```

- [ ] **Step 4: Rieseguire i test**

Run: `php artisan test --filter=PruneScreenshotsCommandTest`
Expected: 3 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/PruneScreenshotsCommand.php tests/Feature/Console/PruneScreenshotsCommandTest.php
git commit -m "feat(console): add prune-screenshots command with 24h+rollup retention"
```

---

### Task 15: Test del sweep orfani

**Files:**
- Modify: `tests/Feature/Console/PruneScreenshotsCommandTest.php`

- [ ] **Step 1: Aggiungere il test**

Appendere al file `PruneScreenshotsCommandTest.php`:

```php
test('orphan sweep removes untracked files and keeps tracked ones', function (): void {
    $box = OnesiBox::factory()->create();

    $tracked = makeSs($box, now()->toDateTimeString(), 'tracked');
    $orphanPath = "onesi-boxes/{$box->id}/screenshots/orphan.webp";
    Storage::disk('local')->put($orphanPath, 'orphan');

    $this->artisan('onesibox:prune-screenshots --sweep-orphans')->assertSuccessful();

    expect(Storage::disk('local')->exists($tracked->storage_path))->toBeTrue();
    expect(Storage::disk('local')->exists($orphanPath))->toBeFalse();
});
```

- [ ] **Step 2: Eseguire**

Run: `php artisan test --filter=PruneScreenshotsCommandTest`
Expected: 4 PASS (inclusi i 3 precedenti).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Console/PruneScreenshotsCommandTest.php
git commit -m "test(console): add orphan sweep test for prune-screenshots"
```

---

### Task 16: Registrazione schedule

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Aggiungere le voci di schedule**

In `routes/console.php`, aggiungere (usando import top-of-file `use Illuminate\Support\Facades\Schedule;`):

```php
Schedule::command('onesibox:prune-screenshots')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('onesibox:prune-screenshots --sweep-orphans')
    ->dailyAt('03:15')
    ->withoutOverlapping();
```

- [ ] **Step 2: Verificare che lo schedule sia listato**

Run: `php artisan schedule:list`
Expected: due righe che citano `onesibox:prune-screenshots`.

- [ ] **Step 3: Commit**

```bash
git add routes/console.php
git commit -m "feat(schedule): run prune-screenshots every 5m + daily orphan sweep"
```

---

## Fase C — UI Admin (Filament)

### Task 17: Sezione "Diagnostica" nel form `OnesiBoxForm`

**Files:**
- Modify: `app/Filament/Resources/OnesiBoxes/Schemas/OnesiBoxForm.php`

- [ ] **Step 1: Aggiungere la Section**

Ispezionare `OnesiBoxForm.php` per capire il pattern delle sezioni esistenti (es. "Informazioni Dispositivo"). Aggiungere una nuova `Section` con due campi:

```php
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;

// dentro l'array di sections esistenti, dopo l'ultima:
Section::make('Diagnostica schermo')
    ->description('Cattura periodica di screenshot del monitor della box per diagnostica.')
    ->schema([
        Toggle::make('screenshot_enabled')
            ->label('Attiva diagnostica')
            ->default(true)
            ->helperText('La box applicherà il cambio al prossimo heartbeat (entro 30s).'),
        TextInput::make('screenshot_interval_seconds')
            ->label('Intervallo (secondi)')
            ->numeric()
            ->minValue(10)
            ->maxValue(3600)
            ->default(60)
            ->suffix('s')
            ->helperText('Intervallo tra uno scatto e il successivo. Min 10s, max 3600s (1h).'),
    ])
    ->collapsed(),
```

- [ ] **Step 2: Smoke check sul pannello admin**

Run: `php artisan serve` (in background) → aprire `/admin/onesi-boxes/{id}/edit`, verificare che la sezione "Diagnostica schermo" appaia e che il salvataggio persista i valori.

Stop del server quando finito.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/OnesiBoxes/Schemas/OnesiBoxForm.php
git commit -m "feat(filament): add screenshot diagnostic section to OnesiBox form"
```

---

### Task 18: `ManageScreenshots` Filament Page

**Files:**
- Create: `app/Filament/Resources/OnesiBoxes/Pages/ManageScreenshots.php`
- Create: `resources/views/filament/onesi-boxes/screenshots.blade.php` (stub per il momento)
- Modify: `app/Filament/Resources/OnesiBoxes/OnesiBoxResource.php`

- [ ] **Step 1: Creare la Page**

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Pages;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Models\OnesiBox;
use Filament\Resources\Pages\Page;

class ManageScreenshots extends Page
{
    protected static string $resource = OnesiBoxResource::class;

    protected static string $view = 'filament.onesi-boxes.screenshots';

    public OnesiBox $record;

    public function mount(int|string $record): void
    {
        $this->record = OnesiBox::findOrFail($record);
    }

    public static function getRoutePath(): string
    {
        return '{record}/screenshots';
    }

    public function getTitle(): string
    {
        return "Diagnostica — {$this->record->name}";
    }

    public function getHeading(): string
    {
        return 'Screenshot diagnostici';
    }
}
```

- [ ] **Step 2: Creare la view stub**

`resources/views/filament/onesi-boxes/screenshots.blade.php`:

```blade
<x-filament-panels::page>
    <livewire:filament.screenshots-viewer :record="$record" :key="'viewer-'.$record->id" />
</x-filament-panels::page>
```

(Il componente Livewire verrà creato nel Task 20 — per ora la Page è registrabile e la rotta esiste, ma l'embed fallirà in run-time finché non esiste il componente. Accettato: testiamo la registrazione.)

- [ ] **Step 3: Registrare la Page nel Resource**

In `app/Filament/Resources/OnesiBoxes/OnesiBoxResource.php`, nel metodo `getPages()`, aggiungere:

```php
'screenshots' => Pages\ManageScreenshots::route('/{record}/screenshots'),
```

L'array deve includere le entry già esistenti; aggiungere la nuova riga in coda, prima della chiusura dell'array.

- [ ] **Step 4: Verificare la rotta**

Run: `php artisan route:list | grep screenshots`
Expected: presente la rotta `filament.admin.resources.onesi-boxes.screenshots` → `GET admin/onesi-boxes/{record}/screenshots`.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/OnesiBoxes/Pages/ManageScreenshots.php resources/views/filament/onesi-boxes/screenshots.blade.php app/Filament/Resources/OnesiBoxes/OnesiBoxResource.php
git commit -m "feat(filament): register ManageScreenshots custom page"
```

---

### Task 19: Entry point — header action + row action

**Files:**
- Modify: `app/Filament/Resources/OnesiBoxes/Pages/EditOnesiBox.php`
- Modify: `app/Filament/Resources/OnesiBoxes/Pages/ListOnesiBoxes.php` (o la classe Table del Resource, a seconda di come è strutturato il Resource)

- [ ] **Step 1: Aggiungere header action su EditOnesiBox**

Nel metodo `getHeaderActions()` di `EditOnesiBox` (se non esiste, crearlo):

```php
use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use Filament\Actions\Action;
use App\Models\OnesiBox;

protected function getHeaderActions(): array
{
    return [
        Action::make('screenshots')
            ->label('Diagnostica schermo')
            ->icon('heroicon-o-camera')
            ->url(fn (OnesiBox $record) =>
                OnesiBoxResource::getUrl('screenshots', ['record' => $record])),
        // mantenere eventuali altre action esistenti
    ];
}
```

- [ ] **Step 2: Aggiungere row action nella List table**

Individuare dove sono definite le action della tabella del Resource (tipicamente `OnesiBoxResource::table()` o `OnesiBoxesTable` Schema). Aggiungere tra le `Actions\Action` della riga:

```php
use Filament\Tables\Actions\Action as TableAction;
use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;

TableAction::make('screenshots')
    ->label('Diagnostica')
    ->icon('heroicon-o-camera')
    ->url(fn (OnesiBox $record) =>
        OnesiBoxResource::getUrl('screenshots', ['record' => $record])),
```

- [ ] **Step 3: Smoke check**

Aprire `/admin/onesi-boxes`: verificare icon camera come row-action. Aprire una box in edit: verificare action header. Il click deve portare alla Page (che mostrerà ancora il livewire embed che fallisce — ok per il momento).

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/OnesiBoxes/
git commit -m "feat(filament): add screenshot action to edit and list views"
```

---

### Task 20: Livewire `ScreenshotsViewer`

**Files:**
- Create: `app/Livewire/Filament/ScreenshotsViewer.php`
- Create: `resources/views/livewire/filament/screenshots-viewer.blade.php`

- [ ] **Step 1: Creare il componente**

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Models\OnesiBox;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ScreenshotsViewer extends Component
{
    public OnesiBox $record;
    public ?int $selectedId = null;

    #[Validate('boolean')]
    public bool $enabled = true;

    #[Validate(['integer', 'between:10,3600'])]
    public int $intervalSeconds = 60;

    public function mount(OnesiBox $record): void
    {
        $this->record          = $record;
        $this->enabled         = $record->screenshot_enabled;
        $this->intervalSeconds = $record->screenshot_interval_seconds;
    }

    #[Computed]
    public function screenshots(): Collection
    {
        return $this->record->screenshots()
            ->orderByDesc('captured_at')
            ->get();
    }

    #[Computed]
    public function top10(): Collection
    {
        return $this->screenshots->take(10)->values();
    }

    #[Computed]
    public function hourlyBeyondTop10(): Collection
    {
        return $this->screenshots->slice(10)->values();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
    }

    public function toggle(): void
    {
        $this->record->update(['screenshot_enabled' => ! $this->record->screenshot_enabled]);
        $this->record->refresh();
        $this->enabled = $this->record->screenshot_enabled;
    }

    public function saveInterval(): void
    {
        $this->validateOnly('intervalSeconds');
        $this->record->update(['screenshot_interval_seconds' => $this->intervalSeconds]);
        $this->record->refresh();
    }

    #[On('echo-private:appliance.{record.id},ApplianceScreenshotReceived')]
    public function onNewScreenshot(): void
    {
        unset($this->screenshots);
        unset($this->top10);
        unset($this->hourlyBeyondTop10);
    }

    public function render()
    {
        return view('livewire.filament.screenshots-viewer');
    }
}
```

- [ ] **Step 2: Creare la view**

`resources/views/livewire/filament/screenshots-viewer.blade.php`:

```blade
<div class="space-y-6">
    {{-- HEADER --}}
    <div class="rounded border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 p-4">
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <span class="inline-flex items-center gap-2">
                    @if ($this->enabled)
                        <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                        <span class="font-semibold">Attiva</span>
                    @else
                        <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                        <span class="font-semibold">Disattivata</span>
                    @endif
                </span>
            </div>
            <div>
                Intervallo:
                <input type="number"
                       min="10" max="3600"
                       wire:model="intervalSeconds"
                       class="w-24 rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700" />
                <span>s</span>
                <button type="button"
                        wire:click="saveInterval"
                        class="ml-1 px-2 py-1 rounded bg-primary-600 text-white text-sm">
                    Salva
                </button>
                @error('intervalSeconds')
                    <span class="text-red-600 text-sm ml-2">{{ $message }}</span>
                @enderror
            </div>
            <div class="ml-auto">
                <button type="button"
                        wire:click="toggle"
                        class="px-3 py-1 rounded {{ $this->enabled ? 'bg-red-600' : 'bg-green-600' }} text-white text-sm">
                    {{ $this->enabled ? 'Disattiva' : 'Attiva' }}
                </button>
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-500">
            La box applicherà il cambio al prossimo heartbeat (entro 30s).
        </p>
    </div>

    {{-- PREVIEW GRANDE --}}
    @php
        $selected = $this->selectedId
            ? $this->screenshots->firstWhere('id', $this->selectedId)
            : $this->screenshots->first();
    @endphp

    @if ($selected)
        <div class="rounded border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 p-4">
            <img src="{{ $selected->signedUrl() }}"
                 alt="screenshot"
                 loading="lazy"
                 class="w-full max-w-full rounded" />
            <div class="mt-2 text-sm text-gray-500">
                {{ $selected->captured_at->toDateTimeString() }} —
                {{ $selected->width }}×{{ $selected->height }}, {{ round($selected->bytes / 1024) }} KB
                <a href="{{ $selected->signedUrl(10) }}"
                   download
                   class="ml-2 underline">Scarica originale</a>
            </div>
        </div>
    @elseif (! $this->enabled)
        <div class="rounded bg-gray-100 dark:bg-gray-800 p-6 text-center text-gray-500">
            Diagnostica disabilitata. Abilitala per iniziare la cattura.
        </div>
    @else
        <div class="rounded bg-gray-100 dark:bg-gray-800 p-6 text-center text-gray-500">
            In attesa del primo screenshot… (entro ~{{ $this->intervalSeconds }}s dall'abilitazione)
        </div>
    @endif

    {{-- TIMELINE --}}
    @if ($this->top10->isNotEmpty())
        <div>
            <h3 class="font-semibold mb-2">Ultimi 10 (realtime)</h3>
            <div class="flex gap-2 overflow-x-auto pb-2">
                @foreach ($this->top10 as $s)
                    <button type="button"
                            wire:click="select({{ $s->id }})"
                            class="shrink-0 {{ $this->selectedId === $s->id ? 'ring-2 ring-primary-500' : '' }}">
                        <img src="{{ $s->signedUrl() }}"
                             loading="lazy"
                             width="160" height="90"
                             class="rounded border border-gray-300 dark:border-gray-700" />
                        <div class="text-xs text-gray-500 text-center mt-1">
                            {{ $s->captured_at->diffForHumans() }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->hourlyBeyondTop10->isNotEmpty())
        <div>
            <h3 class="font-semibold mb-2">24 ore (una per ora)</h3>
            <div class="flex gap-2 overflow-x-auto pb-2">
                @foreach ($this->hourlyBeyondTop10 as $s)
                    <button type="button"
                            wire:click="select({{ $s->id }})"
                            class="shrink-0 {{ $this->selectedId === $s->id ? 'ring-2 ring-primary-500' : '' }}">
                        <img src="{{ $s->signedUrl() }}"
                             loading="lazy"
                             width="160" height="90"
                             class="rounded border border-gray-300 dark:border-gray-700" />
                        <div class="text-xs text-gray-500 text-center mt-1">
                            {{ $s->captured_at->format('H:00') }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
```

- [ ] **Step 3: Smoke check**

Avviare `php artisan serve` + `php artisan reverb:start`, navigare a `/admin/onesi-boxes/{id}/screenshots`: la Page deve renderizzarsi senza errori (anche se senza screenshot reali, l'empty state appare).

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/Filament/ScreenshotsViewer.php resources/views/livewire/filament/screenshots-viewer.blade.php
git commit -m "feat(filament): add ScreenshotsViewer livewire component"
```

---

## Fase D — UI Caregiver (Livewire dashboard)

### Task 21: `ScreenshotCarousel` component

**Files:**
- Create: `app/Livewire/OnesiBox/ScreenshotCarousel.php`
- Create: `resources/views/livewire/onesi-box/screenshot-carousel.blade.php`

- [ ] **Step 1: Creare il componente**

```php
<?php

declare(strict_types=1);

namespace App\Livewire\OnesiBox;

use App\Models\OnesiBox;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ScreenshotCarousel extends Component
{
    public OnesiBox $box;
    public string $variant = 'full';
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
    public function refresh(): void
    {
        unset($this->screenshots);
    }

    public function render()
    {
        return view('livewire.onesi-box.screenshot-carousel');
    }
}
```

- [ ] **Step 2: Creare la view con le due varianti**

```blade
@php $screenshots = $this->screenshots; @endphp

@if ($screenshots->isNotEmpty())
    @if ($variant === 'compact')
        <div class="mt-3 flex gap-1 overflow-x-auto pb-1" role="region" aria-label="Diagnostica">
            @foreach ($screenshots as $s)
                <a href="{{ $s->signedUrl() }}" target="_blank" rel="noopener" class="shrink-0">
                    <img src="{{ $s->signedUrl() }}"
                         alt="{{ $s->captured_at->toDateTimeString() }}"
                         loading="lazy"
                         width="80" height="45"
                         class="rounded border border-gray-200 dark:border-gray-700 {{ $loop->first ? 'ring-2 ring-primary-500' : '' }}" />
                </a>
            @endforeach
        </div>
    @else
        <section class="mt-4" aria-label="Diagnostica schermo">
            <h3 class="font-semibold mb-2">Diagnostica schermo</h3>
            <div class="flex gap-2 overflow-x-auto pb-2">
                @foreach ($screenshots as $s)
                    <a href="{{ $s->signedUrl() }}" target="_blank" rel="noopener" class="shrink-0">
                        <img src="{{ $s->signedUrl() }}"
                             alt="{{ $s->captured_at->toDateTimeString() }}"
                             loading="lazy"
                             width="160" height="90"
                             class="rounded border border-gray-200 dark:border-gray-700 {{ $loop->first ? 'ring-2 ring-primary-500' : '' }}" />
                        <div class="text-xs text-gray-500 text-center mt-1">
                            {{ $s->captured_at->diffForHumans() }}
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@elseif ($variant === 'compact' && ! $box->screenshot_enabled)
    <div class="mt-3 text-xs text-gray-500 italic">Diagnostica non attiva</div>
@else
    {{-- detail view empty OR compact view with feature enabled but no data: render nothing --}}
@endif
```

- [ ] **Step 3: Smoke check**

Il componente va verificato nell'integrazione (Task 22, 23). Per ora verifica che non dia errori fatali con `php artisan view:clear && php artisan config:clear`.

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/OnesiBox/ScreenshotCarousel.php resources/views/livewire/onesi-box/screenshot-carousel.blade.php
git commit -m "feat(livewire): add ScreenshotCarousel component with compact and full variants"
```

---

### Task 22: Integrazione carosello nella list view `/dashboard`

**Files:**
- Modify: `resources/views/livewire/dashboard/onesi-box-list.blade.php`

- [ ] **Step 1: Inserire il componente in ogni card**

Aprire `resources/views/livewire/dashboard/onesi-box-list.blade.php`. Trovare il blocco del loop `@foreach ... $box ... @endforeach` che renderizza ogni card. Dopo la parte di status/info del box (es. sotto "online/offline"), aggiungere:

```blade
<livewire:onesi-box.screenshot-carousel
    :box="$box"
    variant="compact"
    :key="'carousel-compact-'.$box->id" />
```

- [ ] **Step 2: Smoke check**

Run: `php artisan serve`, autenticarsi come caregiver con box assegnata, aprire `/dashboard`. Il carosello compact deve apparire sotto ciascuna card (vuoto finché la box non manda screenshot).

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/dashboard/onesi-box-list.blade.php
git commit -m "feat(dashboard): embed compact screenshot carousel in box list cards"
```

---

### Task 23: Integrazione carosello nella detail view `/dashboard/{box}`

**Files:**
- Modify: `resources/views/livewire/dashboard/onesi-box-detail.blade.php`

- [ ] **Step 1: Inserire il componente sotto l'hero card**

Individuare l'area dopo l'hero card (circa riga 50 secondo il recon). Aggiungere:

```blade
<livewire:onesi-box.screenshot-carousel
    :box="$onesiBox"
    variant="full"
    :key="'carousel-full-'.$onesiBox->id" />
```

Il nome della property del componente padre è `$onesiBox` (come da `OnesiBoxDetail` Livewire). **NOTA PER L'EXECUTOR:** confermare leggendo `app/Livewire/Dashboard/OnesiBoxDetail.php` — se la property ha un altro nome, adeguare.

- [ ] **Step 2: Smoke check**

Con un box che abbia almeno 1 screenshot nel DB (puoi inserirlo con `ApplianceScreenshot::factory()` o manualmente via tinker), verificare che la sezione "Diagnostica schermo" appaia in `/dashboard/{box}` e che le immagini siano caricate (serve Sanctum SPA funzionante, Task 13).

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/dashboard/onesi-box-detail.blade.php
git commit -m "feat(dashboard): embed full screenshot carousel in box detail view"
```

---

## Fase E — Verifica operativa

### Task 24: Smoke test end-to-end

**Files:**
- Nessuna modifica codice. Solo validazione manuale.

- [ ] **Step 1: Precondizioni**

- Migrations applicate in dev.
- Reverb in ascolto: `php artisan reverb:start` in terminal separato.
- Queue worker (se necessario per broadcast async): `php artisan queue:listen`.
- Scheduler test: `php artisan schedule:run` (manuale per verifica, o aspettare il cron).

- [ ] **Step 2: Simulare un upload dalla box**

Usando `curl` con token Sanctum di una OnesiBox di test:

```bash
curl -v -X POST https://onesiforo.local/api/v1/appliances/screenshot \
  -H "Authorization: Bearer ${APPLIANCE_TOKEN}" \
  -F "captured_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  -F "width=1920" \
  -F "height=1080" \
  -F "screenshot=@/path/to/real-sample.webp"
```

Expected: `201 Created` + body `{"id":N}`.

- [ ] **Step 3: Verificare nel pannello Filament**

Navigare a `/admin/onesi-boxes/{id}/screenshots`: l'immagine appena caricata deve comparire nella sezione "Ultimi 10 (realtime)" ed essere visualizzabile come preview.

- [ ] **Step 4: Verificare nella dashboard caregiver**

Autenticarsi come caregiver della box. Navigare a `/dashboard` e `/dashboard/{id}`: il carosello deve mostrare l'immagine. Upload di un secondo screenshot → deve apparire in tempo reale via Reverb (senza ricaricare).

- [ ] **Step 5: Verificare il pruning**

Inserire manualmente un record con `captured_at = now()->subHours(25)` + file dummy. Eseguire `php artisan onesibox:prune-screenshots` → il record e il file devono essere rimossi. Eseguire `--sweep-orphans` dopo aver creato un file orfano → deve essere rimosso.

- [ ] **Step 6: Verificare il rate limiting**

Loop di 13 `curl` in 60 secondi: il 13° deve ricevere `429 Too Many Requests`.

- [ ] **Step 7: Validazione conclusa — niente commit**

Se tutto funziona, passare al piano client. Se qualcosa fallisce, creare task correttivi.

---

## Coverage spec vs piano

| Sezione spec | Task che la implementa |
|---|---|
| §4.1 tabella `appliance_screenshots` | Task 1 |
| §4.2 colonne su `onesi_boxes` | Task 2 |
| §4.3 storage layout | Task 6 |
| §6.1 route upload + rate limit | Task 10, 11 |
| §6.1 route download signed | Task 12 |
| §6.2 validation | Task 5 |
| §6.3 controller | Task 11, 12 |
| §6.4 Action | Task 6 |
| §6.5 model + events + signed URL | Task 3 |
| §6.6 broadcast event + channel auth | Task 7, 8 |
| §6.7 relazioni OnesiBox | Task 4 |
| §6.8 policy | Task 9 |
| §6.9 HeartbeatResource | Task 13 |
| §7 retention (rollup + orfani + schedule) | Task 14, 15, 16 |
| §8 Filament admin Page | Task 17, 18, 19, 20 |
| §9 Livewire caregiver | Task 21, 22, 23 |
| §10.1 Sanctum SPA | Task 13 |
| §11 testing | distribuiti su tutti i task |
| §12 deployment smoke test | Task 24 |
