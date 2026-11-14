@php
    $choice = 'flex min-h-16 w-full items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2.5 text-left hover:bg-zinc-50 active:bg-zinc-100 disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-700/40';
@endphp
<div class="space-y-6">
    <div>
        <flux:heading size="lg">Riunione Zoom</flux:heading>
        <flux:text class="mt-1">
            Il box si collegherà con il nome <strong class="text-zinc-800 dark:text-zinc-100">{{ $this->getParticipantName() }}</strong>.
        </flux:text>
    </div>

    {{-- One-tap choices --}}
    @if($this->congregation || $this->savedMeetings->isNotEmpty())
        <ul class="space-y-2" aria-label="Riunioni del box">
            @if($congregation = $this->congregation)
                <li>
                    <button type="button"
                            wire:click="joinCongregationMeeting"
                            wire:loading.attr="disabled"
                            @disabled(! $this->canControl)
                            data-slot="congregation-meeting"
                            class="{{ $choice }}">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                            <flux:icon name="building-library" variant="mini" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-zinc-900 dark:text-white">Adunanza {{ $congregation->name }}</span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">La congregazione del box</span>
                        </span>
                        <flux:icon.loading class="size-5 text-indigo-500" wire:loading wire:target="joinCongregationMeeting" />
                        <flux:icon name="arrow-right-end-on-rectangle" class="size-5 shrink-0 text-indigo-500" wire:loading.remove wire:target="joinCongregationMeeting" />
                    </button>
                </li>
            @endif

            @foreach($this->savedMeetings as $meeting)
                <li class="flex items-center gap-2" wire:key="zoom-meeting-{{ $meeting->id }}">
                    <button type="button"
                            wire:click="joinSavedMeeting({{ $meeting->id }})"
                            wire:loading.attr="disabled"
                            @disabled(! $this->canControl)
                            class="{{ $choice }} min-w-0 flex-1">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                            <flux:icon name="video-camera" variant="mini" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-zinc-900 dark:text-white">{{ $meeting->name }}</span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $meeting->last_used_at ? 'Usata '.$meeting->last_used_at->diffForHumans() : 'Mai usata' }}
                            </span>
                        </span>
                        <flux:icon.loading class="size-5 text-indigo-500" wire:loading wire:target="joinSavedMeeting({{ $meeting->id }})" />
                        <flux:icon name="arrow-right-end-on-rectangle" class="size-5 shrink-0 text-indigo-500" wire:loading.remove wire:target="joinSavedMeeting({{ $meeting->id }})" />
                    </button>
                    @if($this->canControl)
                        <flux:button wire:click="deleteSavedMeeting({{ $meeting->id }})"
                                     wire:confirm="Togliere «{{ $meeting->name }}» dalle riunioni salvate?"
                                     variant="subtle"
                                     icon="trash"
                                     class="shrink-0"
                                     aria-label="Togli {{ $meeting->name }} dalle riunioni salvate" />
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="flex items-center gap-3 text-xs font-medium uppercase tracking-wider text-zinc-400" aria-hidden="true">
            <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
            oppure
            <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
        </div>
    @endif

    {{-- Any other meeting, by link --}}
    <form wire:submit="startCall" class="space-y-4" x-data>
        <flux:input
            wire:model="zoomUrl"
            label="Incolla il link della riunione"
            type="url"
            inputmode="url"
            autocomplete="off"
            autocapitalize="off"
            placeholder="https://us05web.zoom.us/j/..."
            class="[&_input]:py-3 [&_input]:text-base"
        />

        <flux:checkbox wire:model="saveMeeting" label="Salva questa riunione per la prossima volta" />

        <div x-show="$wire.saveMeeting" x-collapse x-cloak>
            <flux:input wire:model="meetingName" label="Nome della riunione" placeholder="Es. Adunanza Loreto" maxlength="100" class="[&_input]:text-base" />
        </div>

        <flux:button type="submit"
                     variant="primary"
                     color="indigo"
                     class="w-full py-4 text-base font-medium"
                     icon="video-camera"
                     wire:loading.attr="disabled"
                     wire:target="startCall">
            <span wire:loading.remove wire:target="startCall">Collegati alla riunione</span>
            <span wire:loading wire:target="startCall">Connessione...</span>
        </flux:button>
    </form>
</div>
