<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Pages;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Models\OnesiBox;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditOnesiBox extends EditRecord
{
    protected static string $resource = OnesiBoxResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    public static function getNavigationLabel(): string
    {
        return __('Configurazione');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('diagnostics')
                ->label(__('Diagnostica'))
                ->icon('heroicon-o-wrench-screwdriver')
                ->url(fn (OnesiBox $record): string => OnesiBoxResource::getUrl('diagnostics', ['record' => $record])),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
