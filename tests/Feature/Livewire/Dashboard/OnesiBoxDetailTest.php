<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\ActivityTimeline;
use App\Livewire\Dashboard\Controls\BottomBar;
use App\Livewire\Dashboard\Controls\CommandQueue;
use App\Livewire\Dashboard\Controls\HeroCard;
use App\Livewire\Dashboard\Controls\LogViewer;
use App\Livewire\Dashboard\Controls\MeetingSchedule;
use App\Livewire\Dashboard\Controls\QuickPlaySheet;
use App\Livewire\Dashboard\Controls\SystemInfo;
use App\Livewire\Dashboard\OnesiBoxDetail;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('keeps the technical details closed, and their components unmounted, until asked', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSee('Altro')
        ->assertDontSeeLivewire(CommandQueue::class)
        ->call('toggleTechnicalDetails')
        ->assertSeeLivewire(CommandQueue::class)
        ->call('toggleTechnicalDetails')
        ->assertDontSeeLivewire(CommandQueue::class);
});

it('shows system, network and logs only to administrators', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->call('toggleTechnicalDetails')
        ->assertDontSeeLivewire(SystemInfo::class)
        ->assertDontSeeLivewire(LogViewer::class);
});

it('counts the commands still waiting to reach the box next to "Altro"', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);
    Command::factory()->for($box, 'onesiBox')->count(2)->create(['status' => CommandStatus::Pending]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSee('2 in coda');
});

it('shows the activity of today and the meetings to every caregiver, including read-only ones', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::ReadOnly]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSeeLivewire(ActivityTimeline::class)
        ->assertSeeLivewire(MeetingSchedule::class);
});

it('tells a read-only caregiver why there are no commands, and leaves no room for the command bar', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::ReadOnly]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSee('Puoi solo guardare questo box.')
        ->assertSee('il permesso completo')
        ->assertDontSeeHtml('pb-28');
});

it('does not show the read-only notice to who can control the box', function (): void {
    $box = OnesiBox::factory()->online()->withCaregiver($this->user)->create();

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertDontSee('Puoi solo guardare questo box.')
        ->assertSeeHtml('pb-28');
});

it('shows the same colour for the status in the header as in the status card', function (): void {
    $box = OnesiBox::factory()->offline()->withCaregiver($this->user)->create();

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSeeHtml('bg-amber-50 px-2.5')
        ->assertSeeHtml('size-2 rounded-full bg-amber-500');
});

it('mounts HeroCard, BottomBar and QuickPlaySheet in the detail view', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($this->user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSeeLivewire(HeroCard::class)
        ->assertSeeLivewire(BottomBar::class)
        ->assertSeeLivewire(QuickPlaySheet::class);
});

it('offers the way back to the list only to who follows more than one box', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('canGoBackToList', false)
        ->assertDontSeeHtml('aria-label="Torna ai miei box"');

    OnesiBox::factory()->create()->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('canGoBackToList', true)
        ->assertSeeHtml('aria-label="Torna ai miei box"');
});

it('names the page after the person the box serves', function (): void {
    $box = OnesiBox::factory()->online()->forRecipient()->create(['name' => 'OnesiBox Cucina']);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSeeInOrder([$box->recipient->full_name, 'OnesiBox Cucina']);
});

it('tells the command bar when the box comes back online, even if the status card is scrolled away', function (): void {
    $box = OnesiBox::factory()->offline()->withCaregiver($this->user)->create();
    $page = Livewire::test(OnesiBoxDetail::class, ['onesiBox' => $box]);

    $box->update(['last_seen_at' => now()]);

    $page->call('refreshFromDatabase')->assertDispatched('box-state-changed');
    $page->call('refreshFromDatabase')->assertNotDispatched('box-state-changed');
});
