<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HeartbeatRequest;
use App\Http\Resources\Api\V1\HeartbeatResource;

/**
 * Handles heartbeat signals from OnesiBox appliances.
 */
class HeartbeatController extends Controller
{
    /**
     * Record a heartbeat from an OnesiBox appliance.
     *
     * POST /api/v1/appliances/heartbeat
     *
     * The OnesiBox is identified by the Sanctum token - no ID in URL needed.
     */
    public function store(HeartbeatRequest $request): HeartbeatResource
    {
        $onesiBox = $request->onesiBox();
        $validated = $request->validated();

        // Update status if provided
        if (isset($validated['status'])) {
            $onesiBox->status = $validated['status'];
        }

        // Update media info if playing
        $currentMedia = $validated['current_media'] ?? null;
        $onesiBox->current_media_url = $currentMedia['url'] ?? null;
        $onesiBox->current_media_type = $currentMedia['type'] ?? null;
        $onesiBox->current_media_title = $currentMedia['title'] ?? null;

        // Update meeting info if in call
        $currentMeeting = $validated['current_meeting'] ?? null;
        $onesiBox->current_meeting_id = $currentMeeting['meeting_id'] ?? null;

        // Update volume if provided
        if (isset($validated['volume'])) {
            $onesiBox->volume = $validated['volume'];
        }

        // Update system info if provided
        $hasSystemInfo = false;
        if (isset($validated['cpu_usage'])) {
            $onesiBox->cpu_usage = $validated['cpu_usage'];
            $hasSystemInfo = true;
        }
        if (isset($validated['memory_usage'])) {
            $onesiBox->memory_usage = $validated['memory_usage'];
            $hasSystemInfo = true;
        }
        if (isset($validated['disk_usage'])) {
            $onesiBox->disk_usage = $validated['disk_usage'];
            $hasSystemInfo = true;
        }
        if (isset($validated['temperature'])) {
            $onesiBox->temperature = $validated['temperature'];
            $hasSystemInfo = true;
        }
        if (isset($validated['uptime'])) {
            $onesiBox->uptime = $validated['uptime'];
            $hasSystemInfo = true;
        }

        // Update app version
        if (isset($validated['app_version'])) {
            $onesiBox->app_version = $validated['app_version'];
            $hasSystemInfo = true;
        }

        // Update network info
        $network = $validated['network'] ?? null;
        if ($network !== null) {
            $onesiBox->network_type = $network['type'] ?? null;
            $onesiBox->network_interface = $network['interface'] ?? null;
            $onesiBox->ip_address = $network['ip'] ?? null;
            $onesiBox->netmask = $network['netmask'] ?? null;
            $onesiBox->gateway = $network['gateway'] ?? null;
            $onesiBox->mac_address = $network['mac'] ?? null;
            $onesiBox->dns_servers = $network['dns'] ?? null;
            $hasSystemInfo = true;
        }

        // Update WiFi info
        $wifi = $validated['wifi'] ?? null;
        if ($wifi !== null) {
            $onesiBox->wifi_ssid = $wifi['ssid'] ?? null;
            $onesiBox->wifi_signal_dbm = $wifi['signal_dbm'] ?? null;
            $onesiBox->wifi_signal_percent = $wifi['signal_percent'] ?? null;
            $onesiBox->wifi_channel = $wifi['channel'] ?? null;
            $onesiBox->wifi_frequency = $wifi['frequency'] ?? null;
            $hasSystemInfo = true;
        }

        // Update detailed memory info
        $memory = $validated['memory'] ?? null;
        if ($memory !== null) {
            $onesiBox->memory_total = $memory['total'] ?? null;
            $onesiBox->memory_used = $memory['used'] ?? null;
            $onesiBox->memory_free = $memory['free'] ?? null;
            $onesiBox->memory_available = $memory['available'] ?? null;
            $onesiBox->memory_buffers = $memory['buffers'] ?? null;
            $onesiBox->memory_cached = $memory['cached'] ?? null;
            $hasSystemInfo = true;
        }

        if ($hasSystemInfo) {
            $onesiBox->last_system_info_at = \Illuminate\Support\Facades\Date::now();
        }

        $onesiBox->recordHeartbeat();

        return HeartbeatResource::success();
    }
}
