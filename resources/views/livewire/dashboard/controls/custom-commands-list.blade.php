<div>
    @php($commands = $this->commands)

    @if($this->canControl && $commands->isNotEmpty())
        <x-box-section :title="__('Comandi personalizzati')" subtitle="Azioni preparate apposta per questo box" icon="bolt">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($commands as $command)
                    @php($iconName = $command->icon ? str_replace(['heroicon-o-', 'heroicon-s-'], '', $command->icon) : 'bolt')

                    <flux:button
                        wire:click="run({{ $command->id }})"
                        wire:loading.attr="disabled"
                        wire:target="run({{ $command->id }})"
                        :disabled="! $this->onesiBox->isOnline()"
                        variant="outline"
                        icon="{{ $iconName }}"
                        class="justify-start text-left"
                    >
                        <div class="flex flex-col items-start">
                            <span class="font-medium">{{ $command->name }}</span>
                            @if($command->description)
                                <span class="text-xs opacity-80 font-normal">{{ $command->description }}</span>
                            @endif
                        </div>
                    </flux:button>
                @endforeach
            </div>

            @unless($this->onesiBox->isOnline())
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                    Il box è spento o non connesso: per ora questi comandi non possono partire.
                </p>
            @endunless
        </x-box-section>
    @endif
</div>
