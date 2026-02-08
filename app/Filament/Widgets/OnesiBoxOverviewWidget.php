<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\OnesiBox;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class OnesiBoxOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.onesi-box-overview-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, OnesiBox>
     */
    protected function getBoxes(): Collection
    {
        return OnesiBox::query()
            ->with('recipient')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'boxes' => $this->getBoxes(),
        ];
    }
}
