<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire component for displaying OnesiBox network information.
 *
 * Shows connection type, IP address, gateway, DNS, and WiFi details if applicable.
 */
class NetworkInfo extends Component
{
    #[Locked]
    public OnesiBox $onesiBox;

    /**
     * Check if network info is available.
     */
    #[Computed]
    public function hasNetworkInfo(): bool
    {
        return $this->onesiBox->network_type !== null
            || $this->onesiBox->ip_address !== null;
    }

    /**
     * Check if this is a WiFi connection.
     */
    #[Computed]
    public function isWifi(): bool
    {
        return $this->onesiBox->network_type === 'wifi';
    }

    /**
     * Get the WiFi signal strength as a descriptive label.
     */
    #[Computed]
    public function wifiSignalLabel(): ?string
    {
        $percent = $this->onesiBox->wifi_signal_percent;

        if ($percent === null) {
            return null;
        }

        return match (true) {
            $percent >= 80 => 'Eccellente',
            $percent >= 60 => 'Buono',
            $percent >= 40 => 'Discreto',
            $percent >= 20 => 'Debole',
            default => 'Molto debole',
        };
    }

    /**
     * Get the WiFi signal color class.
     */
    #[Computed]
    public function wifiSignalColor(): string
    {
        $percent = $this->onesiBox->wifi_signal_percent ?? 0;

        return match (true) {
            $percent >= 60 => 'text-green-500',
            $percent >= 40 => 'text-amber-500',
            default => 'text-red-500',
        };
    }

    /**
     * Format WiFi frequency as readable string.
     */
    #[Computed]
    public function formattedFrequency(): ?string
    {
        $freq = $this->onesiBox->wifi_frequency;

        if ($freq === null) {
            return null;
        }

        // Frequency is in MHz, return as GHz if above 1000
        if ($freq >= 1000) {
            return number_format($freq / 1000, 1).' GHz';
        }

        return $freq.' MHz';
    }

    /**
     * Get DNS servers as string.
     */
    #[Computed]
    public function dnsServersFormatted(): ?string
    {
        $dns = $this->onesiBox->dns_servers;

        if ($dns === null || $dns === []) {
            return null;
        }

        return implode(', ', $dns);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.network-info');
    }
}
