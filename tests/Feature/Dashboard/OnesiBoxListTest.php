<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\Roles;
use App\Livewire\Dashboard\OnesiBoxList;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

it('shows the OnesiBox list component to authenticated users', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    OnesiBox::factory()->online()->withCaregiver($user)->count(2)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(OnesiBoxList::class);
});

it('displays assigned OnesiBoxes for the authenticated caregiver', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox1 = OnesiBox::factory()->online()->create(['name' => 'OnesiBox Alpha']);
    $onesiBox2 = OnesiBox::factory()->offline()->create(['name' => 'OnesiBox Beta']);

    $onesiBox1->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);
    $onesiBox2->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(OnesiBoxList::class)
        ->assertSee('OnesiBox Alpha')
        ->assertSee('OnesiBox Beta');
});

it('shows empty state when caregiver has no assigned OnesiBoxes', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();

    Livewire::actingAs($user)
        ->test(OnesiBoxList::class)
        ->assertSee('Nessuna OnesiBox assegnata');
});

it('shows online status for OnesiBox with recent heartbeat', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->online()->create(['name' => 'OnesiBox Online']);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);
    OnesiBox::factory()->offline()->withCaregiver($user)->create();

    Livewire::actingAs($user)
        ->test(OnesiBoxList::class)
        ->assertSee('OnesiBox Online')
        ->assertSee('Online');
});

it('shows offline status for OnesiBox without recent heartbeat', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->offline()->create(['name' => 'OnesiBox Offline']);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);
    OnesiBox::factory()->online()->withCaregiver($user)->create();

    Livewire::actingAs($user)
        ->test(OnesiBoxList::class)
        ->assertSee('OnesiBox Offline')
        ->assertSee('Offline');
});

it('does not show unassigned OnesiBoxes', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $assignedBox = OnesiBox::factory()->create(['name' => 'Assigned Box']);
    $unassignedBox = OnesiBox::factory()->create(['name' => 'Unassigned Box']);

    $assignedBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);
    OnesiBox::factory()->withCaregiver($user)->create(['name' => 'Second Box']);

    Livewire::actingAs($user)
        ->test(OnesiBoxList::class)
        ->assertSee('Assigned Box')
        ->assertDontSee('Unassigned Box');
});

it('navigates to OnesiBox detail when selectOnesiBox is called', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);
    OnesiBox::factory()->withCaregiver($user)->create();

    Livewire::actingAs($user)
        ->test(OnesiBoxList::class)
        ->call('selectOnesiBox', $onesiBox->id)
        ->assertRedirect(route('dashboard.show', $onesiBox));
});

it('requires authentication to access the dashboard', function (): void {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('takes a family member with a single box straight to it', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    $onesiBox = OnesiBox::factory()->withCaregiver($user)->create();

    Livewire::actingAs($user)
        ->test(OnesiBoxList::class)
        ->assertRedirect(route('dashboard.show', $onesiBox));
});

it('keeps administrators on the list even with a single box', function (): void {
    $admin = User::factory()->role(Roles::Admin)->create();
    OnesiBox::factory()->withCaregiver($admin)->create(['name' => 'Unico Box']);

    Livewire::actingAs($admin)
        ->test(OnesiBoxList::class)
        ->assertNoRedirect()
        ->assertSee('Unico Box');
});

it('names each box after the person it serves and says in plain words what it is doing', function (): void {
    $user = User::factory()->role(Roles::Caregiver)->create();
    OnesiBox::factory()->online()->withCaregiver($user)->forRecipient()->create([
        'status' => App\Enums\OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.jw.org/video',
        'current_media_type' => 'video',
    ]);
    OnesiBox::factory()->offline()->withCaregiver($user)->create();

    $boxes = $user->onesiBoxes()->with('recipient')->get();

    Livewire::actingAs($user)
        ->test(OnesiBoxList::class)
        ->assertSeeInOrder([$boxes->firstWhere('recipient_id', '!=', null)->recipient->full_name, 'sta mostrando un video'])
        ->assertSee('spento o non connesso');
});
