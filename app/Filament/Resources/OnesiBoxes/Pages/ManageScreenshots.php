<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Pages;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Models\OnesiBox;
use Filament\Resources\Pages\Page;

class ManageScreenshots extends Page
{
    public OnesiBox $record;

    protected static string $resource = OnesiBoxResource::class;

    protected string $view = 'filament.onesi-boxes.screenshots';

    public function mount(int|string $record): void
    {
        $this->record = OnesiBox::findOrFail($record);
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
