@php $screenshots = $this->screenshots; @endphp

<div x-data="{
        open: false,
        src: '',
        download: '',
        captured: '',
        show(src, download, captured) {
            this.src = src;
            this.download = download;
            this.captured = captured;
            this.open = true;
        },
        close() { this.open = false; },
     }"
     x-on:keydown.escape.window="close()"
     class="min-w-0">
    @if ($screenshots->isNotEmpty())
        @if ($variant === 'compact')
            {{-- Lined up with the text of the row above (icon 3rem + gap 1rem); the padding keeps the ring of the first picture from being clipped --}}
            <div class="mt-3 ml-16 flex gap-1.5 overflow-x-auto p-0.5 min-w-0" role="region" aria-label="Diagnostica">
                @foreach ($screenshots as $s)
                    <button type="button"
                            wire:key="carousel-compact-{{ $s->id }}"
                            @click="show(@js($s->signedUrl(10)), @js($s->signedUrl(10)), @js($s->captured_at->toDateTimeString()))"
                            class="shrink-0 cursor-zoom-in"
                            aria-label="Ingrandisci screenshot {{ $s->captured_at->toDateTimeString() }}">
                        <img src="{{ $s->signedUrl() }}"
                             alt="{{ $s->captured_at->toDateTimeString() }}"
                             loading="lazy"
                             width="80" height="45"
                             class="rounded border border-gray-200 dark:border-gray-700 {{ $loop->first ? 'ring-2 ring-indigo-500' : '' }}" />
                    </button>
                @endforeach
            </div>
        @else
            <section class="mt-4 min-w-0" aria-label="Diagnostica schermo">
                <h3 class="font-semibold mb-2">Diagnostica schermo</h3>
                <div class="flex gap-2 overflow-x-auto p-0.5 pb-2 min-w-0">
                    @foreach ($screenshots as $s)
                        <button type="button"
                                wire:key="carousel-full-{{ $s->id }}"
                                @click="show(@js($s->signedUrl(10)), @js($s->signedUrl(10)), @js($s->captured_at->toDateTimeString()))"
                                class="shrink-0 cursor-zoom-in"
                                aria-label="Ingrandisci screenshot {{ $s->captured_at->toDateTimeString() }}">
                            <img src="{{ $s->signedUrl() }}"
                                 alt="{{ $s->captured_at->toDateTimeString() }}"
                                 loading="lazy"
                                 width="160" height="90"
                                 class="rounded border border-gray-200 dark:border-gray-700 {{ $loop->first ? 'ring-2 ring-indigo-500' : '' }}" />
                            <div class="text-xs text-gray-500 text-center mt-1">
                                {{ $s->captured_at->diffForHumans() }}
                            </div>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif
    @elseif ($variant === 'compact' && ! $box->screenshot_enabled)
        <div class="mt-3 ml-16 text-xs text-gray-500 italic">Diagnostica non attiva</div>
    @else
        {{-- detail view empty OR compact view with feature enabled but no data: render nothing --}}
    @endif

    {{-- Lightbox modal --}}
    <template x-teleport="body">
        <div x-show="open"
             x-cloak
             x-transition.opacity
             @click.self="close()"
             role="dialog"
             aria-modal="true"
             aria-label="Anteprima screenshot"
             class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-4">
            <div class="relative max-h-full max-w-full">
                <img :src="src"
                     alt=""
                     class="max-h-[90vh] max-w-[95vw] rounded shadow-2xl object-contain" />
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm text-white">
                    <span x-text="captured"></span>
                    <div class="flex items-center gap-3">
                        <a :href="download"
                           download
                           class="underline hover:text-gray-200">Scarica</a>
                        <button type="button"
                                @click="close()"
                                class="rounded bg-white/10 px-3 py-1 hover:bg-white/20"
                                aria-label="Chiudi anteprima">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
