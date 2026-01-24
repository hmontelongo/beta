<!DOCTYPE html>
<html
    lang="es"
    class="scroll-smooth"
    x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches) }"
    x-init="$watch('darkMode', val => localStorage.setItem('theme', val ? 'dark' : 'light'))"
    :class="{ 'dark': darkMode }"
>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>PropertyManager - De WhatsApp a propuesta en minutos</title>
        <meta name="description" content="La herramienta que los agentes inmobiliarios estaban esperando. Busca propiedades, crea colecciones y comparte con tus clientes en minutos." />

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

        {{-- Prevent flash of wrong theme --}}
        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased overflow-x-hidden transition-colors duration-300 dark:bg-zinc-950 dark:text-white">
        {{ $slot }}

        <flux:toast position="top right" />

        @fluxScripts
    </body>
</html>
