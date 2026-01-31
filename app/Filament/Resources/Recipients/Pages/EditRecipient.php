<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipients\Pages;

use App\Filament\Resources\Recipients\RecipientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRecipient extends EditRecord
{
    protected static string $resource = RecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
