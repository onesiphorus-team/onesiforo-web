@once
    <style>
        @media (min-width: 768px) {
            .detail-accordions details summary { cursor: default; pointer-events: none; }
            .detail-accordions details summary .chevron-toggle { display: none; }
        }
    </style>
@endonce

<div class="mx-auto max-w-4xl md:max-w-6xl pb-24 md:pb-8" wire:poll.15s="refreshFromDatabase">
    {{-- Sticky header --}}
    <header class="sticky top-0 z-10 -mx-4 mb-4 border-b border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95 sm:-mx-6 sm:px-6">
        <div class="flex items-center gap-3">
            <flux:button variant="subtle" wire:click="goBack" icon="arrow-left" size="sm" aria-label="Torna alla lista" />

            <div class="min-w-0 flex-1">
                <flux:heading class="truncate text-base font-semibold">{{ $onesiBox->name }}</flux:heading>
                @if($onesiBox->app_version)
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">v{{ $onesiBox->app_version }}</flux:text>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if($this->isOnline)
                    <span class="relative flex h-2.5 w-2.5" role="status" aria-label="Online">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                    </span>
                @else
                    <span class="h-2.5 w-2.5 rounded-full bg-zinc-400" role="status" aria-label="Offline"></span>
                @endif
            </div>
        </div>
    </header>

    <div class="px-4 sm:px-6">
        {{-- Bottom bar (renders inline on desktop, sticky bottom on mobile) --}}
        <livewire:dashboard.controls.bottom-bar :onesiBox="$onesiBox" wire:key="bottom-bar-{{ $onesiBox->id }}" />

        @if($onesiBox->status === \App\Enums\OnesiBoxStatus::Error)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                <flux:callout.heading>Dispositivo in stato di errore</flux:callout.heading>
                <flux:callout.text>
                    L'OnesiBox ha segnalato un errore. Controlla i log in fondo alla pagina.
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Hero --}}
        <livewire:dashboard.controls.hero-card
            :onesiBox="$onesiBox"
            :state="$this->heroState"
            :isPaused="$this->isMediaPaused"
            wire:key="hero-{{ $onesiBox->id }}" />

        @if(! $this->recipient)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                <flux:callout.heading>Nessun destinatario associato</flux:callout.heading>
                <flux:callout.text>
                    Questa OnesiBox non ha ancora un destinatario associato. Contatta l'amministratore.
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Diagnostic screenshots carousel --}}
        <livewire:onesi-box.screenshot-carousel
            :box="$onesiBox"
            variant="full"
            :key="'carousel-full-'.$onesiBox->id" />

        {{-- Accordion body (native <details> via x-accordion-item component) --}}
        <div class="mt-4 space-y-2 md:grid md:grid-cols-2 md:gap-4 md:space-y-0 detail-accordions">
            @if($this->canControl && $this->isOnline)
                @if($this->accordionDefaults['session'] ?? false)
                    <x-accordion-item title="Sessione in corso" :open="true">
                        <livewire:dashboard.controls.session-status :onesiBox="$onesiBox" wire:key="session-status-{{ $onesiBox->id }}" />
                    </x-accordion-item>
                @endif

                <x-accordion-item title="Comandi in coda" :open="$this->accordionDefaults['commands'] ?? false">
                    <livewire:dashboard.controls.command-queue :onesiBox="$onesiBox" wire:key="command-queue-{{ $onesiBox->id }}" />
                </x-accordion-item>

                <x-accordion-item title="Attività oggi">
                    <livewire:dashboard.controls.activity-timeline :onesiBox="$onesiBox" wire:key="activity-timeline-{{ $onesiBox->id }}" />
                </x-accordion-item>

                <x-accordion-item title="Meeting programmati">
                    <livewire:dashboard.controls.meeting-schedule :onesi-box="$onesiBox" wire:key="meeting-schedule-{{ $onesiBox->id }}" />
                </x-accordion-item>

            @endif

            @if($this->recipient)
                <x-accordion-item title="Contatti destinatario">
                    @include('livewire.dashboard.partials.recipient-contacts', ['recipient' => $this->recipient])
                </x-accordion-item>
            @endif

            @if($this->isAdmin)
                <div class="md:col-span-2 mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-2 flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="shield-check" class="h-4 w-4" />
                        Amministrazione
                    </flux:heading>

                    <div class="space-y-2 md:grid md:grid-cols-2 md:gap-4 md:space-y-0 detail-accordions">
                        <x-accordion-item title="Sistema">
                            <livewire:dashboard.controls.system-info :onesiBox="$onesiBox" wire:key="system-info-{{ $onesiBox->id }}" />
                        </x-accordion-item>

                        <x-accordion-item title="Rete">
                            <livewire:dashboard.controls.network-info :onesiBox="$onesiBox" wire:key="network-info-{{ $onesiBox->id }}" />
                        </x-accordion-item>

                        @if($this->isOnline)
                            <x-accordion-item title="Controlli sistema">
                                <livewire:dashboard.controls.system-controls :onesiBox="$onesiBox" wire:key="system-{{ $onesiBox->id }}" />
                            </x-accordion-item>

                            <x-accordion-item title="Log">
                                <livewire:dashboard.controls.log-viewer :onesiBox="$onesiBox" wire:key="logs-{{ $onesiBox->id }}" />
                            </x-accordion-item>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($this->canControl)
        <livewire:dashboard.controls.quick-play-sheet :onesiBox="$onesiBox" wire:key="quick-play-sheet-{{ $onesiBox->id }}" />
    @endif
</div>
