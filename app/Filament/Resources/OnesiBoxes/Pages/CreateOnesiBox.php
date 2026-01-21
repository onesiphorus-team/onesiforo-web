<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Pages;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use App\Models\OnesiBox;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOnesiBox extends CreateRecord
{
    protected static string $resource = OnesiBoxResource::class;

    protected function afterCreate(): void
    {
        /** @var OnesiBox $record */
        $record = $this->record;

        $token = $record->createToken('onesibox-api-token');

        activity()
            ->performedOn($record)
            ->causedBy(auth()->user())
            ->log('OnesiBox created with API token');

        Notification::make()
            ->title(__('OnesiBox created'))
            ->body(__('API Token generated: :token', ['token' => $token->plainTextToken]))
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
