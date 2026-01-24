<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        {{-- Minimal Public Header (hidden in print/PDF mode) --}}
        @unless(request()->has('pdf'))
            <header class="sticky top-0 z-50 border-b border-zinc-200/80 bg-white/95 backdrop-blur-sm print:hidden supports-[backdrop-filter]:bg-white/80 dark:border-zinc-800 dark:bg-zinc-900/95 dark:supports-[backdrop-filter]:bg-zinc-900/80">
                <div class="mx-auto flex h-14 max-w-screen-2xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    {{-- Logo --}}
                    <a href="{{ route('home') }}" class="flex items-center gap-2">
                        <x-app-logo class="h-7" />
                    </a>

                    {{-- CTA for non-authenticated users --}}
                    @guest
                        <div class="flex items-center gap-2">
                            <a href="{{ route('login') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                                Iniciar sesion
                            </a>
                        </div>
                    @endguest
                </div>
            </header>
        @endunless

        {{-- Main Content --}}
        <main>
            {{ $slot }}
        </main>

        <flux:toast />

        @fluxScripts
    </body>
</html>
