<?php

declare(strict_types=1);

namespace App\Filament\Resources\Congregations\Pages;

use App\Filament\Resources\Congregations\CongregationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCongregations extends ListRecords
{
    protected static string $resource = CongregationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
