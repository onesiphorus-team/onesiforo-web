<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Pages;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Models\OnesiBox;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageScreenshots extends Page
{
    use InteractsWithRecord;

    protected static string $resource = OnesiBoxResource::class;

    protected string $view = 'filament.onesi-boxes.screenshots';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        /** @var OnesiBox $record */
        $record = $this->getRecord();

        return __('Diagnostica — :name', ['name' => $record->name]);
    }

    public function getHeading(): string
    {
        return __('Screenshot diagnostici');
    }
}
