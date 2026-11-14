{{--
    A group of the OnesiBox page: a white card on the grey page, with a header made of an
    icon, a title and a one-line explanation. Structure is neutral on purpose: hierarchy
    comes from cards, type size and weight, not from colour.

    Palette of the box page (each colour has one role only):
    - zinc greys: structure and text
    - indigo (project colour): main actions
    - green: active / all good      - amber: needs attention      - red: stop / delete
--}}
@props([
    'title',
    'subtitle' => null,
    'icon',
])

@php
    $headingId = 'section-'.\Illuminate\Support\Str::slug($title).'-heading';
@endphp

<section aria-labelledby="{{ $headingId }}"
         {{ $attributes->class('min-w-0 scroll-mt-24 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800') }}>
    <header class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3 sm:px-5 dark:border-zinc-700/70">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300" aria-hidden="true">
            <flux:icon :name="$icon" class="size-5" />
        </span>
        <div class="min-w-0 flex-1">
            <h2 id="{{ $headingId }}" class="text-base font-semibold leading-tight text-zinc-900 sm:text-lg dark:text-white">{{ $title }}</h2>
            @if($subtitle)
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="shrink-0">{{ $actions }}</div>
        @endisset
    </header>

    @if($slot->isNotEmpty())
        <div class="p-4 sm:p-5">
            {{ $slot }}
        </div>
    @endif
</section>
