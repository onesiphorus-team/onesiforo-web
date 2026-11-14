<?php

declare(strict_types=1);

use App\Filament\Resources\OnesiBoxes\Pages\EditOnesiBox;
use App\Filament\Resources\OnesiBoxes\RelationManagers\ZoomMeetingsRelationManager;
use App\Models\OnesiBox;
use App\Models\User;
use App\Models\ZoomMeeting;
use Filament\Actions\Testing\TestAction;
use Oltrematica\RoleLite\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $this->actingAs($admin);

    $this->onesiBox = OnesiBox::factory()->create();
});

it('lists the Zoom meetings of the box', function (): void {
    $meetings = ZoomMeeting::factory()->for($this->onesiBox, 'onesiBox')->count(2)->create();
    $otherMeeting = ZoomMeeting::factory()->create();

    livewire(ZoomMeetingsRelationManager::class, ['ownerRecord' => $this->onesiBox, 'pageClass' => EditOnesiBox::class])
        ->assertCanSeeTableRecords($meetings)
        ->assertCanNotSeeTableRecords([$otherMeeting]);
});

it('adds a Zoom meeting to the box', function (): void {
    livewire(ZoomMeetingsRelationManager::class, ['ownerRecord' => $this->onesiBox, 'pageClass' => EditOnesiBox::class])
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'Adunanza Loreto',
            'zoom_url' => 'https://us05web.zoom.us/j/1234567890?pwd=abc',
        ])
        ->assertHasNoActionErrors();

    expect($this->onesiBox->zoomMeetings()->sole()->name)->toBe('Adunanza Loreto');
});

it('rejects a link that is not a Zoom meeting', function (): void {
    livewire(ZoomMeetingsRelationManager::class, ['ownerRecord' => $this->onesiBox, 'pageClass' => EditOnesiBox::class])
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'Adunanza Loreto',
            'zoom_url' => 'https://example.com/meeting',
        ])
        ->assertHasActionErrors(['zoom_url']);

    expect($this->onesiBox->zoomMeetings()->count())->toBe(0);
});
