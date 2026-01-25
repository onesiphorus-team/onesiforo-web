<div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <flux:icon name="cpu-chip" class="w-5 h-5 text-zinc-500 dark:text-zinc-400" />
            <flux:heading size="lg">Informazioni di Sistema</flux:heading>
        </div>

        @if($this->canControl && $this->isOnline)
            <flux:button
                wire:click="requestRefresh"
                wire:loading.attr="disabled"
                variant="subtle"
                size="sm"
                icon="arrow-path"
            >
                <span wire:loading.remove wire:target="requestRefresh">Aggiorna</span>
                <span wire:loading wire:target="requestRefresh">Aggiornamento...</span>
            </flux:button>
        @endif
    </div>

    @if(!$this->isOnline)
        <flux:callout icon="wifi" class="mb-4">
            <flux:callout.text>
                Le informazioni di sistema sono disponibili solo quando il dispositivo è online.
            </flux:callout.text>
        </flux:callout>
    @endif

    @if(!$this->hasSystemInfo)
        <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
            <flux:icon name="information-circle" class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>Nessuna informazione di sistema disponibile</p>
            @if($this->canControl && $this->isOnline)
                <p class="text-sm mt-2">Clicca "Aggiorna" per richiedere i dati.</p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- CPU Usage --}}
            @if($onesiBox->cpu_usage !== null)
                <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">CPU</span>
                        <span class="text-sm font-semibold {{ $onesiBox->cpu_usage > 80 ? 'text-red-500' : ($onesiBox->cpu_usage > 60 ? 'text-amber-500' : 'text-green-500') }}">
                            {{ $onesiBox->cpu_usage }}%
                        </span>
                    </div>
                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                        <div
                            class="h-2 rounded-full {{ $onesiBox->cpu_usage > 80 ? 'bg-red-500' : ($onesiBox->cpu_usage > 60 ? 'bg-amber-500' : 'bg-green-500') }}"
                            style="width: {{ $onesiBox->cpu_usage }}%"
                        ></div>
                    </div>
                </div>
            @endif

            {{-- Memory Usage --}}
            @if($onesiBox->memory_usage !== null)
                <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg {{ $this->hasDetailedMemory ? 'sm:col-span-2' : '' }}">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Memoria</span>
                        <span class="text-sm font-semibold {{ $onesiBox->memory_usage > 80 ? 'text-red-500' : ($onesiBox->memory_usage > 60 ? 'text-amber-500' : 'text-green-500') }}">
                            {{ $onesiBox->memory_usage }}%
                        </span>
                    </div>

                    @if($this->hasDetailedMemory)
                        {{-- Detailed Memory Bar with breakdown --}}
                        @php
                            $breakdown = $this->memoryBreakdown;
                        @endphp
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3 flex overflow-hidden">
                            <div class="h-3 bg-red-400" style="width: {{ $breakdown['used'] }}%" title="Usata"></div>
                            <div class="h-3 bg-amber-400" style="width: {{ $breakdown['buffers'] }}%" title="Buffers"></div>
                            <div class="h-3 bg-blue-400" style="width: {{ $breakdown['cached'] }}%" title="Cache"></div>
                        </div>

                        {{-- Legend --}}
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs">
                            <div class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                <span class="text-zinc-500 dark:text-zinc-400">
                                    Usata: {{ $this->formatBytes($onesiBox->memory_used - ($onesiBox->memory_buffers ?? 0) - ($onesiBox->memory_cached ?? 0)) }}
                                </span>
                            </div>
                            @if($onesiBox->memory_buffers)
                                <div class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                    <span class="text-zinc-500 dark:text-zinc-400">
                                        Buffers: {{ $this->formatBytes($onesiBox->memory_buffers) }}
                                    </span>
                                </div>
                            @endif
                            @if($onesiBox->memory_cached)
                                <div class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                                    <span class="text-zinc-500 dark:text-zinc-400">
                                        Cache: {{ $this->formatBytes($onesiBox->memory_cached) }}
                                    </span>
                                </div>
                            @endif
                            <div class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                <span class="text-zinc-500 dark:text-zinc-400">
                                    Libera: {{ $this->formatBytes($onesiBox->memory_free) }}
                                </span>
                            </div>
                        </div>

                        {{-- Total / Available --}}
                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            Totale: {{ $this->formatBytes($onesiBox->memory_total) }}
                            @if($onesiBox->memory_available)
                                | Disponibile: {{ $this->formatBytes($onesiBox->memory_available) }}
                            @endif
                        </div>
                    @else
                        {{-- Simple progress bar --}}
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                            <div
                                class="h-2 rounded-full {{ $onesiBox->memory_usage > 80 ? 'bg-red-500' : ($onesiBox->memory_usage > 60 ? 'bg-amber-500' : 'bg-green-500') }}"
                                style="width: {{ $onesiBox->memory_usage }}%"
                            ></div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Disk Usage --}}
            @if($onesiBox->disk_usage !== null)
                <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Disco</span>
                        <span class="text-sm font-semibold {{ $onesiBox->disk_usage > 80 ? 'text-red-500' : ($onesiBox->disk_usage > 60 ? 'text-amber-500' : 'text-green-500') }}">
                            {{ $onesiBox->disk_usage }}%
                        </span>
                    </div>
                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                        <div
                            class="h-2 rounded-full {{ $onesiBox->disk_usage > 80 ? 'bg-red-500' : ($onesiBox->disk_usage > 60 ? 'bg-amber-500' : 'bg-green-500') }}"
                            style="width: {{ $onesiBox->disk_usage }}%"
                        ></div>
                    </div>
                </div>
            @endif

            {{-- Temperature --}}
            @if($onesiBox->temperature !== null)
                <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Temperatura</span>
                        <span class="text-sm font-semibold {{ $onesiBox->temperature > 70 ? 'text-red-500' : ($onesiBox->temperature > 55 ? 'text-amber-500' : 'text-green-500') }}">
                            {{ number_format($onesiBox->temperature, 1) }}°C
                        </span>
                    </div>
                </div>
            @endif

            {{-- Uptime --}}
            @if($this->formattedUptime)
                <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Uptime</span>
                        <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                            {{ $this->formattedUptime }}
                        </span>
                    </div>
                </div>
            @endif
        </div>

        {{-- Last Updated --}}
        @if($onesiBox->last_system_info_at)
            <div class="mt-4 text-center text-xs text-zinc-500 dark:text-zinc-400">
                Ultimo aggiornamento: {{ $onesiBox->last_system_info_at->diffForHumans() }}
            </div>
        @endif
    @endif
</div>
