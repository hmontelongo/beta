<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans antialiased dark:bg-zinc-950">
    <div class="flex min-h-screen flex-col items-center justify-center px-4">
        <div class="text-center">
            <p class="text-7xl font-bold text-zinc-300 dark:text-zinc-700">
                @yield('code')
            </p>
            <h1 class="mt-4 text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                @yield('title')
            </h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-400">
                @yield('message')
            </p>
            <div class="mt-8">
                <a
                    href="{{ url('/') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Volver al inicio
                </a>
            </div>
        </div>
    </div>
</body>
</html>
