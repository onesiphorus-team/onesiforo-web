<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Pages;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Models\OnesiBox;
use BackedEnum;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Full-width diagnostics view for a single OnesiBox.
 *
 * Lives on its own tab rather than inside the edit form: the panel needs the
 * whole content width to lay out telemetry, contacts, captures and logs
 * side by side.
 */
class OnesiBoxDiagnostics extends Page
{
    use InteractsWithRecord;

    protected static string $resource = OnesiBoxResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected string $view = 'filament.onesi-boxes.diagnostics';

    public static function getNavigationLabel(): string
    {
        return __('Diagnostica');
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
        return __('Diagnostica');
    }

    public function getSubheading(): string
    {
        /** @var OnesiBox $record */
        $record = $this->getRecord();

        return __('Stato, contatti, schermate e log raccolti da :name.', ['name' => $record->name]);
    }
}
