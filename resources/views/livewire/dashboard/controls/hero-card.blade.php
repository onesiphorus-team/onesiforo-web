@php
    $state = $this->state;
    $isPaused = $state === 'media' && $this->isPaused;
    $session = $this->activeSession;
    $lastSeen = $onesiBox->last_seen_at;

    [$statusLabel, $statusClasses, $dotClasses] = match (true) {
        $state === 'offline' => ['Spento o non connesso', 'bg-amber-50 text-amber-900 border-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/20', 'bg-amber-500'],
        $state === 'call' => ['Riunione in corso', 'bg-green-50 text-green-900 border-green-200 dark:bg-green-500/10 dark:text-green-200 dark:border-green-500/20', 'bg-green-500'],
        $isPaused => ['In pausa', 'bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-700/40 dark:text-zinc-200 dark:border-zinc-700', 'bg-zinc-400'],
        $state === 'media' => ['In riproduzione', 'bg-green-50 text-green-900 border-green-200 dark:bg-green-500/10 dark:text-green-200 dark:border-green-500/20', 'bg-green-500'],
        default => ['Acceso e in attesa', 'bg-zinc-50 text-zinc-800 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700', 'bg-green-500'],
    };
@endphp

<section data-hero-state="{{ $state }}"
         wire:poll.5s.visible="refreshBox"
         aria-live="polite"
         aria-label="Cosa succede sul box"
         class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">

    {{-- Status line --}}
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b px-4 py-3 sm:px-6 {{ $statusClasses }}">
        <div class="flex items-center gap-2.5">
            <span class="relative flex size-2.5">
                @if($state === 'media' && ! $isPaused)
                    <span class="absolute inline-flex size-full animate-ping rounded-full opacity-60 {{ $dotClasses }}"></span>
                @endif
                <span class="relative inline-flex size-2.5 rounded-full {{ $dotClasses }}"></span>
            </span>
            <span class="text-sm font-semibold" data-slot="status-label">{{ $statusLabel }}</span>
        </div>
        @if($state !== 'offline' && ($pendingCommands = $this->pendingCommandCount) > 0)
            <span class="flex items-center gap-1.5 text-xs font-medium" data-slot="pending-commands">
                <flux:icon.loading class="size-3.5" />
                {{ $pendingCommands === 1 ? 'Un comando sta arrivando al box…' : "{$pendingCommands} comandi stanno arrivando al box…" }}
            </span>
        @else
            <span class="text-xs opacity-75">
                {{ $state === 'offline' ? 'Ultimo contatto: '.($lastSeen?->diffForHumans() ?? 'mai') : 'Aggiornato '.($lastSeen?->diffForHumans() ?? 'ora') }}
            </span>
        @endif
    </div>

    <div class="grid md:grid-cols-5">
        {{-- What is happening --}}
        <div class="min-w-0 space-y-5 p-4 sm:p-6 md:col-span-3">
            @if($state === 'media')
                @php
                    $jwMedia = $this->currentJwMedia;
                    $isAudio = $onesiBox->current_media_type === 'audio';
                    $host = parse_url((string) $onesiBox->current_media_url, PHP_URL_HOST) ?: null;
                    $title = $onesiBox->current_media_title ?: ($jwMedia['title'] ?? $host ?? 'Contenuto in riproduzione');
                    $thumbnailUrl = $jwMedia['thumbnail_url'] ?? null;
                    $position = $this->estimatedPosition;
                    $duration = $onesiBox->current_media_duration ?: ($jwMedia['duration_seconds'] ?? null);
                @endphp

                <div class="flex items-start gap-4">
                    <div class="relative shrink-0 overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-700 {{ $isAudio ? 'size-24 sm:size-28' : 'aspect-video w-32 sm:w-52' }}">
                        @if($thumbnailUrl)
                            <img src="{{ $thumbnailUrl }}" alt="" referrerpolicy="no-referrer" data-slot="media-thumbnail" class="size-full object-cover">
                        @else
                            <flux:icon name="{{ $isAudio ? 'musical-note' : 'film' }}" class="absolute inset-0 m-auto size-8 text-zinc-400" />
                        @endif
                        @if($isPaused)
                            <span class="absolute inset-0 flex items-center justify-center bg-black/40">
                                <flux:icon name="pause" variant="solid" class="size-8 text-white" />
                            </span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ $isAudio ? 'Audio' : 'Video' }} · {{ $jwMedia !== null ? 'jw.org' : ($host ?? 'link') }}
                        </p>
                        <h2 class="mt-1 line-clamp-3 break-words text-lg font-semibold leading-snug text-zinc-900 sm:text-xl dark:text-white">{{ $title }}</h2>
                    </div>
                </div>

                @if($position !== null && $duration)
                    <div wire:key="progress-{{ md5((string) $onesiBox->current_media_url) }}-{{ $position }}-{{ $isPaused ? 'paused' : 'playing' }}"
                         x-data="{
                            position: {{ $position }},
                            duration: {{ $duration }},
                            timer: null,
                            init() {
                                if (! {{ $isPaused ? 'true' : 'false' }}) {
                                    this.timer = setInterval(() => { if (this.position < this.duration) this.position++ }, 1000);
                                }
                            },
                            destroy() { clearInterval(this.timer) },
                            format(seconds) {
                                seconds = Math.max(0, Math.floor(seconds));
                                const hours = Math.floor(seconds / 3600);
                                const minutes = Math.floor((seconds % 3600) / 60);
                                const rest = String(seconds % 60).padStart(2, '0');
                                return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`;
                            },
                         }">
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-label="Avanzamento" aria-valuemin="0" aria-valuemax="{{ $duration }}" :aria-valuenow="position">
                            <div class="h-full rounded-full transition-[width] duration-1000 ease-linear {{ $isPaused ? 'bg-zinc-400' : 'bg-green-500' }}"
                                 style="width: {{ min(100, round($position / $duration * 100, 1)) }}%"
                                 :style="`width: ${Math.min(100, position / duration * 100)}%`"></div>
                        </div>
                        <div class="mt-1.5 flex justify-between text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                            <span x-text="format(position)">{{ gmdate($duration >= 3600 ? 'G:i:s' : 'i:s', $position) }}</span>
                            <span>mancano <span class="font-semibold text-zinc-700 dark:text-zinc-200" x-text="format(duration - position)">{{ gmdate($duration >= 3600 ? 'G:i:s' : 'i:s', max(0, $duration - $position)) }}</span></span>
                        </div>
                    </div>
                @endif

                @if($this->canControl)
                    <div class="grid grid-cols-2 gap-2 sm:flex">
                        @if($isPaused)
                            <flux:button wire:click="resume" wire:loading.attr="disabled" wire:target="resume" variant="primary" color="indigo" icon="play" class="sm:min-w-36">Riprendi</flux:button>
                        @else
                            <flux:button wire:click="pause" wire:loading.attr="disabled" wire:target="pause" variant="primary" color="indigo" icon="pause" class="sm:min-w-36">Pausa</flux:button>
                        @endif
                        <flux:button wire:click="stop" wire:loading.attr="disabled" wire:target="stop" variant="danger" icon="stop" class="sm:min-w-36">
                            {{ $session ? 'Ferma tutto' : 'Stop' }}
                        </flux:button>
                    </div>
                @endif
            @elseif($state === 'call')
                <div class="flex items-start gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                        <flux:icon name="video-camera" class="size-7" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Riunione Zoom in corso</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            @if($onesiBox->current_meeting_joined_at)
                                Collegato da {{ $onesiBox->current_meeting_joined_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}
                            @else
                                Collegamento attivo
                            @endif
                            · riunione {{ $onesiBox->current_meeting_id }}
                        </p>
                    </div>
                </div>
                @if($this->canControl)
                    <flux:button wire:click="leaveZoom" wire:loading.attr="disabled" wire:target="leaveZoom" variant="danger" icon="phone-x-mark" class="w-full sm:w-auto">Termina chiamata</flux:button>
                @endif
            @elseif($state === 'offline')
                <div class="flex items-start gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                        <flux:icon name="signal-slash" class="size-7" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Il box non è raggiungibile</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            Controlla che sia acceso e collegato a Internet. Finché non torna raggiungibile non può ricevere contenuti.
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                        <flux:icon name="tv" class="size-7" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Nessun contenuto in riproduzione</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Il box è acceso e pronto. Scegli cosa mostrare.</p>
                    </div>
                </div>
                @if($this->canControl)
                    <div class="grid grid-cols-3 gap-2" data-slot="idle-shortcuts">
                        <flux:button x-on:click="$dispatch('open-quick-play', { tab: 'jw-video' })" variant="primary" color="indigo" icon="film">Video</flux:button>
                        <flux:button x-on:click="$dispatch('open-quick-play', { tab: 'jw-audio' })" variant="primary" color="indigo" icon="musical-note">Audio</flux:button>
                        <flux:button x-on:click="$dispatch('open-quick-play', { tab: 'zoom' })" variant="primary" color="indigo" icon="video-camera">Zoom</flux:button>
                        <flux:button x-on:click="$dispatch('open-quick-play', { tab: 'random-video' })" icon="sparkles" class="col-span-3">Video casuali</flux:button>
                    </div>
                @endif
            @endif

            {{-- Playlist: what comes next --}}
            @if($session && $state !== 'offline')
                @php
                    $upcomingItems = $this->upcomingItems;
                    $secondsLeft = $this->sessionSecondsLeft;
                    $currentNumber = $session->current_position + 1;
                    $totalItems = $currentNumber + $this->upcomingCount;
                    $isAudioSession = $session->media_type === 'audio';
                @endphp
                <div class="rounded-xl bg-indigo-50/70 p-4 dark:bg-indigo-500/10" data-slot="session">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <p class="flex items-center gap-2 text-sm font-semibold text-indigo-950 dark:text-indigo-100">
                            <flux:icon name="queue-list" variant="micro" class="size-4" />
                            {{ $session->playlist->name ?? 'Playlist' }} · {{ $currentNumber }} di {{ $totalItems }}
                        </p>
                        @if($secondsLeft !== null && $secondsLeft > 0)
                            <p class="text-sm text-indigo-900/80 dark:text-indigo-200/80">
                                mancano {{ $this->formatDuration($secondsLeft) }} · fine verso le {{ now()->addSeconds($secondsLeft)->setTimezone($displayTimezone)->format('H:i') }}
                            </p>
                        @endif
                    </div>

                    @if($upcomingItems !== [])
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wider text-indigo-800/70 dark:text-indigo-200/70">Dopo</p>
                        <ol class="mt-2 space-y-2">
                            @foreach($upcomingItems as $index => $upcomingItem)
                                <li class="flex items-center gap-3" wire:key="upcoming-{{ $index }}">
                                    <span class="relative shrink-0 overflow-hidden rounded-md bg-white/70 dark:bg-zinc-700 {{ $isAudioSession ? 'size-10' : 'aspect-video w-16' }}">
                                        @if($upcomingItem['thumbnail_url'])
                                            <img src="{{ $upcomingItem['thumbnail_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="size-full object-cover">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-sm {{ $index === 0 ? 'font-medium text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-300' }}">{{ $upcomingItem['title'] }}</span>
                                    @if($upcomingItem['duration_seconds'])
                                        <span class="shrink-0 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ $this->formatDuration($upcomingItem['duration_seconds']) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                        @if($this->upcomingCount > count($upcomingItems))
                            <p class="mt-2 text-xs text-indigo-900/70 dark:text-indigo-200/70">e altri {{ $this->upcomingCount - count($upcomingItems) }} contenuti</p>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-indigo-900/80 dark:text-indigo-200/80">È l'ultimo contenuto della playlist.</p>
                    @endif
                </div>
            @endif

            {{-- Next congregation meeting --}}
            @if($nextMeeting = $this->nextMeeting)
                <a href="#adunanze" class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-700/40">
                    <flux:icon name="calendar-days" class="size-5 shrink-0 text-zinc-400" />
                    <span class="min-w-0">
                        Prossima adunanza:
                        <strong class="font-semibold text-zinc-900 dark:text-white">{{ ucfirst($nextMeeting['scheduled_at']->translatedFormat('l j F')) }} alle {{ $nextMeeting['scheduled_at']->format('H:i') }}</strong>
                    </span>
                </a>
            @endif
        </div>

        {{-- Picture of the box screen --}}
        <aside class="min-w-0 border-t border-zinc-200 p-4 sm:p-6 md:col-span-2 md:border-l md:border-t-0 dark:border-zinc-700" aria-label="Schermo del box">
            @php $screenshot = $this->latestScreenshot; @endphp
            <div class="mb-2 flex items-baseline justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Schermo del box</p>
                @if($screenshot)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $screenshot->captured_at->diffForHumans() }}</p>
                @endif
            </div>

            @if($screenshot)
                <div wire:key="screen-{{ $screenshot->id }}" wire:ignore x-data="{ zoomed: false }" x-on:keydown.escape.window="zoomed = false">
                    <button type="button" x-on:click="zoomed = true" class="group relative block aspect-video w-full cursor-zoom-in overflow-hidden rounded-xl bg-black" aria-label="Ingrandisci l'immagine dello schermo">
                        <img src="{{ $screenshot->signedUrl(30) }}" alt="Ultima immagine dello schermo del box" class="size-full object-contain" data-slot="screen-preview">
                        <span class="absolute bottom-2 right-2 flex items-center gap-1 rounded-md bg-black/60 px-2 py-1 text-[11px] text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">
                            <flux:icon name="magnifying-glass-plus" variant="micro" class="size-3.5" /> Ingrandisci
                        </span>
                    </button>
                    <template x-teleport="body">
                        <div x-show="zoomed" x-cloak x-transition.opacity x-on:click="zoomed = false" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/85 p-4" role="dialog" aria-modal="true" aria-label="Schermo del box">
                            <img src="{{ $screenshot->signedUrl(30) }}" alt="" class="max-h-[90vh] max-w-[95vw] rounded-lg object-contain shadow-2xl">
                        </div>
                    </template>
                </div>
            @else
                {{-- Compact on phones, where vertical space matters most --}}
                <div class="flex items-center gap-3 rounded-xl border border-dashed border-zinc-300 p-3 md:aspect-video md:flex-col md:justify-center md:gap-2 md:p-4 md:text-center dark:border-zinc-600">
                    <flux:icon name="tv" class="size-6 shrink-0 text-zinc-300 md:size-8 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $onesiBox->screenshot_enabled ? 'In attesa della prima immagine dello schermo.' : 'Immagine dello schermo non attiva per questo box.' }}
                    </p>
                </div>
            @endif
        </aside>
    </div>
</section>
