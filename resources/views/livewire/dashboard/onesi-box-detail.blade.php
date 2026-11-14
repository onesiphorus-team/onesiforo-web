{{--
    Page structure, from most to least important:
    1. header (which box, online or not)  2. commands  3. what is happening now
    4. information groups (today, meetings, contacts)  5. technical details, muted and closed.
    The grey page background makes every group stand out as its own white card.
--}}
<div class="-m-6 min-h-dvh min-w-0 bg-zinc-100 px-4 pb-28 sm:px-6 md:pb-10 lg:-m-8 lg:px-8 dark:bg-zinc-900" wire:poll.30s.visible="refreshFromDatabase">
    {{-- Header --}}
    <header class="sticky top-0 z-10 -mx-4 mb-4 border-b border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:border-zinc-700 dark:bg-zinc-800/95">
        <div class="mx-auto flex max-w-6xl items-center gap-3">
            @if($this->canGoBackToList)
                <flux:button variant="subtle" wire:click="goBack" icon="arrow-left" aria-label="Torna ai miei box" class="-ml-2" />
            @endif

            {{-- Person first: family members think of "mum's box", not of the device name --}}
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-bold text-zinc-900 sm:text-xl dark:text-white">{{ $this->recipient?->full_name ?? $onesiBox->name }}</h1>
                @if($this->recipient)
                    <p class="truncate text-xs text-zinc-500 sm:text-sm dark:text-zinc-400">{{ $onesiBox->name }}</p>
                @endif
            </div>

            @if($this->isOnline)
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-500/10 dark:text-green-300" role="status">
                    <span class="size-2 rounded-full bg-green-500"></span> Online
                </span>
            @else
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300" role="status">
                    <span class="size-2 rounded-full bg-zinc-400"></span> Offline
                </span>
            @endif
        </div>
    </header>

    <div class="mx-auto max-w-6xl space-y-4 sm:space-y-6">
        <x-install-app-hint />

        {{-- Commands (inline toolbar on desktop, fixed at the bottom on mobile) --}}
        <livewire:dashboard.controls.bottom-bar :onesiBox="$onesiBox" wire:key="bottom-bar-{{ $onesiBox->id }}" />

        @if($onesiBox->status === \App\Enums\OnesiBoxStatus::Error)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>Il box ha segnalato un errore</flux:callout.heading>
                <flux:callout.text>
                    Prova a mostrare di nuovo il contenuto. Se il problema continua, i dettagli sono in «Altro».
                </flux:callout.text>
            </flux:callout>
        @endif

        @if(! $this->recipient)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Nessun destinatario associato</flux:callout.heading>
                <flux:callout.text>
                    Questo box non è ancora associato a una persona. Chiedi all'amministratore di completarlo.
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- What is happening on the box --}}
        <livewire:dashboard.controls.hero-card :onesiBox="$onesiBox" wire:key="hero-{{ $onesiBox->id }}" />

        <livewire:dashboard.controls.custom-commands-list :onesiBox="$onesiBox" wire:key="custom-commands-{{ $onesiBox->id }}" />

        <div class="grid gap-4 sm:gap-6 md:grid-cols-2">
            <x-box-section title="Oggi sul box" subtitle="Cosa ha mostrato oggi" icon="clock">
                <livewire:dashboard.controls.activity-timeline :onesiBox="$onesiBox" wire:key="activity-timeline-{{ $onesiBox->id }}" />
            </x-box-section>

            <div class="min-w-0 space-y-4 sm:space-y-6">
                <x-box-section id="adunanze" title="Adunanze" subtitle="Collegamento alle adunanze della congregazione" icon="calendar-days">
                    <livewire:dashboard.controls.meeting-schedule :onesi-box="$onesiBox" wire:key="meeting-schedule-{{ $onesiBox->id }}" />
                </x-box-section>

                @if($this->recipient)
                    <x-box-section title="Contatti" subtitle="Chi chiamare per {{ $this->recipient->first_name }}" icon="phone">
                        @include('livewire.dashboard.partials.recipient-contacts', ['recipient' => $this->recipient])
                    </x-box-section>
                @endif
            </div>
        </div>

        {{-- Technical details: muted (dashed, no background) and closed, so they never compete with the rest --}}
        <section class="overflow-hidden rounded-2xl border border-dashed border-zinc-300 dark:border-zinc-700" aria-labelledby="technical-heading">
            <button type="button"
                    wire:click="toggleTechnicalDetails"
                    aria-expanded="{{ $showTechnicalDetails ? 'true' : 'false' }}"
                    aria-controls="technical-details"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-white/60 sm:px-5 dark:hover:bg-zinc-800/60">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-200/70 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300" aria-hidden="true">
                    <flux:icon name="wrench-screwdriver" class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span id="technical-heading" class="block text-base font-semibold text-zinc-700 dark:text-zinc-200">Altro</span>
                    <span class="block text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $this->isAdmin ? 'Dettagli tecnici: comandi in coda, schermate, sistema, rete e log' : 'Dettagli tecnici: comandi in coda e schermate' }}
                    </span>
                </span>
                @if($this->pendingCommandCount > 0)
                    <flux:badge size="sm" color="amber">{{ $this->pendingCommandCount }} in coda</flux:badge>
                @endif
                <flux:icon.loading class="size-5 text-zinc-400" wire:loading wire:target="toggleTechnicalDetails" />
                <flux:icon name="chevron-down" class="size-5 shrink-0 text-zinc-400 transition-transform {{ $showTechnicalDetails ? 'rotate-180' : '' }}" wire:loading.remove wire:target="toggleTechnicalDetails" />
            </button>

            @if($showTechnicalDetails)
                <div id="technical-details" class="space-y-4 border-t border-dashed border-zinc-300 p-4 sm:p-5 dark:border-zinc-700">
                    <div class="grid gap-4 md:grid-cols-2">
                        @if($this->canControl)
                            <livewire:dashboard.controls.command-queue :onesiBox="$onesiBox" wire:key="command-queue-{{ $onesiBox->id }}" />
                        @endif

                        @if($this->isAdmin)
                            <livewire:dashboard.controls.system-info :onesiBox="$onesiBox" wire:key="system-info-{{ $onesiBox->id }}" />
                            <livewire:dashboard.controls.network-info :onesiBox="$onesiBox" wire:key="network-info-{{ $onesiBox->id }}" />
                            @if($this->isOnline)
                                <livewire:dashboard.controls.system-controls :onesiBox="$onesiBox" wire:key="system-{{ $onesiBox->id }}" />
                                <livewire:dashboard.controls.log-viewer :onesiBox="$onesiBox" wire:key="logs-{{ $onesiBox->id }}" />
                            @endif
                        @endif
                    </div>

                    <livewire:onesi-box.screenshot-carousel :box="$onesiBox" variant="full" :key="'carousel-full-'.$onesiBox->id" />

                    <dl class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <div><dt class="inline">Versione app:</dt> <dd class="inline font-medium">{{ $onesiBox->app_version ?? '—' }}</dd></div>
                        <div><dt class="inline">Numero di serie:</dt> <dd class="inline font-medium">{{ $onesiBox->serial_number }}</dd></div>
                        <div><dt class="inline">Ultimo contatto:</dt> <dd class="inline font-medium">{{ $onesiBox->last_seen_at?->diffForHumans() ?? 'mai' }}</dd></div>
                    </dl>
                </div>
            @endif
        </section>
    </div>

    @if($this->canControl)
        <livewire:dashboard.controls.quick-play-sheet :onesiBox="$onesiBox" wire:key="quick-play-sheet-{{ $onesiBox->id }}" />
    @endif
</div>
