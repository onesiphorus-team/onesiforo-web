<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CommandStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('In attesa'),
            self::Completed => __('Completato'),
            self::Failed => __('Fallito'),
            self::Expired => __('Scaduto'),
            self::Cancelled => __('Annullato'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Completed => 'heroicon-o-check-circle',
            self::Failed => 'heroicon-o-x-circle',
            self::Expired => 'heroicon-o-exclamation-triangle',
            self::Cancelled => 'heroicon-o-x-mark',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Expired => 'gray',
            self::Cancelled => 'zinc',
        };
    }

    /**
     * Check if the command is still processable.
     */
    public function isProcessable(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the command has been processed.
     */
    public function isProcessed(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Expired, self::Cancelled], true);
    }

    /**
     * Check if the command can be cancelled.
     */
    public function isCancellable(): bool
    {
        return $this === self::Pending;
    }
}
