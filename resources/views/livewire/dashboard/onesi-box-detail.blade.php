<div class="mx-auto max-w-4xl pb-24" wire:poll.15s="refreshFromDatabase">
    {{-- Sticky header --}}
    <header class="sticky top-0 z-30 -mx-4 mb-4 border-b border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95 sm:-mx-6 sm:px-6">
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

        {{-- Accordion body (native <details>) --}}
        <div class="mt-4 space-y-2">
            @if($this->canControl && $this->isOnline)
                @if($this->accordionDefaults['session'] ?? false)
                    <details class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800" open>
                        <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
                            <span>Sessione in corso</span>
                            <flux:icon name="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180" />
                        </summary>
                        <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                            <livewire:dashboard.controls.session-status :onesiBox="$onesiBox" wire:key="session-status-{{ $onesiBox->id }}" />
                        </div>
                    </details>
                @endif

                <details class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800" @if($this->accordionDefaults['commands'] ?? false) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
                        <span>Comandi in coda</span>
                        <flux:icon name="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                        <livewire:dashboard.controls.command-queue :onesiBox="$onesiBox" wire:key="command-queue-{{ $onesiBox->id }}" />
                    </div>
                </details>

                <details class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                    <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
                        <span>Meeting programmati</span>
                        <flux:icon name="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                        <livewire:dashboard.controls.meeting-schedule :onesi-box="$onesiBox" wire:key="meeting-schedule-{{ $onesiBox->id }}" />
                    </div>
                </details>

            @endif

            @if($this->recipient)
                <details class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                    <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
                        <span>Contatti destinatario</span>
                        <flux:icon name="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                        @include('livewire.dashboard.partials.recipient-contacts', ['recipient' => $this->recipient])
                    </div>
                </details>
            @endif

            @if($this->isAdmin)
                <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-2 flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="shield-check" class="h-4 w-4" />
                        Amministrazione
                    </flux:heading>

                    <div class="space-y-2">
                        <details class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                            <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
                                <span>Sistema</span>
                                <flux:icon name="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180" />
                            </summary>
                            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                                <livewire:dashboard.controls.system-info :onesiBox="$onesiBox" wire:key="system-info-{{ $onesiBox->id }}" />
                            </div>
                        </details>

                        <details class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                            <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
                                <span>Rete</span>
                                <flux:icon name="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180" />
                            </summary>
                            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                                <livewire:dashboard.controls.network-info :onesiBox="$onesiBox" wire:key="network-info-{{ $onesiBox->id }}" />
                            </div>
                        </details>

                        @if($this->isOnline)
                            <details class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                                <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
                                    <span>Controlli sistema</span>
                                    <flux:icon name="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180" />
                                </summary>
                                <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                                    <livewire:dashboard.controls.system-controls :onesiBox="$onesiBox" wire:key="system-{{ $onesiBox->id }}" />
                                </div>
                            </details>

                            <details class="group rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                                <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-medium select-none">
                                    <span>Log</span>
                                    <flux:icon name="chevron-down" class="h-4 w-4 transition-transform group-open:rotate-180" />
                                </summary>
                                <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                                    <livewire:dashboard.controls.log-viewer :onesiBox="$onesiBox" wire:key="logs-{{ $onesiBox->id }}" />
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Bottom bar + Quick play sheet --}}
    <livewire:dashboard.controls.bottom-bar :onesiBox="$onesiBox" wire:key="bottom-bar-{{ $onesiBox->id }}" />
    <livewire:dashboard.controls.quick-play-sheet :onesiBox="$onesiBox" wire:key="quick-play-sheet-{{ $onesiBox->id }}" />
</div>
