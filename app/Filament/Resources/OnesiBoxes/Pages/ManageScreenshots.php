<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Pages;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Models\OnesiBox;
use BackedEnum;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ManageScreenshots extends Page
{
    use InteractsWithRecord;

    protected static string $resource = OnesiBoxResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected string $view = 'filament.onesi-boxes.screenshots';

    public static function getNavigationLabel(): string
    {
        return __('Diagnostica schermo');
    }

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
