<div class="space-y-4">
    <flux:heading size="sm">
        <flux:icon name="bookmark" class="w-5 h-5 inline-block mr-2" />
        Playlist Salvate
    </flux:heading>

    @if($this->savedPlaylists->isNotEmpty())
        <div class="space-y-2">
            @foreach($this->savedPlaylists as $playlist)
                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg" wire:key="saved-{{ $playlist->id }}">
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium truncate">{{ $playlist->name }}</flux:text>
                        <flux:text class="text-xs text-zinc-500">
                            {{ $playlist->items_count }} video &middot; {{ $playlist->created_at->diffForHumans() }}
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                        <flux:button
                            size="xs"
                            variant="subtle"
                            icon="play"
                            wire:click="loadPlaylist({{ $playlist->id }})"
                        >
                            Carica
                        </flux:button>
                        @if($this->canControl)
                            <flux:button
                                size="xs"
                                variant="subtle"
                                icon="trash"
                                wire:click="deletePlaylist({{ $playlist->id }})"
                                wire:confirm="Sei sicuro di voler eliminare questa playlist?"
                                class="text-red-500 hover:text-red-700"
                            />
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <flux:text class="text-sm text-zinc-400 dark:text-zinc-500 text-center py-2">
            Nessuna playlist salvata.
        </flux:text>
    @endif

    {{-- Save current playlist --}}
    @if($this->canControl && count($videoUrls) > 0)
        <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700">
            <form wire:submit="savePlaylist" class="flex gap-2">
                <div class="flex-1">
                    <flux:input
                        wire:model="playlistName"
                        placeholder="Nome playlist..."
                        size="sm"
                    />
                </div>
                <flux:button
                    type="submit"
                    size="sm"
                    variant="primary"
                    icon="bookmark"
                    wire:loading.attr="disabled"
                    wire:target="savePlaylist"
                >
                    Salva
                </flux:button>
            </form>
            @error('playlistName')
                <flux:text class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</flux:text>
            @enderror
        </div>
    @endif
</div>
