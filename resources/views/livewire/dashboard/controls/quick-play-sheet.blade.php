<div>
    <flux:modal wire:model="open" variant="flyout" position="bottom" class="max-w-md">
        @if($tab === null)
            <flux:heading size="lg">Cosa vuoi riprodurre?</flux:heading>
            <div class="mt-4 flex flex-col gap-2">
                <flux:button wire:click="selectTab('audio')" variant="ghost" icon="musical-note" class="justify-start">Audio da URL</flux:button>
                <flux:button wire:click="selectTab('video')" variant="ghost" icon="video-camera" class="justify-start">Video da URL</flux:button>
                <flux:button wire:click="selectTab('stream')" variant="ghost" icon="play" class="justify-start">Stream YouTube</flux:button>
                <flux:button wire:click="selectTab('playlists')" variant="ghost" icon="queue-list" class="justify-start">Dalle playlist salvate</flux:button>
                <flux:button wire:click="selectTab('zoom')" variant="ghost" icon="phone" class="justify-start">Avvia chiamata Zoom</flux:button>
            </div>
        @else
            <flux:button wire:click="back" variant="subtle" icon="arrow-left" size="sm">Indietro</flux:button>
            <div class="mt-4">
                @switch($tab)
                    @case('audio')
                        <livewire:dashboard.controls.audio-player :onesiBox="$onesiBox" wire:key="qps-audio-{{ $onesiBox->id }}" />
                        @break
                    @case('video')
                        <livewire:dashboard.controls.video-player :onesiBox="$onesiBox" wire:key="qps-video-{{ $onesiBox->id }}" />
                        @break
                    @case('stream')
                        <livewire:dashboard.controls.stream-player :onesiBox="$onesiBox" wire:key="qps-stream-{{ $onesiBox->id }}" />
                        @break
                    @case('zoom')
                        <livewire:dashboard.controls.zoom-call :onesiBox="$onesiBox" wire:key="qps-zoom-{{ $onesiBox->id }}" />
                        @break
                    @case('playlists')
                        <livewire:dashboard.controls.saved-playlists :onesiBox="$onesiBox" wire:key="qps-playlists-{{ $onesiBox->id }}" />
                        @break
                    @default
                        <flux:text class="text-sm text-zinc-500">Tab: {{ $tab }}</flux:text>
                @endswitch
            </div>
        @endif
    </flux:modal>
</div>
