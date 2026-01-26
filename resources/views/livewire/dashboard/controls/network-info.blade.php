<div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-6">
    <div class="flex items-center gap-2 mb-4">
        <flux:icon name="{{ $this->isWifi ? 'wifi' : 'globe-alt' }}" class="w-5 h-5 text-zinc-500 dark:text-zinc-400" />
        <flux:heading size="lg">Connessione di Rete</flux:heading>
    </div>

    @if(!$this->hasNetworkInfo)
        <div class="text-center py-6 text-zinc-500 dark:text-zinc-400">
            <flux:icon name="signal-slash" class="w-10 h-10 mx-auto mb-2 opacity-50" />
            <p class="text-sm">Nessuna informazione di rete disponibile</p>
        </div>
    @else
        <div class="space-y-4">
            {{-- Connection Type --}}
            <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Tipo connessione</span>
                <div class="flex items-center gap-2">
                    <flux:icon name="{{ $this->isWifi ? 'wifi' : 'globe-alt' }}" class="w-4 h-4 text-zinc-500" />
                    <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                        {{ $this->isWifi ? 'WiFi' : 'Ethernet' }}
                    </span>
                </div>
            </div>

            {{-- IP Address --}}
            @if($onesiBox->ip_address)
                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Indirizzo IP</span>
                    <span class="text-sm font-mono font-semibold text-zinc-700 dark:text-zinc-200">
                        {{ $onesiBox->ip_address }}
                    </span>
                </div>
            @endif

            {{-- Interface --}}
            @if($onesiBox->network_interface)
                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Interfaccia</span>
                    <span class="text-sm font-mono text-zinc-700 dark:text-zinc-200">
                        {{ $onesiBox->network_interface }}
                    </span>
                </div>
            @endif

            {{-- Gateway --}}
            @if($onesiBox->gateway)
                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Gateway</span>
                    <span class="text-sm font-mono text-zinc-700 dark:text-zinc-200">
                        {{ $onesiBox->gateway }}
                    </span>
                </div>
            @endif

            {{-- DNS --}}
            @if($this->dnsServersFormatted)
                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">DNS</span>
                    <span class="text-sm font-mono text-zinc-700 dark:text-zinc-200">
                        {{ $this->dnsServersFormatted }}
                    </span>
                </div>
            @endif

            {{-- MAC Address --}}
            @if($onesiBox->mac_address)
                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">MAC</span>
                    <span class="text-sm font-mono text-zinc-700 dark:text-zinc-200">
                        {{ $onesiBox->mac_address }}
                    </span>
                </div>
            @endif

            {{-- WiFi Details --}}
            @if($this->isWifi)
                <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center gap-2 mb-3">
                        <flux:icon name="wifi" class="w-4 h-4 text-zinc-500" />
                        <span class="text-sm font-semibold text-zinc-600 dark:text-zinc-300">Dettagli WiFi</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- SSID --}}
                        @if($onesiBox->wifi_ssid)
                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                                <span class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Rete</span>
                                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                                    {{ $onesiBox->wifi_ssid }}
                                </span>
                            </div>
                        @endif

                        {{-- Signal Strength --}}
                        @if($onesiBox->wifi_signal_percent !== null)
                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                                <span class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Segnale</span>
                                <div class="flex items-center gap-2">
                                    {{-- Signal bars visualization --}}
                                    <div class="flex items-end gap-0.5 h-4">
                                        @php
                                            $percent = $onesiBox->wifi_signal_percent;
                                            $bars = [20, 40, 60, 80];
                                        @endphp
                                        @foreach($bars as $threshold)
                                            <div class="w-1 rounded-sm {{ $percent >= $threshold ? ($percent >= 60 ? 'bg-green-500' : ($percent >= 40 ? 'bg-amber-500' : 'bg-red-500')) : 'bg-zinc-300 dark:bg-zinc-600' }}"
                                                 style="height: {{ ($loop->index + 1) * 25 }}%"></div>
                                        @endforeach
                                    </div>
                                    <span class="text-sm font-semibold {{ $this->wifiSignalColor }}">
                                        {{ $onesiBox->wifi_signal_percent }}%
                                    </span>
                                    <span class="text-xs text-zinc-500">
                                        ({{ $this->wifiSignalLabel }})
                                    </span>
                                </div>
                                @if($onesiBox->wifi_signal_dbm)
                                    <span class="text-xs text-zinc-400 mt-1">
                                        {{ $onesiBox->wifi_signal_dbm }} dBm
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Channel --}}
                        @if($onesiBox->wifi_channel)
                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                                <span class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Canale</span>
                                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                                    {{ $onesiBox->wifi_channel }}
                                </span>
                            </div>
                        @endif

                        {{-- Frequency --}}
                        @if($this->formattedFrequency)
                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                                <span class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Frequenza</span>
                                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                                    {{ $this->formattedFrequency }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
