<div class="overflow-x-hidden">
    {{-- Navigation --}}
    <nav
        x-data="{ mobileMenuOpen: false }"
        class="fixed top-0 left-0 right-0 z-50 border-b border-zinc-200/50 bg-white/80 backdrop-blur-xl backdrop-saturate-150 dark:border-white/[0.05] dark:bg-zinc-950/80"
    >
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-900 dark:bg-white">
                    <x-app-logo-icon class="size-4 text-white dark:text-zinc-900" />
                </div>
                <span class="text-base font-semibold tracking-tight text-zinc-900 dark:text-white">PropertyManager</span>
            </a>

            {{-- Desktop Navigation Links --}}
            <div class="hidden items-center gap-1 md:flex">
                <a href="#caracteristicas" class="rounded-lg px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
                    Caracteristicas
                </a>
                <a href="#como-funciona" class="rounded-lg px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
                    Como funciona
                </a>
            </div>

            {{-- Right side actions --}}
            <div class="flex items-center gap-2" x-data>
                {{-- Theme Toggle --}}
                <button
                    @click="$flux.dark = !$flux.dark"
                    class="flex size-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white"
                    aria-label="Toggle theme"
                >
                    <flux:icon.sun x-show="$flux.dark" x-cloak class="size-5" />
                    <flux:icon.moon x-show="!$flux.dark" x-cloak class="size-5" />
                </button>

                {{-- Desktop auth links --}}
                <div class="hidden items-center gap-2 md:flex">
                    @guest
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
                            Iniciar sesion
                        </a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                            Registrarse
                        </a>
                    @else
                        <a href="{{ auth()->user()->homeUrl() }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                            Ir al panel
                        </a>
                    @endguest
                </div>

                {{-- Mobile menu button --}}
                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="flex size-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 md:hidden dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white"
                    aria-label="Toggle menu"
                >
                    <flux:icon.bars-3 x-show="!mobileMenuOpen" class="size-5" />
                    <flux:icon.x-mark x-show="mobileMenuOpen" x-cloak class="size-5" />
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div
            x-show="mobileMenuOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="border-t border-zinc-200/50 bg-white/95 backdrop-blur-xl md:hidden dark:border-white/5 dark:bg-zinc-950/95"
        >
            <div class="mx-auto max-w-6xl space-y-1 px-6 py-4">
                <a href="#caracteristicas" @click="mobileMenuOpen = false" class="block rounded-lg px-3 py-2.5 text-sm text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
                    Caracteristicas
                </a>
                <a href="#como-funciona" @click="mobileMenuOpen = false" class="block rounded-lg px-3 py-2.5 text-sm text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
                    Como funciona
                </a>
                <div class="my-2 border-t border-zinc-200/50 dark:border-white/5"></div>
                @guest
                    <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
                        Iniciar sesion
                    </a>
                    <a href="{{ route('register') }}" class="mt-2 block rounded-lg bg-zinc-900 px-3 py-2.5 text-center text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                        Registrarse
                    </a>
                @else
                    <a href="{{ auth()->user()->homeUrl() }}" class="block rounded-lg bg-zinc-900 px-3 py-2.5 text-center text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                        Ir al panel
                    </a>
                @endguest
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section class="relative min-h-screen overflow-hidden pt-16">
        {{-- Background - Light mode --}}
        <div class="absolute inset-0 -z-10 dark:hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-zinc-50 to-white"></div>
            <div class="absolute left-1/2 top-0 -translate-x-1/2 h-[600px] w-[800px] rounded-full bg-gradient-to-r from-blue-400/20 via-violet-400/20 to-fuchsia-400/20 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(120,119,198,0.1),rgba(255,255,255,0))]"></div>
            {{-- Animated orbs --}}
            <div class="animate-float-slow absolute left-[10%] top-[20%] h-64 w-64 rounded-full bg-gradient-to-br from-blue-300/30 to-transparent blur-2xl"></div>
            <div class="animate-float-medium absolute right-[15%] top-[30%] h-48 w-48 rounded-full bg-gradient-to-br from-violet-300/30 to-transparent blur-2xl"></div>
            <div class="animate-float-fast absolute left-[20%] bottom-[20%] h-32 w-32 rounded-full bg-gradient-to-br from-fuchsia-300/30 to-transparent blur-2xl"></div>
        </div>

        {{-- Background - Dark mode --}}
        <div class="absolute inset-0 -z-10 hidden dark:block">
            <div class="absolute inset-0 bg-[#08090a]"></div>
            <div class="absolute left-1/2 top-0 -translate-x-1/2 h-[600px] w-[800px] rounded-full bg-gradient-to-r from-blue-500/20 via-violet-500/20 to-fuchsia-500/20 blur-3xl opacity-40"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(120,119,198,0.15),rgba(255,255,255,0))]"></div>
            {{-- Animated orbs --}}
            <div class="animate-float-slow absolute left-[10%] top-[20%] h-64 w-64 rounded-full bg-gradient-to-br from-blue-500/20 to-transparent blur-2xl"></div>
            <div class="animate-float-medium absolute right-[15%] top-[30%] h-48 w-48 rounded-full bg-gradient-to-br from-violet-500/20 to-transparent blur-2xl"></div>
            <div class="animate-float-fast absolute left-[20%] bottom-[20%] h-32 w-32 rounded-full bg-gradient-to-br from-fuchsia-500/20 to-transparent blur-2xl"></div>
        </div>

        <div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-5xl flex-col items-center justify-center px-6 py-20 text-center">
            {{-- Badge --}}
            <div class="animate-fade-in mb-8 inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white/80 px-4 py-2 text-sm text-zinc-600 backdrop-blur-sm dark:border-white/10 dark:bg-white/5 dark:text-zinc-400">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                Proximamente en Guadalajara
            </div>

            {{-- Audience Tag --}}
            <p class="animate-fade-in mb-4 text-sm font-medium uppercase tracking-wider text-zinc-500" style="animation-delay: 50ms">
                Para agentes inmobiliarios
            </p>

            {{-- Main Headline --}}
            <h1 class="animate-fade-in-up max-w-4xl text-balance text-4xl font-semibold tracking-tight text-zinc-900 sm:text-5xl md:text-6xl lg:text-7xl dark:text-white" style="animation-delay: 100ms">
                De WhatsApp a propuesta
                <span class="bg-gradient-to-r from-zinc-900 via-zinc-700 to-zinc-500 bg-clip-text text-transparent dark:from-white dark:via-white/80 dark:to-white/60">
                    en minutos
                </span>
            </h1>

            {{-- Subheadline --}}
            <p class="animate-fade-in-up mt-6 max-w-xl text-balance text-lg text-zinc-600 dark:text-zinc-400" style="animation-delay: 200ms">
                La herramienta para agentes inmobiliarios que quieren responder mas rapido y cerrar mas ventas.
            </p>

            {{-- CTA Form --}}
            <div class="animate-fade-in-up mt-12 w-full max-w-md" style="animation-delay: 300ms">
                @if($submitted)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 dark:border-emerald-500/20 dark:bg-emerald-500/5">
                        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-emerald-100 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:ring-emerald-500/20">
                            <flux:icon.check class="size-7 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <p class="text-lg font-medium text-zinc-900 dark:text-white">Te avisamos pronto</p>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Te hemos agregado a la lista de espera.</p>
                    </div>
                @else
                    <form wire:submit="submit" class="flex flex-col gap-3 sm:flex-row">
                        <div class="flex-1">
                            <input
                                type="email"
                                wire:model="email"
                                placeholder="Tu correo profesional"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-5 py-4 text-zinc-900 placeholder-zinc-400 outline-none transition focus:border-zinc-400 focus:ring-0 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-zinc-500 dark:focus:border-white/20 dark:focus:bg-white/[0.07]"
                                required
                            />
                            @error('email')
                                <p class="mt-2 text-left text-sm text-red-500 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button
                            type="submit"
                            class="shrink-0 rounded-xl bg-zinc-900 px-6 py-4 font-medium text-white transition hover:bg-zinc-800 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>Solicitar acceso</span>
                            <span wire:loading class="flex items-center gap-2">
                                <flux:icon.arrow-path class="size-4 animate-spin" />
                                Enviando...
                            </span>
                        </button>
                    </form>
                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-600">
                        Acceso anticipado limitado. Sin spam.
                    </p>
                @endif
            </div>

            {{-- Hero Visual - App mockup --}}
            <div class="animate-fade-in-up mt-20 w-full max-w-4xl" style="animation-delay: 400ms">
                <div class="relative">
                    {{-- Glow effect --}}
                    <div class="absolute -inset-4 bg-gradient-to-r from-blue-400/20 via-violet-400/20 to-fuchsia-400/20 blur-2xl opacity-50 dark:from-blue-500/20 dark:via-violet-500/20 dark:to-fuchsia-500/20 dark:opacity-30"></div>

                    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white/80 p-1.5 shadow-2xl backdrop-blur-sm dark:border-white/10 dark:bg-zinc-900/80">
                        {{-- Browser chrome --}}
                        <div class="flex items-center gap-2 border-b border-zinc-100 px-4 py-3 dark:border-white/5">
                            <div class="flex gap-1.5">
                                <div class="size-3 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="size-3 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="size-3 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                            </div>
                            <div class="mx-auto h-7 w-64 rounded-md bg-zinc-100 dark:bg-zinc-800"></div>
                        </div>

                        {{-- App interface --}}
                        <div class="aspect-[16/9] bg-zinc-50 dark:bg-zinc-900">
                            <div class="flex h-full">
                                {{-- Sidebar --}}
                                <div class="hidden w-56 border-r border-zinc-100 p-4 lg:block dark:border-white/5">
                                    <div class="mb-6 h-8 w-32 rounded-lg bg-zinc-200 dark:bg-zinc-800"></div>
                                    <div class="space-y-2">
                                        <div class="h-9 w-full rounded-lg bg-zinc-300/50 dark:bg-white/10"></div>
                                        <div class="h-9 w-full rounded-lg bg-zinc-100 dark:bg-zinc-800/50"></div>
                                        <div class="h-9 w-full rounded-lg bg-zinc-100 dark:bg-zinc-800/50"></div>
                                    </div>
                                </div>

                                {{-- Main content --}}
                                <div class="flex-1 p-4 lg:p-6">
                                    {{-- Search bar --}}
                                    <div class="mb-6 flex gap-3">
                                        <div class="h-10 flex-1 rounded-lg bg-zinc-200 dark:bg-zinc-800"></div>
                                        <div class="h-10 w-24 rounded-lg bg-zinc-200 dark:bg-zinc-800"></div>
                                    </div>

                                    {{-- Property grid --}}
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        @for ($i = 0; $i < 6; $i++)
                                            <div class="overflow-hidden rounded-xl bg-white ring-1 ring-zinc-100 dark:bg-zinc-800/50 dark:ring-white/5">
                                                <div class="aspect-[4/3] bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-700/50 dark:to-zinc-800/50"></div>
                                                <div class="p-3">
                                                    <div class="h-4 w-3/4 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                                    <div class="mt-2 h-3 w-1/2 rounded bg-zinc-100 dark:bg-zinc-700/50"></div>
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
    <section class="relative border-t border-zinc-200 bg-white py-24 lg:py-32 dark:border-white/5 dark:bg-[#08090a]">
        {{-- Background grid pattern --}}
        <div class="absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#e5e5e5_1px,transparent_1px),linear-gradient(to_bottom,#e5e5e5_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,black_40%,transparent_100%)] dark:bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)]"></div>
        </div>

        <div class="mx-auto max-w-5xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    Conocemos tu dia a dia
                </h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                    Esto le pasa a cada agente inmobiliario, todos los dias.
                </p>
            </div>

            <div class="relative mt-16 grid gap-8 lg:grid-cols-2">
                {{-- Connecting arrow between cards (desktop) --}}
                <div class="absolute left-1/2 top-1/2 z-10 hidden -translate-x-1/2 -translate-y-1/2 lg:block">
                    <div class="flex size-14 items-center justify-center rounded-full border border-zinc-200 bg-white shadow-lg dark:border-white/10 dark:bg-zinc-900">
                        <flux:icon.arrow-right class="size-6 text-zinc-400 dark:text-zinc-500" />
                    </div>
                </div>

                {{-- Before --}}
                <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-8 backdrop-blur-sm dark:border-white/5 dark:bg-zinc-900/30">
                    <div class="absolute right-0 top-0 h-px w-1/2 bg-gradient-to-l from-red-400 to-transparent dark:from-red-500/50"></div>
                    <div class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-red-600 dark:text-red-400">
                        <span class="flex size-5 items-center justify-center rounded-full bg-red-100 ring-1 ring-red-200 dark:bg-red-500/10 dark:ring-red-500/20">
                            <flux:icon.x-mark class="size-3" />
                        </span>
                        Asi trabajas hoy
                    </div>
                    <ul class="space-y-4 text-zinc-700 dark:text-zinc-300">
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">01</span>
                            <span>Recibes un WhatsApp: "Busco casa en Zapopan"</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">02</span>
                            <span>Abres Inmuebles24, Vivanuncios, WhatsApp...</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">03</span>
                            <span>Tomas screenshots y copias datos a mano</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">04</span>
                            <span>Mandas fotos sueltas por WhatsApp</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">05</span>
                            <span>El cliente recibe un desorden de mensajes</span>
                        </li>
                    </ul>
                    <p class="mt-6 border-t border-zinc-200 pt-6 text-sm text-zinc-500 dark:border-white/5">
                        Y mientras tanto, otro agente ya le mando una propuesta profesional.
                    </p>
                </div>

                {{-- After --}}
                <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-8 backdrop-blur-sm dark:border-white/5 dark:bg-zinc-900/30">
                    <div class="absolute right-0 top-0 h-px w-1/2 bg-gradient-to-l from-emerald-400 to-transparent dark:from-emerald-500/50"></div>
                    <div class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                        <span class="flex size-5 items-center justify-center rounded-full bg-emerald-100 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:ring-emerald-500/20">
                            <flux:icon.check class="size-3" />
                        </span>
                        Con PropertyManager
                    </div>
                    <ul class="space-y-4 text-zinc-700 dark:text-zinc-300">
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">01</span>
                            <span>Recibes el WhatsApp</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">02</span>
                            <span>Buscas una vez, encuentras todo</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">03</span>
                            <span>Creas una coleccion con un tap</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 text-zinc-400 dark:text-zinc-600">04</span>
                            <span>Compartes un link profesional</span>
                        </li>
                    </ul>
                    <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-white/5">
                        <p class="text-3xl font-semibold text-zinc-900 dark:text-white">3 minutos</p>
                        <p class="text-sm text-emerald-600 dark:text-emerald-400">de WhatsApp a propuesta profesional</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Market Context Section --}}
    <section class="relative border-t border-zinc-200 bg-zinc-100 py-24 lg:py-32 dark:border-white/5 dark:bg-zinc-950">
        {{-- Background elements --}}
        <div class="absolute inset-0 -z-10 overflow-hidden">
            {{-- Radial gradient --}}
            <div class="absolute left-1/2 top-1/2 h-[500px] w-[800px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gradient-to-r from-violet-200/40 via-transparent to-blue-200/40 blur-3xl dark:from-violet-500/10 dark:to-blue-500/10"></div>
        </div>

        {{-- Floating UI mockups - Left side (chaos/many portals) --}}
        <div class="pointer-events-none absolute inset-0 hidden overflow-hidden lg:block">
            {{-- Scattered portal mockups (left side) --}}
            <div class="animate-float-slow absolute left-[5%] top-[15%] w-32 -rotate-12 opacity-60">
                <div class="rounded-lg border border-zinc-200/50 bg-white/80 p-2 shadow-lg backdrop-blur-sm dark:border-white/10 dark:bg-zinc-800/80">
                    <div class="mb-2 flex items-center gap-1">
                        <div class="size-1.5 rounded-full bg-red-400"></div>
                        <div class="size-1.5 rounded-full bg-yellow-400"></div>
                        <div class="size-1.5 rounded-full bg-green-400"></div>
                    </div>
                    <div class="h-2 w-12 rounded bg-orange-200 dark:bg-orange-500/30"></div>
                    <div class="mt-2 grid grid-cols-2 gap-1">
                        <div class="h-6 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                        <div class="h-6 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                    </div>
                </div>
            </div>

            <div class="animate-float-medium absolute left-[8%] top-[45%] w-28 rotate-6 opacity-50">
                <div class="rounded-lg border border-zinc-200/50 bg-white/80 p-2 shadow-lg backdrop-blur-sm dark:border-white/10 dark:bg-zinc-800/80">
                    <div class="mb-2 flex items-center gap-1">
                        <div class="size-1.5 rounded-full bg-red-400"></div>
                        <div class="size-1.5 rounded-full bg-yellow-400"></div>
                        <div class="size-1.5 rounded-full bg-green-400"></div>
                    </div>
                    <div class="h-2 w-10 rounded bg-blue-200 dark:bg-blue-500/30"></div>
                    <div class="mt-2 space-y-1">
                        <div class="h-4 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                        <div class="h-4 w-2/3 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                    </div>
                </div>
            </div>

            <div class="animate-float-fast absolute left-[12%] bottom-[20%] w-24 -rotate-6 opacity-40">
                <div class="rounded-lg border border-zinc-200/50 bg-white/80 p-2 shadow-lg backdrop-blur-sm dark:border-white/10 dark:bg-zinc-800/80">
                    <div class="mb-2 flex items-center gap-1">
                        <div class="size-1.5 rounded-full bg-red-400"></div>
                        <div class="size-1.5 rounded-full bg-yellow-400"></div>
                        <div class="size-1.5 rounded-full bg-green-400"></div>
                    </div>
                    <div class="h-2 w-8 rounded bg-purple-200 dark:bg-purple-500/30"></div>
                    <div class="mt-2 h-8 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                </div>
            </div>

            {{-- Scattered portal mockups (right side - also chaos) --}}
            <div class="animate-float-medium absolute right-[6%] top-[20%] w-28 rotate-12 opacity-50">
                <div class="rounded-lg border border-zinc-200/50 bg-white/80 p-2 shadow-lg backdrop-blur-sm dark:border-white/10 dark:bg-zinc-800/80">
                    <div class="mb-2 flex items-center gap-1">
                        <div class="size-1.5 rounded-full bg-red-400"></div>
                        <div class="size-1.5 rounded-full bg-yellow-400"></div>
                        <div class="size-1.5 rounded-full bg-green-400"></div>
                    </div>
                    <div class="h-2 w-10 rounded bg-green-200 dark:bg-green-500/30"></div>
                    <div class="mt-2 h-10 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                </div>
            </div>

            <div class="animate-float-slow absolute right-[10%] top-[50%] w-32 -rotate-3 opacity-60">
                <div class="rounded-lg border border-zinc-200/50 bg-white/80 p-2 shadow-lg backdrop-blur-sm dark:border-white/10 dark:bg-zinc-800/80">
                    <div class="mb-2 flex items-center gap-1">
                        <div class="size-1.5 rounded-full bg-red-400"></div>
                        <div class="size-1.5 rounded-full bg-yellow-400"></div>
                        <div class="size-1.5 rounded-full bg-green-400"></div>
                    </div>
                    <div class="h-2 w-14 rounded bg-red-200 dark:bg-red-500/30"></div>
                    <div class="mt-2 grid grid-cols-3 gap-1">
                        <div class="h-5 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                        <div class="h-5 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                        <div class="h-5 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                    </div>
                </div>
            </div>

            <div class="animate-float-fast absolute right-[4%] bottom-[25%] w-24 rotate-6 opacity-40">
                <div class="rounded-lg border border-zinc-200/50 bg-white/80 p-2 shadow-lg backdrop-blur-sm dark:border-white/10 dark:bg-zinc-800/80">
                    <div class="mb-2 flex items-center gap-1">
                        <div class="size-1.5 rounded-full bg-red-400"></div>
                        <div class="size-1.5 rounded-full bg-yellow-400"></div>
                        <div class="size-1.5 rounded-full bg-green-400"></div>
                    </div>
                    <div class="h-2 w-8 rounded bg-yellow-200 dark:bg-yellow-500/30"></div>
                    <div class="mt-2 space-y-1">
                        <div class="h-3 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                        <div class="h-3 w-1/2 rounded bg-zinc-100 dark:bg-zinc-700"></div>
                    </div>
                </div>
            </div>

            {{-- Floating "100+" number in background --}}
            <div class="animate-float-slow absolute left-[18%] top-[30%] text-[80px] font-bold text-zinc-200/30 dark:text-white/[0.02]">100+</div>
        </div>

        <div class="relative mx-auto max-w-3xl px-6 text-center">
            <h2 class="text-balance text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                El mercado no necesita otro portal
            </h2>
            <p class="mt-8 text-lg text-zinc-600 dark:text-zinc-400">
                Mexico tiene mas de 100 plataformas inmobiliarias. Todas estan hechas para publicar.
            </p>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                Ninguna esta diseñada para como trabajas tu.
            </p>

            {{-- Visual separator with icon --}}
            <div class="my-10 flex items-center justify-center gap-4">
                <div class="h-px w-16 bg-gradient-to-r from-transparent to-zinc-300 dark:to-zinc-700"></div>
                <div class="flex size-12 items-center justify-center rounded-full border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-zinc-900">
                    <flux:icon.arrow-down class="size-5 text-zinc-400" />
                </div>
                <div class="h-px w-16 bg-gradient-to-l from-transparent to-zinc-300 dark:to-zinc-700"></div>
            </div>

            <p class="text-xl font-medium text-zinc-900 dark:text-white">
                PropertyManager no es un portal. Es tu herramienta de trabajo.
            </p>

            {{-- Mini unified interface mockup --}}
            <div class="mx-auto mt-10 max-w-sm">
                <div class="relative">
                    {{-- Glow effect --}}
                    <div class="absolute -inset-2 rounded-2xl bg-gradient-to-r from-violet-400/20 via-blue-400/20 to-fuchsia-400/20 blur-xl opacity-60 dark:opacity-40"></div>

                    <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-1 shadow-xl dark:border-white/10 dark:bg-zinc-900">
                        {{-- Browser chrome --}}
                        <div class="flex items-center gap-1.5 border-b border-zinc-100 px-3 py-2 dark:border-white/5">
                            <div class="flex gap-1">
                                <div class="size-2 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="size-2 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="size-2 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                            </div>
                            <div class="mx-auto h-5 w-32 rounded bg-zinc-100 dark:bg-zinc-800"></div>
                        </div>

                        {{-- App interface preview --}}
                        <div class="bg-zinc-50 p-3 dark:bg-zinc-900">
                            {{-- Search bar --}}
                            <div class="mb-3 flex gap-2">
                                <div class="h-7 flex-1 rounded-md bg-white ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-white/10"></div>
                                <div class="h-7 w-16 rounded-md bg-zinc-900 dark:bg-white"></div>
                            </div>
                            {{-- Property grid --}}
                            <div class="grid grid-cols-3 gap-2">
                                @for ($i = 0; $i < 3; $i++)
                                    <div class="overflow-hidden rounded-md bg-white ring-1 ring-zinc-100 dark:bg-zinc-800 dark:ring-white/5">
                                        <div class="aspect-[4/3] bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-700 dark:to-zinc-800"></div>
                                        <div class="p-1.5">
                                            <div class="h-2 w-3/4 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-600">Todo en un solo lugar</p>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section id="caracteristicas" class="relative scroll-mt-20 border-t border-zinc-200 bg-white py-24 lg:py-32 dark:border-white/5 dark:bg-[#08090a]">
        {{-- Decorative elements --}}
        <div class="absolute inset-0 -z-10 overflow-hidden">
            <div class="animate-float-slow absolute -right-20 top-20 h-40 w-40 rounded-full bg-blue-100/50 blur-3xl dark:bg-blue-500/10"></div>
            <div class="animate-float-medium absolute -left-20 bottom-20 h-32 w-32 rounded-full bg-violet-100/50 blur-3xl dark:bg-violet-500/10"></div>
        </div>

        <div class="mx-auto max-w-5xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    Todo lo que necesitas, nada que no
                </h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                    Herramientas simples para responder mas rapido.
                </p>
            </div>

            <div class="mt-16 grid gap-4 sm:grid-cols-2">
                {{-- Feature 1 --}}
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-8 backdrop-blur-sm transition-all duration-300 hover:border-blue-200 hover:shadow-lg hover:shadow-blue-500/5 dark:border-white/5 dark:bg-zinc-900/30 dark:hover:border-blue-500/30 dark:hover:shadow-blue-500/10">
                    {{-- Gradient overlay on hover --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 dark:from-blue-500/5"></div>
                    <div class="relative mb-5">
                        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-blue-100 ring-1 ring-blue-200 transition-transform duration-300 group-hover:scale-110 dark:bg-blue-500/10 dark:ring-blue-500/20">
                            <flux:icon.magnifying-glass class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                    <h3 class="relative text-lg font-medium text-zinc-900 dark:text-white">Una sola busqueda</h3>
                    <p class="relative mt-2 text-zinc-600 dark:text-zinc-400">
                        Resultados de Inmuebles24, Vivanuncios y mas — en una sola busqueda. Filtra por zona, precio, recamaras.
                    </p>
                </div>

                {{-- Feature 2 --}}
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-8 backdrop-blur-sm transition-all duration-300 hover:border-violet-200 hover:shadow-lg hover:shadow-violet-500/5 dark:border-white/5 dark:bg-zinc-900/30 dark:hover:border-violet-500/30 dark:hover:shadow-violet-500/10">
                    <div class="absolute inset-0 bg-gradient-to-br from-violet-50 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 dark:from-violet-500/5"></div>
                    <div class="relative mb-5">
                        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-violet-100 ring-1 ring-violet-200 transition-transform duration-300 group-hover:scale-110 dark:bg-violet-500/10 dark:ring-violet-500/20">
                            <flux:icon.folder-plus class="size-5 text-violet-600 dark:text-violet-400" />
                        </div>
                    </div>
                    <h3 class="relative text-lg font-medium text-zinc-900 dark:text-white">Colecciones para cada cliente</h3>
                    <p class="relative mt-2 text-zinc-600 dark:text-zinc-400">
                        Cada cliente busca algo diferente. Crea una coleccion, agrega propiedades con un tap, comparte con un link. Sin screenshots. Sin mensajes perdidos.
                    </p>
                </div>

                {{-- Feature 3 --}}
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-8 backdrop-blur-sm transition-all duration-300 hover:border-fuchsia-200 hover:shadow-lg hover:shadow-fuchsia-500/5 dark:border-white/5 dark:bg-zinc-900/30 dark:hover:border-fuchsia-500/30 dark:hover:shadow-fuchsia-500/10">
                    <div class="absolute inset-0 bg-gradient-to-br from-fuchsia-50 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 dark:from-fuchsia-500/5"></div>
                    <div class="relative mb-5">
                        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-fuchsia-100 ring-1 ring-fuchsia-200 transition-transform duration-300 group-hover:scale-110 dark:bg-fuchsia-500/10 dark:ring-fuchsia-500/20">
                            <flux:icon.share class="size-5 text-fuchsia-600 dark:text-fuchsia-400" />
                        </div>
                    </div>
                    <h3 class="relative text-lg font-medium text-zinc-900 dark:text-white">Comparte como profesional</h3>
                    <p class="relative mt-2 text-zinc-600 dark:text-zinc-400">
                        Links elegantes para WhatsApp. PDFs con tu marca para imprimir o enviar por correo. Tus clientes reciben una presentacion profesional.
                    </p>
                </div>

                {{-- Feature 4 --}}
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-8 backdrop-blur-sm transition-all duration-300 hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-500/5 dark:border-white/5 dark:bg-zinc-900/30 dark:hover:border-emerald-500/30 dark:hover:shadow-emerald-500/10">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 dark:from-emerald-500/5"></div>
                    <div class="relative mb-5">
                        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-emerald-100 ring-1 ring-emerald-200 transition-transform duration-300 group-hover:scale-110 dark:bg-emerald-500/10 dark:ring-emerald-500/20">
                            <flux:icon.device-phone-mobile class="size-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                    </div>
                    <h3 class="relative text-lg font-medium text-zinc-900 dark:text-white">Hecho para movil</h3>
                    <p class="relative mt-2 text-zinc-600 dark:text-zinc-400">
                        Tu oficina en tu bolsillo. Rapido incluso con datos limitados.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- How It Works --}}
    <section id="como-funciona" class="relative scroll-mt-20 border-t border-zinc-200 bg-zinc-100 py-24 lg:py-32 dark:border-white/5 dark:bg-[#08090a]">
        <div class="mx-auto max-w-5xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    Asi de simple
                </h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                    Tres pasos. Tres minutos. Listo.
                </p>
            </div>

            <div class="mt-16">
                <div class="relative grid gap-8 lg:grid-cols-3">
                    {{-- Connecting lines (desktop only) --}}
                    <div class="absolute left-0 right-0 top-10 hidden lg:block">
                        <div class="mx-auto flex max-w-2xl items-center justify-between px-24">
                            {{-- Line 1 to 2 --}}
                            <div class="h-px flex-1 bg-gradient-to-r from-blue-300 to-violet-300 dark:from-blue-500/30 dark:to-violet-500/30"></div>
                            <div class="mx-4 flex size-3 items-center justify-center">
                                <div class="size-2 animate-pulse rounded-full bg-violet-400 dark:bg-violet-500/50"></div>
                            </div>
                            {{-- Line 2 to 3 --}}
                            <div class="h-px flex-1 bg-gradient-to-r from-violet-300 to-fuchsia-300 dark:from-violet-500/30 dark:to-fuchsia-500/30"></div>
                        </div>
                    </div>

                    {{-- Step 1 --}}
                    <div class="text-center">
                        <div class="relative mx-auto mb-6 flex size-20 items-center justify-center">
                            <div class="absolute inset-0 animate-pulse rounded-2xl bg-gradient-to-b from-blue-400/30 to-transparent dark:from-blue-500/20"></div>
                            <div class="relative flex size-16 items-center justify-center rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-white/10 dark:bg-zinc-900">
                                <flux:icon.magnifying-glass class="size-7 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wider text-blue-600 dark:text-blue-400">Paso 1</p>
                        <h3 class="mt-2 text-lg font-medium text-zinc-900 dark:text-white">Busca</h3>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            Filtra por lo que busca tu cliente.
                        </p>
                    </div>

                    {{-- Step 2 --}}
                    <div class="text-center">
                        <div class="relative mx-auto mb-6 flex size-20 items-center justify-center">
                            <div class="absolute inset-0 animate-pulse rounded-2xl bg-gradient-to-b from-violet-400/30 to-transparent dark:from-violet-500/20" style="animation-delay: 200ms"></div>
                            <div class="relative flex size-16 items-center justify-center rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-white/10 dark:bg-zinc-900">
                                <flux:icon.plus-circle class="size-7 text-violet-600 dark:text-violet-400" />
                            </div>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wider text-violet-600 dark:text-violet-400">Paso 2</p>
                        <h3 class="mt-2 text-lg font-medium text-zinc-900 dark:text-white">Selecciona</h3>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            Agrega a coleccion con un tap.
                        </p>
                    </div>

                    {{-- Step 3 --}}
                    <div class="text-center">
                        <div class="relative mx-auto mb-6 flex size-20 items-center justify-center">
                            <div class="absolute inset-0 animate-pulse rounded-2xl bg-gradient-to-b from-fuchsia-400/30 to-transparent dark:from-fuchsia-500/20" style="animation-delay: 400ms"></div>
                            <div class="relative flex size-16 items-center justify-center rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-white/10 dark:bg-zinc-900">
                                <flux:icon.paper-airplane class="size-7 text-fuchsia-600 dark:text-fuchsia-400" />
                            </div>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wider text-fuchsia-600 dark:text-fuchsia-400">Paso 3</p>
                        <h3 class="mt-2 text-lg font-medium text-zinc-900 dark:text-white">Comparte</h3>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            Manda link o descarga PDF.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Social Proof / Value Props --}}
    <section class="relative border-t border-zinc-200 bg-white py-24 lg:py-32 dark:border-white/5 dark:bg-zinc-950">
        {{-- Decorative background --}}
        <div class="absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute right-0 top-1/2 h-[400px] w-[400px] -translate-y-1/2 translate-x-1/2 rounded-full bg-gradient-to-l from-emerald-100/50 to-transparent blur-3xl dark:from-emerald-500/10"></div>
        </div>

        <div class="mx-auto max-w-5xl px-6">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">Hecho para agentes</p>
                    <h2 class="mt-4 text-balance text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                        Tu ventaja competitiva empieza aqui
                    </h2>
                    <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                        Mientras otros siguen tomando screenshots, tu ya cerraste la venta.
                    </p>
                    <p class="mt-2 text-sm text-zinc-500">
                        Construido con feedback de agentes reales en Guadalajara.
                    </p>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-center gap-3 text-zinc-700 dark:text-zinc-300">
                            <div class="flex size-6 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/10">
                                <flux:icon.check class="size-4 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            Busqueda unificada de multiples portales
                        </li>
                        <li class="flex items-center gap-3 text-zinc-700 dark:text-zinc-300">
                            <div class="flex size-6 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/10">
                                <flux:icon.check class="size-4 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            Colecciones compartibles al instante
                        </li>
                        <li class="flex items-center gap-3 text-zinc-700 dark:text-zinc-300">
                            <div class="flex size-6 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/10">
                                <flux:icon.check class="size-4 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            PDFs profesionales con tu marca
                        </li>
                        <li class="flex items-center gap-3 text-zinc-700 dark:text-zinc-300">
                            <div class="flex size-6 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/10">
                                <flux:icon.check class="size-4 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            Optimizado para movil
                        </li>
                    </ul>
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 backdrop-blur-sm transition-all duration-300 hover:border-blue-200 hover:shadow-lg dark:border-white/5 dark:bg-zinc-900/30 dark:hover:border-blue-500/30">
                        <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-blue-100/50 blur-2xl transition-transform duration-300 group-hover:scale-150 dark:bg-blue-500/10"></div>
                        <p class="relative text-4xl font-semibold text-zinc-900 dark:text-white">3</p>
                        <p class="relative text-sm text-zinc-500">minutos promedio</p>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 backdrop-blur-sm transition-all duration-300 hover:border-violet-200 hover:shadow-lg dark:border-white/5 dark:bg-zinc-900/30 dark:hover:border-violet-500/30">
                        <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-violet-100/50 blur-2xl transition-transform duration-300 group-hover:scale-150 dark:bg-violet-500/10"></div>
                        <p class="relative text-4xl font-semibold text-zinc-900 dark:text-white">1</p>
                        <p class="relative text-sm text-zinc-500">busqueda en vez de 5</p>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 backdrop-blur-sm transition-all duration-300 hover:border-fuchsia-200 hover:shadow-lg dark:border-white/5 dark:bg-zinc-900/30 dark:hover:border-fuchsia-500/30">
                        <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-fuchsia-100/50 blur-2xl transition-transform duration-300 group-hover:scale-150 dark:bg-fuchsia-500/10"></div>
                        <p class="relative text-4xl font-semibold text-zinc-900 dark:text-white">0</p>
                        <p class="relative text-sm text-zinc-500">screenshots</p>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 backdrop-blur-sm transition-all duration-300 hover:border-emerald-200 hover:shadow-lg dark:border-white/5 dark:bg-zinc-900/30 dark:hover:border-emerald-500/30">
                        <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-emerald-100/50 blur-2xl transition-transform duration-300 group-hover:scale-150 dark:bg-emerald-500/10"></div>
                        <p class="relative text-4xl font-semibold text-zinc-900 dark:text-white">100%</p>
                        <p class="relative text-sm text-zinc-500">profesional</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative border-t border-zinc-200 bg-zinc-100 py-24 lg:py-32 dark:border-white/5 dark:bg-[#08090a]">
        {{-- Background --}}
        <div class="absolute inset-0 -z-10">
            <div class="absolute bottom-0 left-1/2 h-[300px] w-[500px] -translate-x-1/2 rounded-full bg-gradient-to-r from-blue-400/20 via-violet-400/20 to-fuchsia-400/20 blur-3xl dark:from-blue-500/10 dark:via-violet-500/10 dark:to-fuchsia-500/10"></div>
        </div>

        <div class="mx-auto max-w-2xl px-6 text-center">
            <h2 class="text-balance text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                Listo para responder mas rapido?
            </h2>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                Unete a los primeros agentes en Guadalajara que estan cambiando como trabajan.
            </p>

            <div class="mx-auto mt-10 max-w-md">
                @if($submitted)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 dark:border-emerald-500/20 dark:bg-emerald-500/5">
                        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-emerald-100 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:ring-emerald-500/20">
                            <flux:icon.check class="size-7 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <p class="text-lg font-medium text-zinc-900 dark:text-white">Ya estas en la lista</p>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Te contactamos cuando abramos acceso.</p>
                    </div>
                @else
                    <form wire:submit="submit" class="flex flex-col gap-3 sm:flex-row">
                        <input
                            type="email"
                            wire:model="email"
                            placeholder="Tu correo profesional"
                            class="flex-1 rounded-xl border border-zinc-200 bg-white px-5 py-4 text-zinc-900 placeholder-zinc-400 outline-none transition focus:border-zinc-400 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-zinc-500 dark:focus:border-white/20 dark:focus:bg-white/[0.07]"
                            required
                        />
                        <button
                            type="submit"
                            class="shrink-0 rounded-xl bg-zinc-900 px-6 py-4 font-medium text-white transition hover:bg-zinc-800 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>Solicitar acceso</span>
                            <span wire:loading class="flex items-center gap-2">
                                <flux:icon.arrow-path class="size-4 animate-spin" />
                            </span>
                        </button>
                    </form>
                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-600">
                        Guadalajara primero. Sin spam.
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-zinc-200 bg-white py-12 dark:border-white/5 dark:bg-[#08090a]">
        <div class="mx-auto max-w-5xl px-6">
            <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                <div class="flex items-center gap-3">
                    <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-900 dark:bg-white">
                        <x-app-logo-icon class="size-4 text-white dark:text-zinc-900" />
                    </div>
                    <span class="font-medium text-zinc-900 dark:text-white">PropertyManager</span>
                </div>

                <div class="flex items-center gap-6 text-sm text-zinc-500 dark:text-zinc-600">
                    <a href="#" class="transition hover:text-zinc-700 dark:hover:text-zinc-400">Terminos</a>
                    <a href="#" class="transition hover:text-zinc-700 dark:hover:text-zinc-400">Privacidad</a>
                    <a href="mailto:hola@propertymanager.mx" class="transition hover:text-zinc-700 dark:hover:text-zinc-400">Contacto</a>
                </div>

                <p class="text-sm text-zinc-400 dark:text-zinc-700">
                    Hecho con <span class="text-zinc-500">&hearts;</span> en Guadalajara
                </p>
            </div>
        </div>
    </footer>
</div>
