<div>
    {{-- Navigation --}}
    <nav class="fixed top-0 left-0 right-0 z-50 border-b border-white/[0.03] bg-[#08090a]/70 backdrop-blur-2xl backdrop-saturate-150">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-lg bg-white">
                    <x-app-logo-icon class="size-5 text-zinc-900" />
                </div>
                <span class="text-lg font-semibold tracking-tight">PropertyManager</span>
            </a>

            <div class="flex items-center gap-4">
                @guest
                    <a href="{{ route('login') }}" class="text-sm font-medium text-zinc-400 transition hover:text-white">
                        Iniciar sesion
                    </a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-900 transition hover:bg-zinc-100">
                        Registrarse
                    </a>
                @else
                    <a href="{{ auth()->user()->homeUrl() }}" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-900 transition hover:bg-zinc-100">
                        Ir al panel
                    </a>
                @endguest
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section class="relative min-h-screen overflow-hidden pt-16">
        {{-- Background gradient - Linear inspired --}}
        <div class="absolute inset-0 -z-10">
            <div class="absolute inset-0 bg-[#08090a]"></div>
            <div class="absolute left-1/2 top-0 -translate-x-1/2 h-[600px] w-[800px] rounded-full bg-gradient-to-r from-blue-500/20 via-violet-500/20 to-fuchsia-500/20 blur-3xl opacity-40"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(120,119,198,0.15),rgba(255,255,255,0))]"></div>
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-5xl flex-col items-center justify-center px-6 py-20 text-center">
            {{-- Badge --}}
            <div class="animate-fade-in mb-8 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-zinc-400 backdrop-blur-sm">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                Proximamente en Guadalajara
            </div>

            {{-- Main Headline - Linear style gradient text --}}
            <h1 class="animate-fade-in-up max-w-4xl text-balance text-4xl font-semibold tracking-tight text-white sm:text-5xl md:text-6xl lg:text-7xl" style="animation-delay: 100ms">
                De WhatsApp a propuesta
                <span class="bg-gradient-to-r from-white via-white/80 to-white/60 bg-clip-text text-transparent">
                    en minutos
                </span>
            </h1>

            {{-- Subheadline --}}
            <p class="animate-fade-in-up mt-6 max-w-xl text-balance text-lg text-zinc-400" style="animation-delay: 200ms">
                Busca propiedades, crea colecciones y comparte con tus clientes. Todo en un solo lugar.
            </p>

            {{-- CTA Form --}}
            <div class="animate-fade-in-up mt-12 w-full max-w-md" style="animation-delay: 300ms">
                @if($submitted)
                    <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-8">
                        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-emerald-500/10 ring-1 ring-emerald-500/20">
                            <flux:icon.check class="size-7 text-emerald-400" />
                        </div>
                        <p class="text-lg font-medium text-white">Te avisamos pronto</p>
                        <p class="mt-2 text-sm text-zinc-400">Te hemos agregado a la lista de espera.</p>
                    </div>
                @else
                    <form wire:submit="submit" class="flex flex-col gap-3 sm:flex-row">
                        <div class="flex-1">
                            <input
                                type="email"
                                wire:model="email"
                                placeholder="Tu correo profesional"
                                class="w-full rounded-xl border border-white/10 bg-white/5 px-5 py-4 text-white placeholder-zinc-500 outline-none transition focus:border-white/20 focus:bg-white/[0.07] focus:ring-0"
                                required
                            />
                            @error('email')
                                <p class="mt-2 text-left text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button
                            type="submit"
                            class="shrink-0 rounded-xl bg-white px-6 py-4 font-medium text-zinc-900 transition hover:bg-zinc-200 disabled:opacity-50"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>Solicitar acceso</span>
                            <span wire:loading class="flex items-center gap-2">
                                <flux:icon.arrow-path class="size-4 animate-spin" />
                                Enviando...
                            </span>
                        </button>
                    </form>
                    <p class="mt-4 text-sm text-zinc-600">
                        Acceso anticipado limitado. Sin spam.
                    </p>
                @endif
            </div>

            {{-- Hero Visual - App mockup --}}
            <div class="animate-fade-in-up mt-20 w-full max-w-4xl" style="animation-delay: 400ms">
                <div class="relative">
                    {{-- Glow effect --}}
                    <div class="absolute -inset-4 bg-gradient-to-r from-blue-500/20 via-violet-500/20 to-fuchsia-500/20 blur-2xl opacity-30"></div>

                    <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-zinc-900/80 p-1.5 shadow-2xl backdrop-blur-sm">
                        {{-- Browser chrome --}}
                        <div class="flex items-center gap-2 border-b border-white/5 px-4 py-3">
                            <div class="flex gap-1.5">
                                <div class="size-3 rounded-full bg-zinc-700"></div>
                                <div class="size-3 rounded-full bg-zinc-700"></div>
                                <div class="size-3 rounded-full bg-zinc-700"></div>
                            </div>
                            <div class="mx-auto h-7 w-64 rounded-md bg-zinc-800"></div>
                        </div>

                        {{-- App interface --}}
                        <div class="aspect-[16/9] bg-zinc-900">
                            <div class="flex h-full">
                                {{-- Sidebar --}}
                                <div class="hidden w-56 border-r border-white/5 p-4 lg:block">
                                    <div class="mb-6 h-8 w-32 rounded-lg bg-zinc-800"></div>
                                    <div class="space-y-2">
                                        <div class="h-9 w-full rounded-lg bg-white/10"></div>
                                        <div class="h-9 w-full rounded-lg bg-zinc-800/50"></div>
                                        <div class="h-9 w-full rounded-lg bg-zinc-800/50"></div>
                                    </div>
                                </div>

                                {{-- Main content --}}
                                <div class="flex-1 p-4 lg:p-6">
                                    {{-- Search bar --}}
                                    <div class="mb-6 flex gap-3">
                                        <div class="h-10 flex-1 rounded-lg bg-zinc-800"></div>
                                        <div class="h-10 w-24 rounded-lg bg-zinc-800"></div>
                                    </div>

                                    {{-- Property grid --}}
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        @for ($i = 0; $i < 6; $i++)
                                            <div class="overflow-hidden rounded-xl bg-zinc-800/50 ring-1 ring-white/5">
                                                <div class="aspect-[4/3] bg-gradient-to-br from-zinc-700/50 to-zinc-800/50"></div>
                                                <div class="p-3">
                                                    <div class="h-4 w-3/4 rounded bg-zinc-700"></div>
                                                    <div class="mt-2 h-3 w-1/2 rounded bg-zinc-700/50"></div>
                                                </div>
                                            </div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Problem Section --}}
    <section class="relative border-t border-white/5 bg-[#08090a] py-24 lg:py-32">
        <div class="mx-auto max-w-5xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    Conocemos tu dia a dia
                </h2>
                <p class="mt-4 text-lg text-zinc-400">
                    Esto le pasa a cada agente inmobiliario, todos los dias.
                </p>
            </div>

            <div class="mt-16 grid gap-8 lg:grid-cols-2">
                {{-- Before --}}
                <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/30 p-8">
                    <div class="absolute right-0 top-0 h-px w-1/2 bg-gradient-to-l from-red-500/50 to-transparent"></div>
                    <div class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-red-400">
                        <span class="flex size-5 items-center justify-center rounded-full bg-red-500/10 ring-1 ring-red-500/20">
                            <flux:icon.x-mark class="size-3" />
                        </span>
                        Asi trabajas hoy
                    </div>
                    <ul class="space-y-4 text-zinc-300">
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">01</span>
                            <span>Recibes un WhatsApp: "Busco casa en Zapopan"</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">02</span>
                            <span>Abres 3 portales diferentes a buscar</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">03</span>
                            <span>Tomas screenshots y copias datos a mano</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">04</span>
                            <span>Mandas fotos sueltas por WhatsApp</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">05</span>
                            <span>El cliente recibe un desorden de mensajes</span>
                        </li>
                    </ul>
                    <p class="mt-6 border-t border-white/5 pt-6 text-sm text-zinc-500">
                        Y mientras tanto, la competencia ya le mando opciones...
                    </p>
                </div>

                {{-- After --}}
                <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/30 p-8">
                    <div class="absolute right-0 top-0 h-px w-1/2 bg-gradient-to-l from-emerald-500/50 to-transparent"></div>
                    <div class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-emerald-400">
                        <span class="flex size-5 items-center justify-center rounded-full bg-emerald-500/10 ring-1 ring-emerald-500/20">
                            <flux:icon.check class="size-3" />
                        </span>
                        Con PropertyManager
                    </div>
                    <ul class="space-y-4 text-zinc-300">
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">01</span>
                            <span>Recibes el WhatsApp</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">02</span>
                            <span>Buscas una vez, encuentras todo</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">03</span>
                            <span>Creas una coleccion con un tap</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-600">04</span>
                            <span>Compartes un link profesional</span>
                        </li>
                    </ul>
                    <div class="mt-6 border-t border-white/5 pt-6">
                        <p class="text-3xl font-semibold text-white">3 minutos</p>
                        <p class="text-sm text-emerald-400">de WhatsApp a propuesta profesional</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section class="relative border-t border-white/5 bg-zinc-950 py-24 lg:py-32">
        <div class="mx-auto max-w-5xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    Todo lo que necesitas, nada que no
                </h2>
                <p class="mt-4 text-lg text-zinc-400">
                    Herramientas simples para responder mas rapido.
                </p>
            </div>

            <div class="mt-16 grid gap-4 sm:grid-cols-2">
                {{-- Feature 1 --}}
                <div class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/30 p-8 transition-colors hover:border-white/10 hover:bg-zinc-900/50">
                    <div class="mb-5">
                        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-blue-500/10 ring-1 ring-blue-500/20">
                            <flux:icon.magnifying-glass class="size-5 text-blue-400" />
                        </div>
                    </div>
                    <h3 class="text-lg font-medium text-white">Una sola busqueda</h3>
                    <p class="mt-2 text-zinc-400">
                        Filtra por zona, precio, recamaras. Resultados de multiples portales en segundos.
                    </p>
                </div>

                {{-- Feature 2 --}}
                <div class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/30 p-8 transition-colors hover:border-white/10 hover:bg-zinc-900/50">
                    <div class="mb-5">
                        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-violet-500/10 ring-1 ring-violet-500/20">
                            <flux:icon.folder-plus class="size-5 text-violet-400" />
                        </div>
                    </div>
                    <h3 class="text-lg font-medium text-white">Colecciones en un tap</h3>
                    <p class="mt-2 text-zinc-400">
                        Arma propuestas personalizadas para cada cliente en segundos.
                    </p>
                </div>

                {{-- Feature 3 --}}
                <div class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/30 p-8 transition-colors hover:border-white/10 hover:bg-zinc-900/50">
                    <div class="mb-5">
                        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-fuchsia-500/10 ring-1 ring-fuchsia-500/20">
                            <flux:icon.share class="size-5 text-fuchsia-400" />
                        </div>
                    </div>
                    <h3 class="text-lg font-medium text-white">Comparte como profesional</h3>
                    <p class="mt-2 text-zinc-400">
                        Links elegantes y PDFs que impresionan. Nada de screenshots.
                    </p>
                </div>

                {{-- Feature 4 --}}
                <div class="group relative overflow-hidden rounded-2xl border border-white/5 bg-zinc-900/30 p-8 transition-colors hover:border-white/10 hover:bg-zinc-900/50">
                    <div class="mb-5">
                        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 ring-1 ring-emerald-500/20">
                            <flux:icon.device-phone-mobile class="size-5 text-emerald-400" />
                        </div>
                    </div>
                    <h3 class="text-lg font-medium text-white">Hecho para movil</h3>
                    <p class="mt-2 text-zinc-400">
                        Tu oficina en tu bolsillo. Rapido incluso con datos limitados.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- How It Works --}}
    <section class="relative border-t border-white/5 bg-[#08090a] py-24 lg:py-32">
        <div class="mx-auto max-w-5xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    Asi de simple
                </h2>
                <p class="mt-4 text-lg text-zinc-400">
                    Tres pasos. Tres minutos. Listo.
                </p>
            </div>

            <div class="mt-16">
                <div class="grid gap-8 lg:grid-cols-3">
                    {{-- Step 1 --}}
                    <div class="text-center">
                        <div class="relative mx-auto mb-6 flex size-20 items-center justify-center">
                            <div class="absolute inset-0 rounded-2xl bg-gradient-to-b from-blue-500/20 to-transparent"></div>
                            <div class="relative flex size-16 items-center justify-center rounded-xl border border-white/10 bg-zinc-900">
                                <flux:icon.magnifying-glass class="size-7 text-blue-400" />
                            </div>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wider text-blue-400">Paso 1</p>
                        <h3 class="mt-2 text-lg font-medium text-white">Busca</h3>
                        <p class="mt-2 text-sm text-zinc-400">
                            Filtra por lo que busca tu cliente.
                        </p>
                    </div>

                    {{-- Step 2 --}}
                    <div class="text-center">
                        <div class="relative mx-auto mb-6 flex size-20 items-center justify-center">
                            <div class="absolute inset-0 rounded-2xl bg-gradient-to-b from-violet-500/20 to-transparent"></div>
                            <div class="relative flex size-16 items-center justify-center rounded-xl border border-white/10 bg-zinc-900">
                                <flux:icon.plus-circle class="size-7 text-violet-400" />
                            </div>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wider text-violet-400">Paso 2</p>
                        <h3 class="mt-2 text-lg font-medium text-white">Selecciona</h3>
                        <p class="mt-2 text-sm text-zinc-400">
                            Agrega a coleccion con un tap.
                        </p>
                    </div>

                    {{-- Step 3 --}}
                    <div class="text-center">
                        <div class="relative mx-auto mb-6 flex size-20 items-center justify-center">
                            <div class="absolute inset-0 rounded-2xl bg-gradient-to-b from-fuchsia-500/20 to-transparent"></div>
                            <div class="relative flex size-16 items-center justify-center rounded-xl border border-white/10 bg-zinc-900">
                                <flux:icon.paper-airplane class="size-7 text-fuchsia-400" />
                            </div>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wider text-fuchsia-400">Paso 3</p>
                        <h3 class="mt-2 text-lg font-medium text-white">Comparte</h3>
                        <p class="mt-2 text-sm text-zinc-400">
                            Manda link o descarga PDF.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Social Proof / Value Props --}}
    <section class="relative border-t border-white/5 bg-zinc-950 py-24 lg:py-32">
        <div class="mx-auto max-w-5xl px-6">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">Hecho para agentes</p>
                    <h2 class="mt-4 text-balance text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        Tu ventaja competitiva empieza aqui
                    </h2>
                    <p class="mt-4 text-lg text-zinc-400">
                        Mientras otros siguen tomando screenshots, tu ya cerraste la venta.
                    </p>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-center gap-3 text-zinc-300">
                            <flux:icon.check class="size-5 text-emerald-400" />
                            Busqueda unificada de multiples portales
                        </li>
                        <li class="flex items-center gap-3 text-zinc-300">
                            <flux:icon.check class="size-5 text-emerald-400" />
                            Colecciones compartibles al instante
                        </li>
                        <li class="flex items-center gap-3 text-zinc-300">
                            <flux:icon.check class="size-5 text-emerald-400" />
                            PDFs profesionales con tu marca
                        </li>
                        <li class="flex items-center gap-3 text-zinc-300">
                            <flux:icon.check class="size-5 text-emerald-400" />
                            Optimizado para movil
                        </li>
                    </ul>
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-white/5 bg-zinc-900/30 p-6">
                        <p class="text-4xl font-semibold text-white">3</p>
                        <p class="text-sm text-zinc-500">minutos promedio</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-zinc-900/30 p-6">
                        <p class="text-4xl font-semibold text-white">1</p>
                        <p class="text-sm text-zinc-500">busqueda en vez de 5</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-zinc-900/30 p-6">
                        <p class="text-4xl font-semibold text-white">0</p>
                        <p class="text-sm text-zinc-500">screenshots</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-zinc-900/30 p-6">
                        <p class="text-4xl font-semibold text-white">100%</p>
                        <p class="text-sm text-zinc-500">profesional</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative border-t border-white/5 bg-[#08090a] py-24 lg:py-32">
        {{-- Background --}}
        <div class="absolute inset-0 -z-10">
            <div class="absolute bottom-0 left-1/2 h-[300px] w-[500px] -translate-x-1/2 rounded-full bg-gradient-to-r from-blue-500/10 via-violet-500/10 to-fuchsia-500/10 blur-3xl"></div>
        </div>

        <div class="mx-auto max-w-2xl px-6 text-center">
            <h2 class="text-balance text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                Listo para responder mas rapido?
            </h2>
            <p class="mt-4 text-lg text-zinc-400">
                Unete a la lista y se de los primeros en probarlo.
            </p>

            <div class="mx-auto mt-10 max-w-md">
                @if($submitted)
                    <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-8">
                        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-emerald-500/10 ring-1 ring-emerald-500/20">
                            <flux:icon.check class="size-7 text-emerald-400" />
                        </div>
                        <p class="text-lg font-medium text-white">Ya estas en la lista</p>
                        <p class="mt-2 text-sm text-zinc-400">Te contactamos cuando abramos acceso.</p>
                    </div>
                @else
                    <form wire:submit="submit" class="flex flex-col gap-3 sm:flex-row">
                        <input
                            type="email"
                            wire:model="email"
                            placeholder="Tu correo profesional"
                            class="flex-1 rounded-xl border border-white/10 bg-white/5 px-5 py-4 text-white placeholder-zinc-500 outline-none transition focus:border-white/20 focus:bg-white/[0.07]"
                            required
                        />
                        <button
                            type="submit"
                            class="shrink-0 rounded-xl bg-white px-6 py-4 font-medium text-zinc-900 transition hover:bg-zinc-200 disabled:opacity-50"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>Solicitar acceso</span>
                            <span wire:loading class="flex items-center gap-2">
                                <flux:icon.arrow-path class="size-4 animate-spin" />
                            </span>
                        </button>
                    </form>
                    <p class="mt-4 text-sm text-zinc-600">
                        Guadalajara primero. Sin spam.
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-white/5 bg-[#08090a] py-12">
        <div class="mx-auto max-w-5xl px-6">
            <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                <div class="flex items-center gap-3">
                    <div class="flex size-8 items-center justify-center rounded-lg bg-white">
                        <x-app-logo-icon class="size-4 text-zinc-900" />
                    </div>
                    <span class="font-medium text-white">PropertyManager</span>
                </div>

                <div class="flex items-center gap-6 text-sm text-zinc-600">
                    <a href="#" class="transition hover:text-zinc-400">Terminos</a>
                    <a href="#" class="transition hover:text-zinc-400">Privacidad</a>
                    <a href="mailto:hola@propertymanager.mx" class="transition hover:text-zinc-400">Contacto</a>
                </div>

                <p class="text-sm text-zinc-700">
                    Hecho con <span class="text-zinc-500">&hearts;</span> en Guadalajara
                </p>
            </div>
        </div>
    </footer>
</div>
