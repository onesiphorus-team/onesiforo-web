@php $screenshots = $this->screenshots; @endphp

<div>
    @if ($screenshots->isNotEmpty())
        @if ($variant === 'compact')
            <div class="mt-3 flex gap-1 overflow-x-auto pb-1" role="region" aria-label="Diagnostica">
                @foreach ($screenshots as $s)
                    <a href="{{ $s->signedUrl() }}" target="_blank" rel="noopener" class="shrink-0">
                        <img src="{{ $s->signedUrl() }}"
                             alt="{{ $s->captured_at->toDateTimeString() }}"
                             loading="lazy"
                             width="80" height="45"
                             class="rounded border border-gray-200 dark:border-gray-700 {{ $loop->first ? 'ring-2 ring-primary-500' : '' }}" />
                    </a>
                @endforeach
            </div>
        @else
            <section class="mt-4" aria-label="Diagnostica schermo">
                <h3 class="font-semibold mb-2">Diagnostica schermo</h3>
                <div class="flex gap-2 overflow-x-auto pb-2">
                    @foreach ($screenshots as $s)
                        <a href="{{ $s->signedUrl() }}" target="_blank" rel="noopener" class="shrink-0">
                            <img src="{{ $s->signedUrl() }}"
                                 alt="{{ $s->captured_at->toDateTimeString() }}"
                                 loading="lazy"
                                 width="160" height="90"
                                 class="rounded border border-gray-200 dark:border-gray-700 {{ $loop->first ? 'ring-2 ring-primary-500' : '' }}" />
                            <div class="text-xs text-gray-500 text-center mt-1">
                                {{ $s->captured_at->diffForHumans() }}
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @elseif ($variant === 'compact' && ! $box->screenshot_enabled)
        <div class="mt-3 text-xs text-gray-500 italic">Diagnostica non attiva</div>
    @else
        {{-- detail view empty OR compact view with feature enabled but no data: render nothing --}}
    @endif
</div>
