<div>
    @if($this->onesiBox->recipient?->congregation)
        <div class="space-y-4">
            {{-- Next meeting info --}}
            @if($this->nextMeeting)
                <div class="rounded-lg border border-zinc-700 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-zinc-400">Prossima adunanza</p>
                            <p class="text-lg font-semibold">
                                {{ $this->nextMeeting['type']->getLabel() }}
                                — {{ $this->nextMeeting['scheduled_at']->translatedFormat('l d M, H:i') }}
                            </p>
                            <p class="text-sm text-zinc-400">{{ $this->onesiBox->recipient->congregation->name }}</p>
                        </div>
                        <flux:button wire:click="joinNow" variant="primary" size="sm">
                            Collega ora
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- Join mode toggle --}}
            <div class="flex items-center justify-between rounded-lg border border-zinc-700 p-4">
                <div>
                    <p class="text-sm font-medium">Modalità collegamento</p>
                    <p class="text-sm text-zinc-400">{{ $this->onesiBox->meeting_join_mode->getLabel() }}</p>
                </div>
                <flux:button wire:click="toggleJoinMode" variant="subtle" size="sm">
                    Cambia
                </flux:button>
            </div>

            {{-- Pending attendance actions --}}
            @if($this->pendingAttendance)
                <div class="rounded-lg border border-yellow-600 bg-yellow-900/20 p-4">
                    <p class="font-medium text-yellow-400">
                        Adunanza {{ $this->pendingAttendance->meetingInstance->type->getLabel() }} in arrivo
                    </p>
                    <div class="mt-2 flex gap-2">
                        <flux:button wire:click="confirmJoin({{ $this->pendingAttendance->id }})" variant="primary" size="sm">
                            Conferma
                        </flux:button>
                        <flux:button wire:click="skipMeeting({{ $this->pendingAttendance->id }})" variant="danger" size="sm">
                            Salta
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- History --}}
            @if($this->recentAttendances->isNotEmpty())
                <div>
                    <h4 class="mb-2 text-sm font-medium text-zinc-400">Storico adunanze</h4>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-700 text-left text-zinc-400">
                                <th class="pb-2">Data/ora</th>
                                <th class="pb-2">Tipo</th>
                                <th class="pb-2">Durata</th>
                                <th class="pb-2">Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->recentAttendances as $attendance)
                                <tr class="border-b border-zinc-800">
                                    <td class="py-2">{{ $attendance->meetingInstance->scheduled_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $attendance->meetingInstance->type->getLabel() }}</td>
                                    <td>
                                        @if($attendance->joined_at && $attendance->left_at)
                                            {{ $attendance->joined_at->diffForHumans($attendance->left_at, true) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <span class="rounded-full px-2 py-0.5 text-xs {{ match($attendance->status->value) {
                                            'completed' => 'bg-green-900 text-green-300',
                                            'joined' => 'bg-blue-900 text-blue-300',
                                            'skipped' => 'bg-red-900 text-red-300',
                                            default => 'bg-zinc-700 text-zinc-300',
                                        } }}">
                                            {{ $attendance->status->getLabel() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        <p class="text-sm text-zinc-400">Nessuna congregazione assegnata.</p>
    @endif
</div>
