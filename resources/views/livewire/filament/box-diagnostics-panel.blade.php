@php
    use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;

    $box = $this->record;

    $metrics = [
        ['label' => 'CPU', 'value' => $box->cpu_usage !== null ? $box->cpu_usage.'%' : null, 'percent' => $box->cpu_usage, 'tone' => $box->cpu_usage > 80 ? 'danger' : ($box->cpu_usage > 60 ? 'warning' : 'normal')],
        ['label' => 'RAM', 'value' => $box->memory_usage !== null ? $box->memory_usage.'%' : null, 'percent' => $box->memory_usage, 'tone' => $box->memory_usage > 80 ? 'danger' : ($box->memory_usage > 60 ? 'warning' : 'normal')],
        ['label' => 'Disco', 'value' => $box->disk_usage !== null ? $box->disk_usage.'%' : null, 'percent' => $box->disk_usage, 'tone' => $box->disk_usage > 90 ? 'danger' : ($box->disk_usage > 75 ? 'warning' : 'normal')],
        ['label' => 'Temperatura', 'value' => $box->temperature !== null ? number_format($box->temperature, 1).'°C' : null, 'percent' => null, 'tone' => $box->temperature > 70 ? 'danger' : ($box->temperature > 55 ? 'warning' : 'normal')],
    ];

    $identity = array_filter([
        'Numero seriale' => $box->serial_number,
        'Versione app' => $box->app_version,
        'Versione firmware' => $box->firmware_version,
        'Volume' => $box->volume !== null ? $box->volume.'%' : null,
        'Uptime' => $this->formattedUptime,
        'Media in corso' => $box->current_media_title ?? $box->current_media_url,
        'Riunione in corso' => $box->current_meeting_id,
    ], fn ($value) => filled($value));

    $network = array_filter([
        'Indirizzo IP' => $box->ip_address,
        'Interfaccia' => $box->network_interface,
        'Tipo' => $box->network_type,
        'MAC' => $box->mac_address,
        'Gateway' => $box->gateway,
        'Netmask' => $box->netmask,
        'DNS' => is_array($box->dns_servers) ? implode(', ', $box->dns_servers) : null,
        'SSID Wi-Fi' => $box->wifi_ssid,
        'Segnale Wi-Fi' => $box->wifi_signal_percent !== null ? $box->wifi_signal_percent.'%'.($box->wifi_signal_dbm !== null ? " ({$box->wifi_signal_dbm} dBm)" : '') : null,
        'Canale Wi-Fi' => $box->wifi_channel,
    ], fn ($value) => filled($value));
@endphp

<div class="space-y-6" wire:poll.30s="refreshDiagnostics">
    {{-- ═══ INTESTAZIONE: stato + azioni ═══ --}}
    <x-filament::section>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            @if ($this->isOnline)
                <x-filament::badge color="success" icon="heroicon-o-signal" size="lg">Online</x-filament::badge>
            @elseif ($box->last_seen_at)
                <x-filament::badge color="gray" icon="heroicon-o-signal-slash" size="lg">Offline</x-filament::badge>
            @else
                <x-filament::badge color="warning" icon="heroicon-o-question-mark-circle" size="lg">Mai connesso</x-filament::badge>
            @endif

            @if ($box->status)
                <x-filament::badge :color="$box->status->getColor()" :icon="$box->status->getIcon()" size="lg">
                    {{ $box->status->getLabel() }}
                </x-filament::badge>
            @endif

            @if ($box->recipient)
                <x-filament::badge color="gray" icon="heroicon-o-user" size="lg">
                    {{ $box->recipient->full_name }}
                </x-filament::badge>
            @endif

            @if ($this->pendingCommandsCount > 0)
                <x-filament::badge color="info" icon="heroicon-o-queue-list" size="lg">
                    {{ $this->pendingCommandsCount }} {{ $this->pendingCommandsCount === 1 ? 'comando' : 'comandi' }} in coda
                </x-filament::badge>
            @endif

            <div class="ms-auto flex flex-wrap items-center gap-2">
                <x-filament::button
                    wire:click="requestLogs"
                    wire:loading.attr="disabled"
                    wire:target="requestLogs"
                    :color="$this->isOnline ? 'primary' : 'gray'"
                    icon="heroicon-o-arrow-down-tray"
                    size="sm"
                    :disabled="! $this->isOnline"
                    :tooltip="$this->isOnline ? null : __('Il dispositivo è offline')"
                >
                    Richiedi log ora
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="OnesiBoxResource::getUrl('screenshots', ['record' => $box])"
                    color="gray"
                    icon="heroicon-o-camera"
                    size="sm"
                >
                    Diagnostica schermo
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>

    {{-- ═══ TELEMETRIA ═══ --}}
    <x-filament::section
        icon="heroicon-o-cpu-chip"
        :heading="__('Telemetria')"
        :description="$box->last_system_info_at ? 'Aggiornata '.$box->last_system_info_at->diffForHumans() : null"
    >
        @if ($box->last_system_info_at)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($metrics as $metric)
                    <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{ $metric['label'] }}
                        </p>
                        <p @class([
                            'mt-1 text-2xl font-bold tabular-nums',
                            'text-danger-600 dark:text-danger-400' => $metric['tone'] === 'danger',
                            'text-warning-600 dark:text-warning-400' => $metric['tone'] === 'warning',
                            'text-gray-950 dark:text-white' => $metric['tone'] === 'normal',
                        ])>
                            {{ $metric['value'] ?? '—' }}
                        </p>
                        @if ($metric['percent'] !== null)
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                <div @class([
                                        'h-full rounded-full',
                                        'bg-danger-500' => $metric['tone'] === 'danger',
                                        'bg-warning-500' => $metric['tone'] === 'warning',
                                        'bg-success-500' => $metric['tone'] === 'normal',
                                    ])
                                    style="width: {{ min(100, max(0, $metric['percent'])) }}%"
                                ></div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nessuna telemetria ricevuta da questo dispositivo.
            </p>
        @endif
    </x-filament::section>

    {{-- ═══ ULTIMI CONTATTI + ULTIMI COMANDI ═══ --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-filament::section icon="heroicon-o-clock" :heading="__('Ultimi contatti')">
            <dl class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($this->contacts as $contact)
                    <div class="flex items-start justify-between gap-4 py-2 first:pt-0 last:pb-0">
                        <dt class="min-w-0">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $contact['label'] }}</span>
                            @if ($contact['hint'])
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $contact['hint'] }}</span>
                            @endif
                        </dt>
                        <dd class="shrink-0 text-sm tabular-nums">
                            @if ($contact['at'])
                                <span
                                    class="text-gray-950 dark:text-white"
                                    title="{{ $contact['at']->format('d/m/Y H:i:s') }}"
                                >{{ $contact['at']->diffForHumans() }}</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">mai</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-queue-list" :heading="__('Ultimi comandi')">
            @if ($this->recentCommands->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Nessun comando inviato a questo dispositivo.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->recentCommands as $command)
                        <li class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0" wire:key="diag-cmd-{{ $command->id }}">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-filament::badge :color="$command->status->getColor()" size="sm">
                                    {{ $command->status->getLabel() }}
                                </x-filament::badge>
                                <span class="truncate text-sm text-gray-950 dark:text-white">{{ $command->type->getLabel() }}</span>
                            </span>
                            <span
                                class="shrink-0 text-xs text-gray-500 dark:text-gray-400"
                                title="{{ ($command->executed_at ?? $command->created_at)?->format('d/m/Y H:i:s') }}"
                            >
                                {{ ($command->executed_at ?? $command->created_at)?->diffForHumans() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>

    {{-- ═══ DISPOSITIVO + RETE ═══ --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-filament::section icon="heroicon-o-tv" :heading="__('Dispositivo')">
            @if ($identity === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">Nessun dato disponibile.</p>
            @else
                <dl class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($identity as $label => $value)
                        <div class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0">
                            <dt class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="truncate text-sm font-medium text-gray-950 dark:text-white" title="{{ $value }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-filament::section>

        <x-filament::section icon="heroicon-o-wifi" :heading="__('Rete')">
            @if ($network === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">Nessun dato di rete ricevuto.</p>
            @else
                <dl class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($network as $label => $value)
                        <div class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0">
                            <dt class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="truncate font-mono text-sm text-gray-950 dark:text-white" title="{{ $value }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-filament::section>
    </div>

    {{-- ═══ ULTIME SCHERMATE ═══ --}}
    <x-filament::section icon="heroicon-o-photo" :heading="__('Ultime schermate')">
        <x-slot name="afterHeader">
            @if ($this->screenshots->isNotEmpty())
                <x-filament::link
                    :href="OnesiBoxResource::getUrl('screenshots', ['record' => $box])"
                    size="sm"
                >
                    Vedi tutte
                </x-filament::link>
            @endif
        </x-slot>

        @if ($this->screenshots->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nessuna schermata acquisita.
                @unless ($box->screenshot_enabled)
                    La diagnostica schermo è disattivata per questo dispositivo.
                @endunless
            </p>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($this->screenshots as $screenshot)
                    <a
                        href="{{ $screenshot->signedUrl(10) }}"
                        target="_blank"
                        rel="noopener"
                        wire:key="diag-shot-{{ $screenshot->id }}"
                        class="group block"
                        title="{{ $screenshot->captured_at->format('d/m/Y H:i:s') }}"
                    >
                        <div class="aspect-video overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/5 transition group-hover:ring-primary-500 dark:bg-white/5 dark:ring-white/10">
                            <img
                                src="{{ $screenshot->signedUrl() }}"
                                alt="Schermata del {{ $screenshot->captured_at->format('d/m/Y H:i:s') }}"
                                loading="lazy"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <span class="mt-1 block truncate text-center text-xs text-gray-500 dark:text-gray-400">
                            {{ $screenshot->captured_at->diffForHumans() }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- ═══ LOG ONESIBOX ═══ --}}
    <x-filament::section
        icon="heroicon-o-document-text"
        :heading="__('Log OnesiBox')"
        :description="$box->log_collection_enabled
            ? 'Raccolta periodica attiva ogni '.$box->log_collection_interval_minutes.' minuti ('.$box->log_collection_lines.' righe per acquisizione).'
            : 'Raccolta periodica disattivata: i log arrivano solo su richiesta manuale.'"
    >
        <x-slot name="afterHeader">
            @if ($this->hasPendingLogRequest)
                <span class="text-xs text-gray-500 dark:text-gray-400">Richiesta in attesa di risposta…</span>
            @endif
        </x-slot>

        @if ($this->logSnapshots->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nessun log ancora raccolto da questo dispositivo.
            </p>
        @else
            @php($selected = $this->selectedSnapshot)
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                {{-- Elenco acquisizioni --}}
                <ul class="space-y-1 lg:col-span-1">
                    @foreach ($this->logSnapshots as $snapshot)
                        <li wire:key="diag-log-{{ $snapshot->id }}">
                            <button
                                type="button"
                                wire:click="selectSnapshot({{ $snapshot->id }})"
                                @class([
                                    'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-start text-sm transition',
                                    'bg-primary-50 text-primary-700 ring-1 ring-primary-500 dark:bg-primary-500/10 dark:text-primary-400' => $selected?->id === $snapshot->id,
                                    'hover:bg-gray-50 dark:hover:bg-white/5' => $selected?->id !== $snapshot->id,
                                ])
                            >
                                <span class="min-w-0">
                                    <span class="block font-medium tabular-nums">{{ $snapshot->captured_at->format('d/m H:i') }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $snapshot->source->getLabel() }}</span>
                                </span>
                                @if ($snapshot->hasError())
                                    <x-filament::icon
                                        icon="heroicon-o-exclamation-triangle"
                                        class="h-4 w-4 shrink-0 text-danger-500"
                                    />
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                {{-- Contenuto acquisizione --}}
                <div class="lg:col-span-3">
                    @if ($selected)
                        <div class="mb-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span class="tabular-nums">{{ $selected->captured_at->format('d/m/Y H:i:s') }}</span>
                            <span>·</span>
                            <span>{{ $selected->source->getLabel() }}</span>
                            @if ($selected->log_file)
                                <span>·</span>
                                <span class="font-mono">{{ $selected->log_file }}</span>
                            @endif
                            @if ($selected->total_lines !== null)
                                <span>·</span>
                                <span>{{ $selected->returned_lines }} / {{ $selected->total_lines }} righe</span>
                            @endif
                        </div>

                        @if ($selected->hasError())
                            <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                                {{ $selected->error_message }}
                            </div>
                        @elseif ($selected->lines === [])
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Acquisizione vuota: il file di log non conteneva righe.
                            </p>
                        @else
                            <pre class="max-h-[28rem] overflow-auto rounded-lg bg-gray-950 p-4 font-mono text-xs leading-relaxed text-gray-100 dark:bg-black">{{ $selected->linesAsText() }}</pre>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </x-filament::section>
</div>
