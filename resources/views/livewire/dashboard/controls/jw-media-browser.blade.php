@php
    $isAudio = $mediaType === 'audio';
    $kindLabel = $isAudio ? 'Audio' : 'Video';
@endphp
<div class="space-y-4">
    @if($this->rootCategory === null)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="Impossibile caricare i contenuti da jw.org">
            <flux:callout.text>Controlla la connessione e riprova tra poco.</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" wire:click="$refresh">Riprova</flux:button>
            </x-slot>
        </flux:callout>
    @elseif($openCategory === null)
        {{-- Category list --}}
        <div>
            <flux:heading size="lg">{{ $kindLabel }} da jw.org</flux:heading>
            <flux:text class="mt-1">Scegli una categoria, poi tocca un {{ $isAudio ? 'audio' : 'video' }} per mostrarlo subito sul box, oppure il pulsante con il + per metterlo in una playlist da far partire dopo.</flux:text>
        </div>

        <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach($this->rootCategory['subcategories'] as $category)
                @php $isExpanded = $expandedCategory === $category['key']; @endphp
                <div wire:key="jw-category-{{ $category['key'] }}">
                    <button type="button"
                            wire:click="selectCategory(@js($category['key']))"
                            class="flex min-h-14 w-full items-center justify-between gap-3 px-4 py-3 text-left text-base hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                            @if($category['is_container']) aria-expanded="{{ $isExpanded ? 'true' : 'false' }}" @endif>
                        <span class="font-medium">{{ $category['name'] }}</span>
                        <span class="flex items-center gap-2 text-zinc-400">
                            <flux:icon.loading class="size-5" wire:loading wire:target="selectCategory(@js($category['key']))" />
                            @if($category['is_container'])
                                <flux:icon name="{{ $isExpanded ? 'chevron-up' : 'chevron-down' }}" class="size-5" wire:loading.remove wire:target="selectCategory(@js($category['key']))" />
                            @else
                                <flux:icon name="chevron-right" class="size-5" wire:loading.remove wire:target="selectCategory(@js($category['key']))" />
                            @endif
                        </span>
                    </button>

                    @if($isExpanded)
                        <div class="bg-zinc-50 pb-2 dark:bg-zinc-900/40">
                            @if($this->expandedCategoryData === null)
                                <flux:text class="px-6 py-3 text-sm">Impossibile caricare questa categoria. Riprova.</flux:text>
                            @else
                                @foreach($this->expandedCategoryData['subcategories'] as $subcategory)
                                    <button type="button"
                                            wire:key="jw-subcategory-{{ $subcategory['key'] }}"
                                            wire:click="openSubcategory(@js($subcategory['key']))"
                                            class="flex min-h-12 w-full items-center justify-between gap-3 py-2 pl-8 pr-4 text-left hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                                        <span>{{ $subcategory['name'] }}</span>
                                        <span class="flex shrink-0 items-center gap-2 text-sm text-zinc-500">
                                            @if(count($subcategory['media']) > 0)
                                                {{ count($subcategory['media']) }}
                                            @endif
                                            <flux:icon.loading class="size-4" wire:loading wire:target="openSubcategory(@js($subcategory['key']))" />
                                            <flux:icon name="chevron-right" class="size-4" wire:loading.remove wire:target="openSubcategory(@js($subcategory['key']))" />
                                        </span>
                                    </button>
                                @endforeach
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        {{-- Media list of the open category --}}
        @if($this->openCategoryData === null)
            <flux:button wire:click="closeCategory" variant="subtle" icon="arrow-left" class="-ml-2">Categorie</flux:button>
            <flux:callout variant="danger" icon="exclamation-triangle" heading="Impossibile caricare questa categoria">
                <x-slot name="actions">
                    <flux:button size="sm" wire:click="$refresh">Riprova</flux:button>
                </x-slot>
            </flux:callout>
        @else
            {{-- Pinned right under the sheet's top bar (56px tall, stuck at minus the sheet padding),
                 so the way back to the categories stays at hand while scrolling --}}
            <div data-slot="category-header" class="sticky top-10 z-10 sm:top-6 -mx-4 -mt-4 flex items-center gap-1 border-b border-zinc-100 bg-white px-4 py-1 sm:-mx-8 sm:px-8 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:button wire:click="closeCategory" variant="subtle" icon="chevron-left" class="-ml-2 shrink-0" aria-label="Torna alle categorie" />
                <flux:heading size="lg" class="min-w-0 truncate">{{ $this->openCategoryData['name'] }}</flux:heading>
            </div>

            <flux:input wire:model.live.debounce.300ms="search"
                        icon="magnifying-glass"
                        placeholder="Cerca per titolo..."
                        clearable
                        class="[&_input]:text-base" />

            @if(! $this->canControl)
                <flux:text class="text-sm">Hai solo il permesso di visualizzazione: non puoi avviare contenuti.</flux:text>
            @endif

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700" aria-label="Elenco {{ strtolower($kindLabel) }}">
                @forelse($this->visibleItems as $row)
                    @php
                        $item = $row['item'];
                        $isQueued = isset($this->queuedKeys[$item['key']]);
                    @endphp
                    <li wire:key="jw-item-{{ $item['key'] }}" class="flex items-center gap-1">
                        <button type="button"
                                wire:click="play(@js($item['key']))"
                                wire:loading.attr="disabled"
                                @disabled(! $this->canControl)
                                class="flex min-w-0 flex-1 items-center gap-3 py-3 text-left hover:bg-zinc-50 disabled:cursor-not-allowed dark:hover:bg-zinc-700/50"
                                aria-label="Mostra {{ $item['title'] }}">
                            <span class="relative shrink-0 overflow-hidden rounded-md bg-zinc-200 dark:bg-zinc-700 {{ $isAudio ? 'size-14 sm:size-16' : 'aspect-video w-24 sm:w-28' }}">
                                @if($item['thumbnail_url'])
                                    <img src="{{ $item['thumbnail_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="size-full object-cover">
                                @else
                                    <flux:icon name="{{ $isAudio ? 'musical-note' : 'film' }}" class="absolute inset-0 m-auto size-6 text-zinc-400" />
                                @endif
                                <span class="absolute bottom-1 right-1 rounded bg-black/75 px-1 text-[11px] font-medium text-white">{{ $item['duration_formatted'] }}</span>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-3 text-sm font-medium text-zinc-900 sm:line-clamp-2 dark:text-white">{{ $item['title'] }}</span>
                                <span class="mt-1 block">
                                    @if($row['never_shown'])
                                        <flux:badge size="sm" color="green" icon="sparkles">{{ $row['last_shown_label'] }}</flux:badge>
                                    @elseif($row['is_recent'])
                                        <flux:badge size="sm" color="amber" icon="clock">Mostrato {{ lcfirst($row['last_shown_label']) }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc" icon="clock">Mostrato {{ lcfirst($row['last_shown_label']) }}</flux:badge>
                                    @endif
                                </span>
                            </span>

                            <span class="shrink-0 text-indigo-600 dark:text-indigo-400">
                                <flux:icon.loading class="size-7" wire:loading wire:target="play(@js($item['key']))" />
                                <flux:icon name="play-circle" class="size-8" wire:loading.remove wire:target="play(@js($item['key']))" />
                            </span>
                        </button>

                        @if($this->canControl)
                            <button type="button"
                                    wire:click="toggleQueued(@js($item['key']))"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleQueued(@js($item['key']))"
                                    data-slot="queue-toggle"
                                    aria-pressed="{{ $isQueued ? 'true' : 'false' }}"
                                    aria-label="{{ $isQueued ? 'Togli dalla playlist' : 'Aggiungi alla playlist' }}: {{ $item['title'] }}"
                                    title="{{ $isQueued ? 'Togli dalla playlist' : 'Aggiungi alla playlist' }}"
                                    class="flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                                @if($isQueued)
                                    <flux:icon name="check-circle" variant="solid" class="size-7 text-green-600 dark:text-green-400" />
                                @else
                                    <span class="relative inline-flex size-6 items-center justify-center rounded-full border-2 border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400">
                                        <flux:icon name="play" variant="micro" class="size-3" />
                                        <span class="absolute -right-1.5 -top-1.5 flex size-3.5 items-center justify-center rounded-full bg-indigo-600 text-white ring-2 ring-white dark:bg-indigo-400 dark:text-zinc-900 dark:ring-zinc-800">
                                            <flux:icon name="plus" variant="micro" class="size-2.5" />
                                        </span>
                                    </span>
                                @endif
                            </button>
                        @endif
                    </li>
                @empty
                    <li class="py-6 text-center">
                        <flux:text>Nessun contenuto trovato.</flux:text>
                    </li>
                @endforelse
            </ul>
        @endif
    @endif

    @if($this->canControl && $queue !== [])
        {{-- Temporary playlist built with the + buttons --}}
        <div data-slot="queue-bar"
             class="sticky bottom-0 z-10 rounded-xl border border-indigo-200 bg-white p-3 shadow-lg dark:border-indigo-800 dark:bg-zinc-800">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2 sm:justify-start">
                        <flux:heading size="sm">Playlist ({{ count($queue) }})</flux:heading>
                        <flux:button wire:click="clearQueue" variant="subtle" size="sm">Svuota</flux:button>
                    </div>
                    <ol class="mt-1 max-h-32 space-y-1 overflow-y-auto pr-1 sm:max-h-36" aria-label="Contenuti nella playlist">
                        @foreach($queue as $position => $queuedItem)
                            <li wire:key="jw-queue-{{ $queuedItem['key'] }}" class="flex items-center gap-2">
                                <span class="w-5 shrink-0 text-right text-xs tabular-nums text-zinc-400">{{ $position + 1 }}.</span>
                                @if($queuedItem['thumbnail_url'])
                                    <img src="{{ $queuedItem['thumbnail_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer"
                                         class="shrink-0 rounded bg-zinc-200 object-cover dark:bg-zinc-700 {{ $isAudio ? 'size-8' : 'aspect-video w-12' }}">
                                @endif
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $queuedItem['title'] }}</span>
                                <span class="shrink-0 text-xs tabular-nums text-zinc-500">{{ $queuedItem['duration_formatted'] }}</span>
                                <button type="button"
                                        wire:click="removeFromQueue(@js($queuedItem['key']))"
                                        aria-label="Togli dalla playlist: {{ $queuedItem['title'] }}"
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200">
                                    <flux:icon name="x-mark" variant="mini" class="size-4" />
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="flex shrink-0 items-center justify-between gap-3 border-t border-zinc-100 pt-3 sm:flex-col sm:items-end sm:border-0 sm:pt-0 dark:border-zinc-700">
                    <div class="sm:text-right">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Durata totale</p>
                        <p class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->queueDurationLabel }}</p>
                    </div>
                    <flux:button wire:click="startQueue"
                                 wire:loading.attr="disabled"
                                 wire:target="startQueue"
                                 variant="primary"
                                 color="indigo"
                                 icon="play">
                        <span wire:loading.remove wire:target="startQueue">Avvia playlist</span>
                        <span wire:loading wire:target="startQueue">Avvio...</span>
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
