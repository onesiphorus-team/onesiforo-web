<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            // Network info
            $table->string('network_type', 10)->nullable()->after('uptime');
            $table->string('network_interface', 20)->nullable()->after('network_type');
            $table->string('ip_address', 45)->nullable()->after('network_interface');
            $table->string('netmask', 45)->nullable()->after('ip_address');
            $table->string('gateway', 45)->nullable()->after('netmask');
            $table->string('mac_address', 17)->nullable()->after('gateway');
            $table->json('dns_servers')->nullable()->after('mac_address');

            // WiFi info
            $table->string('wifi_ssid', 64)->nullable()->after('dns_servers');
            $table->smallInteger('wifi_signal_dbm')->nullable()->after('wifi_ssid');
            $table->unsignedTinyInteger('wifi_signal_percent')->nullable()->after('wifi_signal_dbm');
            $table->unsignedSmallInteger('wifi_channel')->nullable()->after('wifi_signal_percent');
            $table->unsignedInteger('wifi_frequency')->nullable()->after('wifi_channel');

            // Detailed memory (bytes)
            $table->unsignedBigInteger('memory_total')->nullable()->after('wifi_frequency');
            $table->unsignedBigInteger('memory_used')->nullable()->after('memory_total');
            $table->unsignedBigInteger('memory_free')->nullable()->after('memory_used');
            $table->unsignedBigInteger('memory_available')->nullable()->after('memory_free');
            $table->unsignedBigInteger('memory_buffers')->nullable()->after('memory_available');
            $table->unsignedBigInteger('memory_cached')->nullable()->after('memory_buffers');

            // App version
            $table->string('app_version', 20)->nullable()->after('memory_cached');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            $table->dropColumn([
                'network_type',
                'network_interface',
                'ip_address',
                'netmask',
                'gateway',
                'mac_address',
                'dns_servers',
                'wifi_ssid',
                'wifi_signal_dbm',
                'wifi_signal_percent',
                'wifi_channel',
                'wifi_frequency',
                'memory_total',
                'memory_used',
                'memory_free',
                'memory_available',
                'memory_buffers',
                'memory_cached',
                'app_version',
            ]);
        });
    }
};
