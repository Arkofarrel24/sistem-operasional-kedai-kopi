<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Kasir · {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    {{-- Full-screen, touch-first layout for tablets: no sidebar, large tap targets, no page scroll. --}}
    <body class="h-dvh overflow-hidden font-sans antialiased bg-stone-100 text-stone-900 select-none [-webkit-tap-highlight-color:transparent]">
        <div class="flex h-full flex-col">
            <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-stone-900 px-4 text-white print:hidden sm:px-6">
                <div class="flex items-center gap-3">
                    <x-application-logo class="h-8 w-8 shrink-0 text-amber-400" />
                    <div class="hidden leading-tight sm:block">
                        <p class="text-lg font-semibold">{{ config('app.name') }}</p>
                        <p class="text-xs text-stone-400">{{ now()->translatedFormat('j M Y') }}</p>
                    </div>
                </div>

                <nav class="flex items-center gap-1 rounded-xl bg-stone-800 p-1" aria-label="Kasir">
                    @foreach (['pos.index' => 'Kasir', 'pos.history' => 'Riwayat'] as $route => $label)
                        @php($active = request()->routeIs($route) || ($route === 'pos.history' && request()->routeIs('pos.sales.*')))
                        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                           @class([
                               'flex h-10 items-center rounded-lg px-4 text-base font-medium',
                               'bg-stone-100 text-stone-900' => $active,
                               'text-stone-300 active:bg-stone-700' => ! $active,
                           ])>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="hidden text-right leading-tight md:block">
                        <p class="max-w-[12rem] truncate text-base font-medium">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-stone-400">{{ auth()->user()->role->label() }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex h-12 items-center gap-2 rounded-xl bg-stone-800 px-4 text-base font-medium text-stone-100 hover:bg-stone-700 active:bg-stone-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                            Keluar
                        </button>
                    </form>
                </div>
            </header>

            <main class="min-h-0 flex-1">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
