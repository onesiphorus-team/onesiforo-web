<div class="space-y-4">
    {{-- Source type toggle --}}
    <div class="flex w-full gap-2">
        <flux:button
            class="flex-1"
            size="sm"
            :variant="$sourceType === 'manual' ? 'primary' : 'subtle'"
            wire:click="switchSourceType('manual')"
            icon="link"
        >
            URL Manuali
        </flux:button>
        <flux:button
            class="flex-1"
            size="sm"
            :variant="$sourceType === 'jworg_section' ? 'primary' : 'subtle'"
            wire:click="switchSourceType('jworg_section')"
            icon="globe-alt"
        >
            Sezione JW.org
        </flux:button>
    </div>

    @if($sourceType === 'manual')
        {{-- Manual URL input --}}
        <form wire:submit="addUrl" class="flex gap-2">
            <div class="flex-1">
                <flux:input
                    wire:model="newUrl"
                    type="url"
                    inputmode="url"
                    autocomplete="off"
                    autocapitalize="off"
                    placeholder="https://www.jw.org/it/..."
                    class="[&_input]:text-base [&_input]:py-3"
                />
            </div>
            <flux:button
                type="submit"
                variant="primary"
                icon="plus"
                wire:loading.attr="disabled"
                wire:target="addUrl"
            >
                Aggiungi
            </flux:button>
        </form>

        @error('newUrl')
            <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    @else
        {{-- JW.org section input --}}
        <form wire:submit="extractFromJwOrg" class="flex gap-2">
            <div class="flex-1">
                <flux:input
                    wire:model="sectionUrl"
                    type="url"
                    inputmode="url"
                    autocomplete="off"
                    autocapitalize="off"
                    placeholder="https://www.jw.org/it/biblioteca/video/#it/categories/..."
                    description="Incolla l'URL di una sezione video di jw.org"
                    class="[&_input]:text-base [&_input]:py-3"
                />
            </div>
            <flux:button
                type="submit"
                variant="primary"
                icon="arrow-down-tray"
                wire:loading.attr="disabled"
                wire:target="extractFromJwOrg"
            >
                <span wire:loading.remove wire:target="extractFromJwOrg">Estrai</span>
                <span wire:loading wire:target="extractFromJwOrg">Estrazione...</span>
            </flux:button>
        </form>

        @error('sectionUrl')
            <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        {{-- Extraction preview --}}
        @if(count($extractedVideos) > 0)
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex items-center justify-between mb-2">
                    <flux:text class="font-medium text-blue-700 dark:text-blue-300">
                        {{ $extractedCategoryName }}
                    </flux:text>
                    <flux:badge color="blue" size="sm">{{ count($extractedVideos) }} video &middot; {{ $extractedTotalDuration }}</flux:badge>
                </div>
                <div class="max-h-40 overflow-y-auto space-y-1">
                    @foreach($extractedVideos as $index => $video)
                        <div class="flex items-center gap-2 text-sm" wire:key="extracted-{{ $index }}">
                            <span class="flex-shrink-0 text-zinc-400">{{ $index + 1 }}.</span>
                            <flux:text class="truncate">{{ $video['title'] }}</flux:text>
                            <flux:text class="flex-shrink-0 text-zinc-400 text-xs">{{ $video['duration_formatted'] }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- URL List (common for both modes) --}}
    @if(count($videoUrls) > 0 && $sourceType === 'manual')
        <div class="space-y-2">
            @foreach($videoUrls as $index => $url)
                <div class="flex items-center gap-2 p-2 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg" wire:key="url-{{ $index }}">
                    <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-600 text-xs font-medium">
                        {{ $index + 1 }}
                    </span>
                    <flux:text class="flex-1 truncate text-sm">{{ $url }}</flux:text>
                    <div class="flex items-center gap-1">
                        @if($index > 0)
                            <flux:button size="xs" variant="subtle" icon="chevron-up" wire:click="moveUp({{ $index }})" />
                        @endif
                        @if($index < count($videoUrls) - 1)
                            <flux:button size="xs" variant="subtle" icon="chevron-down" wire:click="moveDown({{ $index }})" />
                        @endif
                        <flux:button size="xs" variant="subtle" icon="x-mark" wire:click="removeUrl({{ $index }})" class="text-red-500 hover:text-red-700" />
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-between">
            <flux:text class="text-sm text-zinc-500">{{ count($videoUrls) }} video nella playlist</flux:text>
            <flux:button size="xs" variant="subtle" wire:click="clearAll" icon="trash">
                Svuota tutto
            </flux:button>
        </div>
    @endif
</div>
