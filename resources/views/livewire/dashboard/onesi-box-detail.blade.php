<div class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">
    {{-- Header --}}
    <div class="mb-6">
        <flux:button variant="subtle" wire:click="goBack" class="mb-4">
            <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
            Torna alla lista
        </flux:button>

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $onesiBox->name }}</flux:heading>
                @if($onesiBox->last_seen_at)
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                        Ultimo contatto: {{ $onesiBox->last_seen_at->diffForHumans() }}
                    </flux:text>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if($this->isOnline)
                    <flux:badge color="green" size="sm">Online</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Offline</flux:badge>
                @endif

                @if($onesiBox->status)
                    <flux:badge :color="$onesiBox->status->getColor()" size="sm">
                        {{ $onesiBox->status->getLabel() }}
                    </flux:badge>
                @endif
            </div>
        </div>
    </div>

    {{-- Recipient Contacts --}}
    @if($this->recipient)
        <div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 mb-6">
            <flux:heading size="lg" class="mb-4">Contatti Destinatario</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <flux:icon name="user" class="w-5 h-5 text-zinc-400" />
                    <flux:text>{{ $this->recipient->full_name }}</flux:text>
                </div>

                @if($this->recipient->phone)
                    <div class="flex items-center gap-3">
                        <flux:icon name="phone" class="w-5 h-5 text-zinc-400" />
                        <a href="tel:{{ $this->recipient->phone }}" class="text-blue-600 hover:underline dark:text-blue-400">
                            {{ $this->recipient->phone }}
                        </a>
                    </div>
                @endif

                @if($this->recipient->full_address)
                    <div class="flex items-center gap-3">
                        <flux:icon name="map-pin" class="w-5 h-5 text-zinc-400" />
                        <flux:text>{{ $this->recipient->full_address }}</flux:text>
                    </div>
                @endif

                @if($this->recipient->emergency_contacts && count($this->recipient->emergency_contacts) > 0)
                    <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:text class="font-semibold text-sm text-zinc-600 dark:text-zinc-300 mb-2">
                            Contatti di emergenza
                        </flux:text>
                        @foreach($this->recipient->emergency_contacts as $contact)
                            <div class="flex items-center gap-3 mb-2">
                                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500" />
                                <flux:text>
                                    {{ $contact['name'] }}
                                    @if(isset($contact['relationship'])) ({{ $contact['relationship'] }}) @endif
                                    - {{ $contact['phone'] }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @else
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
            <flux:callout.heading>Nessun destinatario associato</flux:callout.heading>
            <flux:callout.text>
                Questa OnesiBox non ha ancora un destinatario associato. Contatta l'amministratore per configurare il dispositivo.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Controls (only for Full permission and online devices) --}}
    @if($this->canControl)
        <div class="space-y-6">
            <flux:heading size="lg">Controlli</flux:heading>

            @if($this->isOnline)
                {{-- Media & Communication Controls --}}
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <livewire:dashboard.controls.audio-player :onesiBox="$onesiBox" wire:key="audio-{{ $onesiBox->id }}" />
                    <livewire:dashboard.controls.video-player :onesiBox="$onesiBox" wire:key="video-{{ $onesiBox->id }}" />
                    <livewire:dashboard.controls.zoom-call :onesiBox="$onesiBox" wire:key="zoom-{{ $onesiBox->id }}" />
                </div>

                {{-- System Controls (Admin only) --}}
                @if($this->isAdmin)
                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:heading size="lg" class="mb-4">Amministrazione</flux:heading>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <livewire:dashboard.controls.system-controls :onesiBox="$onesiBox" wire:key="system-{{ $onesiBox->id }}" />
                        </div>
                    </div>
                @endif
            @else
                <flux:callout icon="wifi" class="mb-4">
                    <flux:callout.heading>Dispositivo offline</flux:callout.heading>
                    <flux:callout.text>
                        I controlli saranno disponibili quando l'OnesiBox tornerà online.
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    @endif
</div>
