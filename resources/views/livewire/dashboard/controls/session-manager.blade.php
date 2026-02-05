<div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700" wire:poll.10s>
    <flux:heading size="sm" class="mb-4">
        <flux:icon name="play-circle" class="w-5 h-5 inline-block mr-2" />
        Sessione Playlist
    </flux:heading>

    @if($this->activeSession)
        {{-- Active session display --}}
        <div class="space-y-4">
            <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                <div class="flex items-center gap-2 text-green-700 dark:text-green-300 mb-2">
                    <flux:icon name="play-circle" class="w-5 h-5" />
                    <span class="font-medium">Sessione in corso</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <flux:text class="text-zinc-500">Durata</flux:text>
                        <flux:text class="font-medium">{{ $this->formatDuration($this->activeSession->duration_minutes) }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">Tempo rimasto</flux:text>
                        <flux:text class="font-medium">{{ $this->formatDuration((int) ceil($this->activeSession->timeRemainingSeconds() / 60)) }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">Video riprodotti</flux:text>
                        <flux:text class="font-medium">{{ $this->activeSession->items_played }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">Posizione</flux:text>
                        <flux:text class="font-medium">{{ $this->activeSession->current_position + 1 }} / {{ $this->activeSession->playlist->items()->count() }}</flux:text>
                    </div>
                </div>
                @if($this->activeSession->currentItem())
                    <div class="mt-2 pt-2 border-t border-green-200 dark:border-green-800">
                        <flux:text class="text-xs text-zinc-500">Video attuale</flux:text>
                        <flux:text class="text-sm truncate">{{ $this->activeSession->currentItem()->title ?? $this->activeSession->currentItem()->media_url }}</flux:text>
                    </div>
                @endif
            </div>

            @if($this->canControl)
                <flux:button
                    variant="danger"
                    class="w-full py-4 text-base font-medium"
                    icon="stop"
                    wire:click="stopSession"
                    wire:loading.attr="disabled"
                    wire:confirm="Sei sicuro di voler interrompere la sessione?"
                >
                    <span wire:loading.remove wire:target="stopSession">Interrompi Sessione</span>
                    <span wire:loading wire:target="stopSession">Interruzione in corso...</span>
                </flux:button>
            @endif
        </div>
    @else
        {{-- New session form --}}
        <div class="space-y-4">
            {{-- Saved Playlists --}}
            <livewire:dashboard.controls.saved-playlists :onesiBox="$onesiBox" wire:key="saved-playlists-{{ $onesiBox->id }}" />

            {{-- Playlist Builder --}}
            <livewire:dashboard.controls.playlist-builder :onesiBox="$onesiBox" wire:model.live="videoUrls" wire:key="playlist-builder-{{ $onesiBox->id }}" />

            {{-- Duration selector --}}
            <flux:field>
                <flux:label>Durata sessione</flux:label>
                <flux:select wire:model="durationMinutes">
                    @foreach($durationOptions as $option)
                        <flux:select.option value="{{ $option }}">{{ $this->formatDuration($option) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Start button --}}
            @if($this->canControl)
                <flux:button
                    variant="primary"
                    class="w-full py-4 text-base font-medium"
                    icon="play"
                    wire:click="startSession"
                    wire:loading.attr="disabled"
                    :disabled="count($videoUrls) === 0"
                >
                    <span wire:loading.remove wire:target="startSession">Avvia Sessione ({{ count($videoUrls) }} video)</span>
                    <span wire:loading wire:target="startSession">Avvio in corso...</span>
                </flux:button>
            @endif
        </div>
    @endif
</div>
