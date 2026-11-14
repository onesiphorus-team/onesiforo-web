<div>
    @if($this->onesiBox->recipient?->congregation)
        <div class="space-y-4">
            {{-- Pending attendance: needs a decision --}}
            @if($this->pendingAttendance)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="font-semibold text-amber-900 dark:text-amber-200">
                        Adunanza {{ $this->pendingAttendance->meetingInstance->type->getLabel() }} in arrivo
                    </p>
                    <p class="mt-0.5 text-sm text-amber-800/80 dark:text-amber-200/80">Vuoi collegare il box all'adunanza?</p>
                    @if($this->canControl)
                        <div class="mt-3 flex gap-2">
                            <flux:button wire:click="confirmJoin({{ $this->pendingAttendance->id }})" variant="primary" size="sm" icon="check">
                                Conferma
                            </flux:button>
                            <flux:button wire:click="skipMeeting({{ $this->pendingAttendance->id }})" variant="danger" size="sm">
                                Salta
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Next meeting --}}
            @if($this->nextMeeting)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Prossima adunanza</p>
                        <p class="text-lg font-semibold text-zinc-900 dark:text-white">
                            {{ ucfirst($this->nextMeeting['scheduled_at']->translatedFormat('l j F')) }}, {{ $this->nextMeeting['scheduled_at']->format('H:i') }}
                        </p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $this->nextMeeting['type']->getLabel() }} · {{ $this->onesiBox->recipient->congregation->name }}
                        </p>
                    </div>
                    @if($this->canControl)
                        <flux:button wire:click="joinNow" variant="primary" size="sm" icon="video-camera">
                            Collega ora
                        </flux:button>
                    @endif
                </div>
            @endif

            {{-- Join mode --}}
            <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-700/40">
                <div>
                    <p class="text-sm font-medium text-zinc-900 dark:text-white">Collegamento alle adunanze</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->onesiBox->meeting_join_mode->getLabel() }}</p>
                </div>
                @if($this->canControl)
                    <flux:button wire:click="toggleJoinMode" variant="subtle" size="sm">
                        Cambia
                    </flux:button>
                @endif
            </div>

            {{-- History --}}
            @if($this->recentAttendances->isNotEmpty())
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-zinc-600 dark:text-zinc-300">
                        Storico adunanze
                        <flux:icon name="chevron-down" variant="mini" class="size-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <ul class="mt-3 divide-y divide-zinc-100 text-sm dark:divide-zinc-700/60">
                        @foreach($this->recentAttendances as $attendance)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span class="min-w-0">
                                    <span class="block text-zinc-800 dark:text-zinc-100">{{ $attendance->meetingInstance->scheduled_at->format('d/m/Y H:i') }} · {{ $attendance->meetingInstance->type->getLabel() }}</span>
                                    @if($attendance->joined_at && $attendance->left_at)
                                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">Durata {{ $attendance->joined_at->diffForHumans($attendance->left_at, true) }}</span>
                                    @endif
                                </span>
                                <flux:badge size="sm" :color="$attendance->status->value === 'joined' ? 'green' : 'zinc'">
                                    {{ $attendance->status->getLabel() }}
                                </flux:badge>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @else
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Nessuna congregazione assegnata: le adunanze non sono configurate per questo box.</p>
    @endif
</div>
