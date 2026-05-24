<div>
    @php($commands = $this->commands)

    @if($this->canControl && $commands->isNotEmpty())
        <div class="p-4 sm:p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <flux:heading size="sm" class="mb-4">
                <flux:icon name="command-line" class="w-5 h-5 inline-block mr-2" />
                {{ __('Comandi personalizzati') }}
            </flux:heading>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($commands as $command)
                    @php($iconName = $command->icon ? str_replace(['heroicon-o-', 'heroicon-s-'], '', $command->icon) : 'bolt')

                    <flux:button
                        wire:click="run({{ $command->id }})"
                        wire:loading.attr="disabled"
                        wire:target="run({{ $command->id }})"
                        :disabled="! $this->onesiBox->isOnline()"
                        variant="primary"
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
                    {{ __('La OnesiBox è offline. I comandi non possono essere inviati.') }}
                </p>
            @endunless
        </div>
    @endif
</div>
