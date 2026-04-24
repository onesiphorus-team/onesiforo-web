<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
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

test('caregiver of the box can download', function (): void {
    $box = OnesiBox::factory()->create();
    $s = createScreenshotWithFile($box);
    $caregiver = User::factory()->create();
    $caregiver->assignRole('caregiver');
    $box->caregivers()->attach($caregiver->id, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    $url = URL::signedRoute('api.v1.screenshots.show',
        ['screenshot' => $s->id],
        now()->addMinutes(5));

    $this->actingAs($caregiver)
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});

test('unauthorized user gets 403 even with signed url', function (): void {
    $box = OnesiBox::factory()->create();
    $s = createScreenshotWithFile($box);
    $stranger = User::factory()->create();
    $stranger->assignRole('caregiver');

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
