<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-stone-950">
        <div class="flex min-h-svh">
            {{-- Left Panel --}}
            <div class="hidden lg:flex lg:w-1/2 lg:flex-col lg:justify-center lg:px-16 xl:px-24 bg-gradient-to-br from-stone-300 via-stone-200 to-stone-100 dark:from-stone-900 dark:via-stone-900 dark:to-stone-950">
                <div class="max-w-md">
                    <h1 class="text-6xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 xl:text-7xl">
                        Onesiforo
                    </h1>
                </div>
            </div>

            {{-- Right Panel --}}
            <div class="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-1/2 lg:px-12 bg-white dark:bg-stone-950">
                <div class="w-full max-w-sm">
                    {{-- Mobile Logo --}}
                    <div class="mb-10 lg:hidden">
                        <h1 class="text-4xl font-semibold tracking-tight text-stone-900 dark:text-stone-100">
                            Onesiforo
                        </h1>
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
