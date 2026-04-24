<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Pages;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Models\OnesiBox;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditOnesiBox extends EditRecord
{
    protected static string $resource = OnesiBoxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('screenshots')
                ->label(__('Diagnostica schermo'))
                ->icon('heroicon-o-camera')
                ->url(fn (OnesiBox $record): string => OnesiBoxResource::getUrl('screenshots', ['record' => $record])),
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
