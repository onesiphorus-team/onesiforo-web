<?php

declare(strict_types=1);

namespace App\Filament\Resources\Congregations\Pages;

use App\Filament\Resources\Congregations\CongregationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCongregation extends CreateRecord
{
    protected static string $resource = CongregationResource::class;
}
