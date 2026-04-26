<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use App\Models\User;

function makeScreenshot(OnesiBox $box): ApplianceScreenshot
{
    return ApplianceScreenshot::query()->create([
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
    $admin->assignRole('admin');

    expect($admin->can('view', $s))->toBeTrue();
});

test('super-admin can view any screenshot', function (): void {
    $box = OnesiBox::factory()->create();
    $s = makeScreenshot($box);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    expect($superAdmin->can('view', $s))->toBeTrue();
});

test('caregiver of the box can view', function (): void {
    $box = OnesiBox::factory()->create();
    $s = makeScreenshot($box);
    $caregiver = User::factory()->create();
    $caregiver->assignRole('caregiver');
    $box->caregivers()->attach($caregiver->id, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    expect($caregiver->can('view', $s))->toBeTrue();
});

test('stranger cannot view', function (): void {
    $box = OnesiBox::factory()->create();
    $s = makeScreenshot($box);
    $stranger = User::factory()->create();
    $stranger->assignRole('caregiver');

    expect($stranger->can('view', $s))->toBeFalse();
});
