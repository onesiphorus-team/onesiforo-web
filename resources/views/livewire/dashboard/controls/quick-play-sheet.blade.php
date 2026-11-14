<div>
    {{-- Tighter padding on phones. The dialog keeps its first-render attributes (wire:ignore.self), so per-tab sizing lives on the inner content. --}}
    <flux:modal wire:model="open" variant="flyout" position="bottom" :closable="false" class="w-full p-4! sm:p-8! md:max-w-xl">
        {{-- Our own top bar, pinned while the sheet scrolls (Flux's close button would scroll away with the content).
             The negative top cancels the sheet padding, so nothing scrolls through above the bar. --}}
        <div data-slot="sheet-header" class="sticky -top-4 z-20 sm:-top-8 -mx-4 -mt-4 flex h-14 items-center gap-2 border-b border-zinc-200 bg-white px-4 sm:-mx-8 sm:-mt-8 sm:px-8 dark:border-zinc-700 dark:bg-zinc-800">
            @if($tab === null)
                <flux:heading size="lg" class="min-w-0 flex-1 truncate">Cosa vuoi mostrare sul box?</flux:heading>
            @else
                <flux:button wire:click="back" variant="subtle" icon="arrow-left" class="-ml-2">Indietro</flux:button>
                <span class="flex-1"></span>
            @endif
            <flux:button wire:click="close" variant="subtle" icon="x-mark" class="-mr-2" aria-label="Chiudi" />
        </div>

        @if($tab === null)
            @php
                // Every choice says what it does in plain words: caregivers should not have to guess.
                $mainChoices = [
                    ['jw-video', 'film', 'Video', 'Scegli un video tra quelli di jw.org'],
                    ['jw-audio', 'musical-note', 'Audio', 'Cantici, letture della Bibbia e altri audio'],
                    ['zoom', 'video-camera', 'Zoom e adunanze', "Collega il box a un'adunanza o a una riunione"],
                    ['random-video', 'sparkles', 'Video casuali', 'Una serie di video scelti per te, per il tempo che decidi'],
                    ['random-audio', 'sparkles', 'Audio casuali', 'Una serie di audio scelti per te, per il tempo che decidi'],
                ];
                $moreChoices = [
                    ['video', 'link', 'Video da un link', 'Se hai il link di un video di jw.org'],
                    ['audio', 'link', 'Audio da un link', 'Se hai il link di un audio di jw.org'],
                    ['stream', 'play-circle', 'Programma di JW Stream', 'Per i programmi condivisi da stream.jw.org'],
                ];
                $choiceRow = 'flex w-full items-center gap-3 rounded-xl px-2 py-2.5 text-left hover:bg-zinc-50 active:bg-zinc-100 dark:hover:bg-zinc-700/40';
                $choiceIcon = 'flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200';
            @endphp
            <div class="mt-2 flex flex-col" x-data="{ showMore: false }">
                @foreach($mainChoices as [$choiceTab, $choiceIconName, $choiceLabel, $choiceHint])
                    <button type="button" wire:click="selectTab('{{ $choiceTab }}')" wire:key="choice-{{ $choiceTab }}" class="{{ $choiceRow }}">
                        <span class="{{ $choiceIcon }}" aria-hidden="true"><flux:icon :name="$choiceIconName" class="size-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-zinc-900 dark:text-white">{{ $choiceLabel }}</span>
                            <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $choiceHint }}</span>
                        </span>
                        <flux:icon.loading class="size-5 text-zinc-400" wire:loading wire:target="selectTab('{{ $choiceTab }}')" />
                        <flux:icon name="chevron-right" class="size-5 shrink-0 text-zinc-300 dark:text-zinc-500" wire:loading.remove wire:target="selectTab('{{ $choiceTab }}')" />
                    </button>
                @endforeach

                <button type="button" x-on:click="showMore = ! showMore" x-bind:aria-expanded="showMore" class="{{ $choiceRow }} mt-1 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                    <span class="{{ $choiceIcon }}" aria-hidden="true"><flux:icon name="ellipsis-horizontal" class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium text-zinc-900 dark:text-white">Altro</span>
                        <span class="block text-sm text-zinc-500 dark:text-zinc-400">Link incollati e programmi di JW Stream</span>
                    </span>
                    <flux:icon name="chevron-down" class="size-5 shrink-0 text-zinc-400 transition-transform" x-bind:class="showMore && 'rotate-180'" />
                </button>

                <div x-show="showMore" x-collapse class="ml-7 flex flex-col border-l border-zinc-200 pl-3 dark:border-zinc-700">
                    @foreach($moreChoices as [$choiceTab, $choiceIconName, $choiceLabel, $choiceHint])
                        <button type="button" wire:click="selectTab('{{ $choiceTab }}')" wire:key="choice-{{ $choiceTab }}" class="{{ $choiceRow }}">
                            <flux:icon :name="$choiceIconName" class="size-5 shrink-0 text-zinc-500" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-zinc-900 dark:text-white">{{ $choiceLabel }}</span>
                                <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $choiceHint }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Nearly full height on phones once a section is open, so the sheet does not jump in size while browsing --}}
            <div class="mt-4 max-sm:min-h-[calc(85dvh-6rem)]">
                @switch($tab)
                    @case('jw-video')
                        <livewire:dashboard.controls.jw-media-browser :onesiBox="$onesiBox" mediaType="video" wire:key="qps-jw-video-{{ $onesiBox->id }}" />
                        @break
                    @case('jw-audio')
                        <livewire:dashboard.controls.jw-media-browser :onesiBox="$onesiBox" mediaType="audio" wire:key="qps-jw-audio-{{ $onesiBox->id }}" />
                        @break
                    @case('random-video')
                        <livewire:dashboard.controls.random-session-starter :onesiBox="$onesiBox" mediaType="video" wire:key="qps-random-video-{{ $onesiBox->id }}" />
                        @break
                    @case('random-audio')
                        <livewire:dashboard.controls.random-session-starter :onesiBox="$onesiBox" mediaType="audio" wire:key="qps-random-audio-{{ $onesiBox->id }}" />
                        @break
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
                    @default
                        <flux:text class="text-sm text-zinc-500">Tab: {{ $tab }}</flux:text>
                @endswitch
            </div>
        @endif
    </flux:modal>
</div>
