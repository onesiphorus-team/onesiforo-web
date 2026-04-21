<?php

declare(strict_types=1);

namespace App\Filament\Resources\Congregations\Pages;

use App\Filament\Resources\Congregations\CongregationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCongregation extends EditRecord
{
    protected static string $resource = CongregationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
