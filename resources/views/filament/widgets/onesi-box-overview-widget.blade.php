<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($boxes as $box)
            <div class="relative rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{-- Header: Name + Online Status --}}
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <x-filament::icon
                            icon="heroicon-o-tv"
                            class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                        />
                        <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $box->name }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if ($box->isOnline())
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            </span>
                            <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Online</span>
                        @elseif ($box->last_seen_at)
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                            </span>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Offline</span>
                        @else
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                            </span>
                            <span class="text-xs font-medium text-amber-600 dark:text-amber-400">Mai connesso</span>
                        @endif
                    </div>
                </div>

                {{-- Status Badge --}}
                <div class="mt-3 flex items-center gap-2">
                    <x-filament::badge
                        :color="$box->status->getColor()"
                        :icon="$box->status->getIcon()"
                        size="sm"
                    >
                        {{ $box->status->getLabel() }}
                    </x-filament::badge>

                    @if ($box->recipient)
                        <x-filament::badge color="gray" icon="heroicon-o-user" size="sm">
                            {{ $box->recipient->full_name }}
                        </x-filament::badge>
                    @endif
                </div>

                {{-- System Info --}}
                @if ($box->last_system_info_at)
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        {{-- CPU --}}
                        <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-white/5">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">CPU</p>
                            <p class="text-sm font-semibold @if($box->cpu_usage !== null && $box->cpu_usage > 80) text-danger-600 dark:text-danger-400 @else text-gray-950 dark:text-white @endif">
                                {{ $box->cpu_usage !== null ? $box->cpu_usage . '%' : '—' }}
                            </p>
                        </div>
                        {{-- Memory --}}
                        <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-white/5">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">RAM</p>
                            <p class="text-sm font-semibold @if($box->memory_usage !== null && $box->memory_usage > 80) text-danger-600 dark:text-danger-400 @else text-gray-950 dark:text-white @endif">
                                {{ $box->memory_usage !== null ? $box->memory_usage . '%' : '—' }}
                            </p>
                        </div>
                        {{-- Temperature --}}
                        <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-white/5">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Temp</p>
                            <p class="text-sm font-semibold @if($box->temperature !== null && $box->temperature > 70) text-danger-600 dark:text-danger-400 @elseif($box->temperature !== null && $box->temperature > 55) text-warning-600 dark:text-warning-400 @else text-gray-950 dark:text-white @endif">
                                {{ $box->temperature !== null ? number_format($box->temperature, 1) . '°C' : '—' }}
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Footer: Serial + Last Seen --}}
                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2.5 dark:border-white/5">
                    <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ $box->serial_number }}">
                        S/N: {{ Str::limit($box->serial_number, 16) }}
                    </span>
                    @if ($box->last_seen_at)
                        <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ $box->last_seen_at->format('d/m/Y H:i:s') }}">
                            {{ $box->last_seen_at->diffForHumans() }}
                        </span>
                    @endif
                </div>

                {{-- Link to Edit --}}
                <a
                    href="{{ \App\Filament\Resources\OnesiBoxes\OnesiBoxResource::getUrl('edit', ['record' => $box]) }}"
                    class="absolute inset-0 rounded-xl"
                    title="Gestisci {{ $box->name }}"
                >
                    <span class="sr-only">Gestisci {{ $box->name }}</span>
                </a>
            </div>
        @empty
            <div class="col-span-full rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon
                    icon="heroicon-o-tv"
                    class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                />
                <h3 class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">
                    Nessuna OnesiBox registrata
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Aggiungi una OnesiBox per iniziare a monitorare i dispositivi.
                </p>
            </div>
        @endforelse
    </div>
</x-filament-widgets::widget>
