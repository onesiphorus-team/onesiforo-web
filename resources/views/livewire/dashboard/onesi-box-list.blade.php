<div class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8" wire:poll.10s.visible>
    <flux:heading size="xl" class="mb-6">Le tue OnesiBox</flux:heading>

    @forelse($this->onesiBoxes as $box)
        <div wire:key="box-{{ $box->id }}" class="mb-4">
            <div
                class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:shadow-md transition-shadow cursor-pointer"
                wire:click="selectOnesiBox({{ $box->id }})"
            >
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <flux:heading size="lg">{{ $box->name }}</flux:heading>
                        @if($box->recipient)
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                                {{ $box->recipient->full_name }}
                            </flux:text>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 ml-4">
                        @if($box->isOnline())
                            <flux:badge color="green" size="sm">Online</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Offline</flux:badge>
                        @endif

                        @if($box->status)
                            <flux:badge :color="$box->status->getColor()" size="sm">
                                {{ $box->status->getLabel() }}
                            </flux:badge>
                        @endif

                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                    </div>
                </div>
            </div>
        </div>
    @empty
        <flux:callout icon="information-circle">
            <flux:callout.heading>Nessuna OnesiBox assegnata</flux:callout.heading>
            <flux:callout.text>
                Non hai ancora OnesiBox assegnate. Contatta l'amministratore per essere associato a un dispositivo.
            </flux:callout.text>
        </flux:callout>
    @endforelse
</div>
