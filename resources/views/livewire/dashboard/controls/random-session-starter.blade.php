@php
    $isAudio = $mediaType === 'audio';
    $kindLabel = $isAudio ? 'audio' : 'video';
@endphp
<div class="space-y-5">
    <div>
        <flux:heading size="lg">{{ $isAudio ? 'Audio' : 'Video' }} casuali</flux:heading>
        <flux:text class="mt-1">
            Il box mostrerà {{ $kindLabel }} di jw.org scelti a caso per il tempo indicato,
            dando la precedenza a quelli mai mostrati o mostrati meno di recente.
        </flux:text>
    </div>

    @if($this->categories === null)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="Impossibile caricare le categorie da jw.org">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="$refresh">Riprova</flux:button>
            </x-slot>
        </flux:callout>
    @else
        @if($this->activeSession)
            <flux:callout variant="warning" icon="information-circle">
                <flux:callout.text>C'è già una sessione in corso: avviandone una nuova, quella attuale verrà interrotta.</flux:callout.text>
            </flux:callout>
        @endif

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-white">Per quanto tempo?</legend>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach(\App\Livewire\Dashboard\Controls\RandomSessionStarter::DURATION_OPTIONS as $option)
                    <button type="button"
                            wire:key="duration-{{ $option }}"
                            wire:click="$set('durationMinutes', {{ $option }})"
                            aria-pressed="{{ $durationMinutes === $option ? 'true' : 'false' }}"
                            class="min-h-12 rounded-lg border px-3 text-base font-medium {{ $durationMinutes === $option
                                ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-500'
                                : 'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-700' }}">
                        {{ $this->formatDuration($option) }}
                    </button>
                @endforeach
            </div>
            <flux:error name="durationMinutes" />
        </fieldset>

        <details class="group rounded-lg border border-zinc-200 dark:border-zinc-700">
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-2">
                <span class="text-sm font-medium">
                    Categorie ({{ count($selectedCategories) }} di {{ count($this->categories) }})
                </span>
                <flux:icon name="chevron-down" class="size-5 text-zinc-400 transition group-open:rotate-180" />
            </summary>
            <div class="space-y-3 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:button size="sm" variant="subtle" wire:click="selectAllCategories">Seleziona tutte</flux:button>
                <flux:checkbox.group wire:model.live="selectedCategories">
                    @foreach($this->categories as $category)
                        <flux:checkbox wire:key="random-category-{{ $category['key'] }}" value="{{ $category['key'] }}" label="{{ $category['name'] }}" />
                    @endforeach
                </flux:checkbox.group>
            </div>
        </details>
        <flux:error name="selectedCategories" />

        @unless($isAudio)
            <flux:switch wire:model="includeLongItems" label="Includi video lunghi (oltre 30 minuti)" align="left" />
        @endunless

        @if($this->canControl)
            <flux:button variant="primary" color="indigo"
                         icon="play"
                         class="w-full py-4 text-base font-medium"
                         wire:click="start"
                         wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="start">Avvia {{ $kindLabel }} casuali</span>
                <span wire:loading wire:target="start">Preparo la sessione...</span>
            </flux:button>
        @endif
    @endif
</div>
