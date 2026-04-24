<div class="space-y-6">
    {{-- HEADER --}}
    <div class="rounded border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 p-4">
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <span class="inline-flex items-center gap-2">
                    @if ($this->enabled)
                        <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                        <span class="font-semibold">Attiva</span>
                    @else
                        <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                        <span class="font-semibold">Disattivata</span>
                    @endif
                </span>
            </div>
            <div>
                Intervallo:
                <input type="number"
                       min="10" max="3600"
                       wire:model="intervalSeconds"
                       class="w-24 rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700" />
                <span>s</span>
                <button type="button"
                        wire:click="saveInterval"
                        class="ml-1 px-2 py-1 rounded bg-primary-600 text-white text-sm">
                    Salva
                </button>
                @error('intervalSeconds')
                    <span class="text-red-600 text-sm ml-2">{{ $message }}</span>
                @enderror
            </div>
            <div class="ml-auto">
                <button type="button"
                        wire:click="toggle"
                        class="px-3 py-1 rounded {{ $this->enabled ? 'bg-red-600' : 'bg-green-600' }} text-white text-sm">
                    {{ $this->enabled ? 'Disattiva' : 'Attiva' }}
                </button>
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-500">
            La box applicherà il cambio al prossimo heartbeat (entro 30s).
        </p>
    </div>

    {{-- STALE CAPTURE WARNING --}}
    @php
        $latest = $this->screenshots->first();
        $isStale = $this->enabled
            && $latest !== null
            && $latest->captured_at->lt(now()->subMinutes(5));
    @endphp
    @if ($isStale)
        <div class="rounded border border-amber-400 bg-amber-50 dark:border-amber-600 dark:bg-amber-900/20 p-3 text-sm text-amber-800 dark:text-amber-200">
            ⚠ Ultimo scatto {{ $latest->captured_at->diffForHumans() }} — la box potrebbe essere offline o grim in errore.
        </div>
    @endif

    {{-- PREVIEW GRANDE --}}
    @php
        $selected = $this->selectedId
            ? $this->screenshots->firstWhere('id', $this->selectedId)
            : $this->screenshots->first();
    @endphp

    @if ($selected)
        <div class="rounded border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 p-4">
            <img src="{{ $selected->signedUrl() }}"
                 alt="screenshot"
                 loading="lazy"
                 class="w-full max-w-full rounded" />
            <div class="mt-2 text-sm text-gray-500">
                {{ $selected->captured_at->toDateTimeString() }} —
                {{ $selected->width }}×{{ $selected->height }}, {{ round($selected->bytes / 1024) }} KB
                <a href="{{ $selected->signedUrl(10) }}"
                   download
                   class="ml-2 underline">Scarica originale</a>
            </div>
        </div>
    @elseif (! $this->enabled)
        <div class="rounded bg-gray-100 dark:bg-gray-800 p-6 text-center text-gray-500">
            Diagnostica disabilitata. Abilitala per iniziare la cattura.
        </div>
    @else
        <div class="rounded bg-gray-100 dark:bg-gray-800 p-6 text-center text-gray-500">
            In attesa del primo screenshot… (entro ~{{ $this->intervalSeconds }}s dall'abilitazione)
        </div>
    @endif

    {{-- TIMELINE --}}
    @if ($this->top10->isNotEmpty())
        <div>
            <h3 class="font-semibold mb-2">Ultimi 10 (realtime)</h3>
            <div class="flex gap-2 overflow-x-auto pb-2">
                @foreach ($this->top10 as $s)
                    <button type="button"
                            wire:click="select({{ $s->id }})"
                            class="shrink-0 {{ $this->selectedId === $s->id ? 'ring-2 ring-primary-500' : '' }}">
                        <img src="{{ $s->signedUrl() }}"
                             loading="lazy"
                             width="160" height="90"
                             class="rounded border border-gray-300 dark:border-gray-700" />
                        <div class="text-xs text-gray-500 text-center mt-1">
                            {{ $s->captured_at->diffForHumans() }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->hourlyBeyondTop10->isNotEmpty())
        <div>
            <h3 class="font-semibold mb-2">24 ore (una per ora)</h3>
            <div class="flex gap-2 overflow-x-auto pb-2">
                @foreach ($this->hourlyBeyondTop10 as $s)
                    <button type="button"
                            wire:click="select({{ $s->id }})"
                            class="shrink-0 {{ $this->selectedId === $s->id ? 'ring-2 ring-primary-500' : '' }}">
                        <img src="{{ $s->signedUrl() }}"
                             loading="lazy"
                             width="160" height="90"
                             class="rounded border border-gray-300 dark:border-gray-700" />
                        <div class="text-xs text-gray-500 text-center mt-1">
                            {{ $s->captured_at->format('H:00') }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
