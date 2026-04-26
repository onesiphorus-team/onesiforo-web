<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Models\OnesiBox;
use App\Services\ActivityTimelineAggregator;
use App\Support\ActivityTimelineEntry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Collection<int, ActivityTimelineEntry> $entries
 */
class ActivityTimeline extends Component
{
    #[Locked]
    public OnesiBox $onesiBox;

    public function mount(OnesiBox $onesiBox): void
    {
        Gate::authorize('view', $onesiBox);

        $this->onesiBox = $onesiBox;
    }

    /**
     * @return Collection<int, ActivityTimelineEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        $timezone = $this->displayTimezone();
        $from = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $to = CarbonImmutable::now()->utc();

        return resolve(ActivityTimelineAggregator::class)->forBox($this->onesiBox, $from, $to);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.activity-timeline', [
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    private function displayTimezone(): string
    {
        return config('app.display_timezone', 'Europe/Rome');
    }
}
