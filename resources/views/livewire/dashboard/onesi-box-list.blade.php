<div class="-m-6 min-h-dvh bg-zinc-100 px-4 py-6 sm:px-6 lg:-m-8 lg:px-8 lg:py-8 dark:bg-zinc-900" wire:poll.10s.visible>
    <div class="mx-auto max-w-3xl space-y-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Dashboard') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Tocca un box per vedere cosa sta facendo e mandargli video, audio o una riunione.</p>
        </div>

        <x-install-app-hint />

        @if(auth()->user()->hasNoRoles())
            <flux:callout icon="clock" variant="warning">
                <flux:callout.heading>{{ __('Account in attesa di attivazione') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Il tuo account è stato creato ma non è ancora stato attivato. Un amministratore dovrà assegnarti un ruolo prima che tu possa accedere alle funzionalità della piattaforma.') }}
                </flux:callout.text>
            </flux:callout>
        @else
            @if(auth()->user()->hasAnyRoles(\App\Enums\Roles::SuperAdmin, \App\Enums\Roles::Admin))
                <flux:button variant="primary" icon="cog-6-tooth" :href="url('/admin')">
                    {{ __('Accedi all\'area amministrazione') }}
                </flux:button>
            @endif

            @forelse($this->onesiBoxes as $box)
                @php
                    $isOnline = $box->isOnline();
                    [$statusText, $dotClass] = match (true) {
                        ! $isOnline => ['spento o non connesso (ultimo contatto '.($box->last_seen_at?->diffForHumans() ?? 'mai').')', 'bg-amber-500'],
                        $box->status === \App\Enums\OnesiBoxStatus::Calling => ['in riunione Zoom', 'bg-green-500'],
                        $box->current_media_url !== null => [$box->current_media_type === 'audio' ? 'sta facendo ascoltare un audio' : 'sta mostrando un video', 'bg-green-500'],
                        default => ['acceso, in attesa', 'bg-zinc-400'],
                    };
                @endphp
                <a href="{{ route('dashboard.show', $box) }}"
                   wire:navigate
                   wire:key="box-{{ $box->id }}"
                   class="block rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-zinc-300 hover:shadow-md sm:p-5 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                    <div class="flex items-center gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300" aria-hidden="true">
                            <flux:icon name="tv" class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-semibold text-zinc-900 dark:text-white">{{ $box->recipient?->full_name ?? $box->name }}</p>
                            @if($box->recipient)
                                <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $box->name }}</p>
                            @endif
                            <p class="mt-1.5 flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300" data-slot="box-status">
                                <span class="size-2.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                                <span>{{ $isOnline ? 'Online' : 'Offline' }} · {{ $statusText }}</span>
                            </p>
                        </div>
                        <flux:icon name="chevron-right" class="size-6 shrink-0 text-zinc-400" />
                    </div>

                    <livewire:onesi-box.screenshot-carousel
                        :box="$box"
                        variant="compact"
                        :key="'carousel-compact-'.$box->id" />
                </a>
            @empty
                <flux:callout icon="information-circle">
                    <flux:callout.heading>Nessuna OnesiBox assegnata</flux:callout.heading>
                    <flux:callout.text>
                        Non hai ancora box da seguire. Chiedi all'amministratore di associarti al box della persona che assisti.
                    </flux:callout.text>
                </flux:callout>
            @endforelse
        @endif
    </div>
</div>
