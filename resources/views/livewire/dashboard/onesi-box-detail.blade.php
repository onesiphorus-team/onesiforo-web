<div class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8" wire:poll.15s="refreshFromDatabase">
    {{-- Header --}}
    <div class="mb-6">
        <flux:button variant="subtle" wire:click="goBack" class="mb-4" icon="arrow-left">
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

            <div class="flex items-center gap-3">
                {{-- Online Status with Pulse Indicator --}}
                <div class="flex items-center gap-2">
                    @if($this->isOnline)
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">Online</span>
                    @else
                        <span class="h-3 w-3 rounded-full bg-zinc-400"></span>
                        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Offline</span>
                    @endif
                </div>

                @if($onesiBox->status)
                    <flux:badge :color="$onesiBox->status->getColor()" size="sm">
                        {{ $onesiBox->status->getLabel() }}
                    </flux:badge>
                @endif

                {{-- App Version --}}
                @if($onesiBox->app_version)
                    <flux:badge color="zinc" size="sm">v{{ $onesiBox->app_version }}</flux:badge>
                @endif
            </div>
        </div>

        {{-- Contextual Status Info --}}
        @if($this->currentMediaInfo)
            <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                <div class="flex items-center gap-2 text-green-700 dark:text-green-300">
                    <flux:icon name="{{ $this->currentMediaInfo['type'] === 'video' ? 'video-camera' : 'musical-note' }}" class="w-5 h-5" />
                    <span class="font-medium">
                        {{ $this->currentMediaInfo['type'] === 'video' ? 'Video' : 'Audio' }} in riproduzione
                    </span>
                </div>
                <p class="mt-1 text-sm text-green-600 dark:text-green-400 truncate">
                    {{ $this->currentMediaInfo['title'] ?? $this->currentMediaInfo['url'] }}
                </p>
            </div>
        @endif

        @if($this->currentMeetingInfo)
            <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                    <flux:icon name="phone" class="w-5 h-5" />
                    <span class="font-medium">Chiamata in corso</span>
                </div>
                <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                    Meeting ID: {{ $this->currentMeetingInfo['meeting_id'] }}
                </p>
            </div>
        @endif
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

    {{-- Session Status (visible to all caregivers, including read-only) --}}
    <livewire:dashboard.controls.session-status :onesiBox="$onesiBox" wire:key="session-status-{{ $onesiBox->id }}" />

    {{-- Controls (only for Full permission and online devices) --}}
    @if($this->canControl)
        <div class="space-y-6">
            <flux:heading size="lg">Controlli</flux:heading>

            @if($this->isOnline)
                {{-- Stop All Playback Button - Prima di tutto --}}
                <livewire:dashboard.controls.stop-all-playback :onesiBox="$onesiBox" wire:key="stop-all-{{ $onesiBox->id }}" />

                {{-- Volume Control --}}
                <livewire:dashboard.controls.volume-control :onesiBox="$onesiBox" wire:key="volume-{{ $onesiBox->id }}" />

                {{-- Session Manager (Playlist Sessions) --}}
                <livewire:dashboard.controls.session-manager :onesiBox="$onesiBox" wire:key="session-manager-{{ $onesiBox->id }}" />

                {{-- Command Queue --}}
                <livewire:dashboard.controls.command-queue :onesiBox="$onesiBox" wire:key="command-queue-{{ $onesiBox->id }}" />

                {{-- Media & Communication Controls - Single column on mobile --}}
                <div class="grid grid-cols-1 gap-4">
                    <livewire:dashboard.controls.audio-player :onesiBox="$onesiBox" wire:key="audio-{{ $onesiBox->id }}" />
                    <livewire:dashboard.controls.video-player :onesiBox="$onesiBox" wire:key="video-{{ $onesiBox->id }}" />
                    <livewire:dashboard.controls.stream-player :onesiBox="$onesiBox" wire:key="stream-{{ $onesiBox->id }}" />
                    <livewire:dashboard.controls.zoom-call :onesiBox="$onesiBox" wire:key="zoom-{{ $onesiBox->id }}" />
                    <livewire:dashboard.controls.meeting-schedule :onesi-box="$onesiBox" wire:key="meeting-schedule-{{ $onesiBox->id }}" />
                </div>
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

    {{-- Admin Section (visible only to admin and super-admin) --}}
    @if($this->isAdmin)
        <div class="mt-8 pt-6 border-t border-zinc-300 dark:border-zinc-600">
            <flux:heading size="lg" class="mb-4">
                <flux:icon name="shield-check" class="w-5 h-5 inline-block mr-2" />
                Amministrazione
            </flux:heading>

            <div class="space-y-4">
                {{-- System Information --}}
                <livewire:dashboard.controls.system-info :onesiBox="$onesiBox" wire:key="system-info-{{ $onesiBox->id }}" />

                {{-- Network Information --}}
                <livewire:dashboard.controls.network-info :onesiBox="$onesiBox" wire:key="network-info-{{ $onesiBox->id }}" />

                @if($this->isOnline)
                    {{-- System Controls --}}
                    <livewire:dashboard.controls.system-controls :onesiBox="$onesiBox" wire:key="system-{{ $onesiBox->id }}" />

                    {{-- Log Viewer --}}
                    <livewire:dashboard.controls.log-viewer :onesiBox="$onesiBox" wire:key="logs-{{ $onesiBox->id }}" />
                @endif
            </div>
        </div>
    @endif
</div>
