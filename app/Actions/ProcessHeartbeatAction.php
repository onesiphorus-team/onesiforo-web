<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\OnesiBox;
use Illuminate\Support\Facades\Date;

/**
 * Processes heartbeat data from an OnesiBox appliance.
 *
 * This action encapsulates the logic for updating an OnesiBox model
 * with heartbeat data including status, media info, system metrics,
 * network info, and WiFi details.
 */
class ProcessHeartbeatAction
{
    /**
     * Process heartbeat data and update the OnesiBox model.
     *
     * @param  OnesiBox  $onesiBox  The OnesiBox to update
     * @param  array<string, mixed>  $data  Validated heartbeat data
     */
    public function __invoke(OnesiBox $onesiBox, array $data): void
    {
        $this->updateStatus($onesiBox, $data);
        $this->updateMediaInfo($onesiBox, $data);
        $this->updateMeetingInfo($onesiBox, $data);
        $this->updateVolume($onesiBox, $data);

        $hasSystemInfo = $this->updateSystemMetrics($onesiBox, $data);
        $hasSystemInfo = $this->updateNetworkInfo($onesiBox, $data) || $hasSystemInfo;
        $hasSystemInfo = $this->updateWifiInfo($onesiBox, $data) || $hasSystemInfo;
        $hasSystemInfo = $this->updateMemoryInfo($onesiBox, $data) || $hasSystemInfo;

        if ($hasSystemInfo) {
            $onesiBox->last_system_info_at = Date::now();
        }

        $onesiBox->recordHeartbeat();
    }

    /**
     * Update device status.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateStatus(OnesiBox $onesiBox, array $data): void
    {
        if (isset($data['status'])) {
            $onesiBox->status = $data['status'];
        }
    }

    /**
     * Update current media information.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateMediaInfo(OnesiBox $onesiBox, array $data): void
    {
        $currentMedia = $data['current_media'] ?? null;
        $onesiBox->current_media_url = $currentMedia['url'] ?? null;
        $onesiBox->current_media_type = $currentMedia['type'] ?? null;
        $onesiBox->current_media_title = $currentMedia['title'] ?? null;
    }

    /**
     * Update current meeting information.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateMeetingInfo(OnesiBox $onesiBox, array $data): void
    {
        $currentMeeting = $data['current_meeting'] ?? null;
        $onesiBox->current_meeting_id = $currentMeeting['meeting_id'] ?? null;
    }

    /**
     * Update volume level.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateVolume(OnesiBox $onesiBox, array $data): void
    {
        if (isset($data['volume'])) {
            $onesiBox->volume = $data['volume'];
        }
    }

    /**
     * Update system metrics (CPU, memory, disk, temperature, uptime, app version).
     *
     * @param  array<string, mixed>  $data
     * @return bool True if any system info was updated
     */
    private function updateSystemMetrics(OnesiBox $onesiBox, array $data): bool
    {
        $hasSystemInfo = false;

        if (isset($data['cpu_usage'])) {
            $onesiBox->cpu_usage = $data['cpu_usage'];
            $hasSystemInfo = true;
        }
        if (isset($data['memory_usage'])) {
            $onesiBox->memory_usage = $data['memory_usage'];
            $hasSystemInfo = true;
        }
        if (isset($data['disk_usage'])) {
            $onesiBox->disk_usage = $data['disk_usage'];
            $hasSystemInfo = true;
        }
        if (isset($data['temperature'])) {
            $onesiBox->temperature = $data['temperature'];
            $hasSystemInfo = true;
        }
        if (isset($data['uptime'])) {
            $onesiBox->uptime = $data['uptime'];
            $hasSystemInfo = true;
        }
        if (isset($data['app_version'])) {
            $onesiBox->app_version = $data['app_version'];
            $hasSystemInfo = true;
        }

        return $hasSystemInfo;
    }

    /**
     * Update network information.
     *
     * @param  array<string, mixed>  $data
     * @return bool True if network info was updated
     */
    private function updateNetworkInfo(OnesiBox $onesiBox, array $data): bool
    {
        $network = $data['network'] ?? null;

        if ($network === null) {
            return false;
        }

        $onesiBox->network_type = $network['type'] ?? null;
        $onesiBox->network_interface = $network['interface'] ?? null;
        $onesiBox->ip_address = $network['ip'] ?? null;
        $onesiBox->netmask = $network['netmask'] ?? null;
        $onesiBox->gateway = $network['gateway'] ?? null;
        $onesiBox->mac_address = $network['mac'] ?? null;
        $onesiBox->dns_servers = $network['dns'] ?? null;

        return true;
    }

    /**
     * Update WiFi information.
     *
     * @param  array<string, mixed>  $data
     * @return bool True if WiFi info was updated
     */
    private function updateWifiInfo(OnesiBox $onesiBox, array $data): bool
    {
        $wifi = $data['wifi'] ?? null;

        if ($wifi === null) {
            return false;
        }

        $onesiBox->wifi_ssid = $wifi['ssid'] ?? null;
        $onesiBox->wifi_signal_dbm = $wifi['signal_dbm'] ?? null;
        $onesiBox->wifi_signal_percent = $wifi['signal_percent'] ?? null;
        $onesiBox->wifi_channel = $wifi['channel'] ?? null;
        $onesiBox->wifi_frequency = $wifi['frequency'] ?? null;

        return true;
    }

    /**
     * Update detailed memory information.
     *
     * @param  array<string, mixed>  $data
     * @return bool True if memory info was updated
     */
    private function updateMemoryInfo(OnesiBox $onesiBox, array $data): bool
    {
        $memory = $data['memory'] ?? null;

        if ($memory === null) {
            return false;
        }

        $onesiBox->memory_total = $memory['total'] ?? null;
        $onesiBox->memory_used = $memory['used'] ?? null;
        $onesiBox->memory_free = $memory['free'] ?? null;
        $onesiBox->memory_available = $memory['available'] ?? null;
        $onesiBox->memory_buffers = $memory['buffers'] ?? null;
        $onesiBox->memory_cached = $memory['cached'] ?? null;

        return true;
    }
}
