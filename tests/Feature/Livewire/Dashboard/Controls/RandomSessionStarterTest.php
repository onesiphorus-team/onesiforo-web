<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\RandomSessionStarter;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->box = OnesiBox::factory()->online()->withCaregiver($this->user)->create();
    $this->actingAs($this->user);

    fakeJwOrgCategories([
        'VideoOnDemand' => ['key' => 'VideoOnDemand', 'name' => 'Categorie', 'type' => 'container', 'subcategories' => [
            ['key' => 'VODFamily', 'name' => 'Famiglia', 'type' => 'container', 'media' => []],
            ['key' => 'VODAudioDescriptions', 'name' => 'Audiodescrizioni', 'type' => 'container', 'media' => []],
        ]],
        'VODFamily' => ['key' => 'VODFamily', 'name' => 'Famiglia', 'type' => 'container', 'subcategories' => [
            ['key' => 'FamilyWorship', 'name' => 'Culto', 'type' => 'ondemand', 'media' => [jwOrgMedia('pub-family_I_VIDEO', 'Video famiglia')]],
        ]],
    ]);
});

it('preselects every category except audio descriptions', function (): void {
    Livewire::test(RandomSessionStarter::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->assertSet('selectedCategories', ['VODFamily']);
});

it('starts a random session for the chosen duration', function (): void {
    Livewire::test(RandomSessionStarter::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->set('durationMinutes', 30)
        ->call('start')
        ->assertHasNoErrors();

    expect($this->box->activeSession())
        ->duration_minutes->toBe(30)
        ->media_type->toBe('video');
});

it('rejects a session without categories or with an unknown one', function (array $categories): void {
    Livewire::test(RandomSessionStarter::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->set('selectedCategories', $categories)
        ->call('start')
        ->assertHasErrors();

    expect($this->box->activeSession())->toBeNull();
})->with([
    'no categories' => [[]],
    'unknown category' => [['VODSomethingElse']],
]);

it('rejects a duration that is not offered', function (): void {
    Livewire::test(RandomSessionStarter::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->set('durationMinutes', 45)
        ->call('start')
        ->assertHasErrors(['durationMinutes']);
});

it('forbids starting a session for a caregiver with read-only permission', function (): void {
    $viewer = User::factory()->create();
    $this->box->caregivers()->attach($viewer, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($viewer)
        ->test(RandomSessionStarter::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->call('start')
        ->assertForbidden();

    expect($this->box->activeSession())->toBeNull();
});
