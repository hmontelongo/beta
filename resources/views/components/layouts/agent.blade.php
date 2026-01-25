<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body
        class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950"
        x-data="{
            copyToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-9999px';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }
            }
        }"
        @copy-to-clipboard.window="copyToClipboard($event.detail.text)"
        @open-url.window="window.open($event.detail.url, '_blank')"
    >
        {{-- Minimal Header --}}
        <header class="sticky top-0 z-50 border-b border-zinc-200/80 bg-white/95 backdrop-blur-sm supports-[backdrop-filter]:bg-white/80 dark:border-zinc-800 dark:bg-zinc-900/95 dark:supports-[backdrop-filter]:bg-zinc-900/80">
            <div class="mx-auto flex h-14 max-w-screen-2xl items-center justify-between px-4 sm:px-6 lg:px-8">
                {{-- Logo + Nav --}}
                <div class="flex items-center gap-6">
                    <a href="{{ route('agents.properties.index') }}" class="flex items-center gap-2" wire:navigate>
                        <x-app-logo class="h-7" />
                    </a>

                    {{-- Navigation Links --}}
                    <nav class="hidden items-center gap-1 sm:flex">
                        <a
                            href="{{ route('agents.properties.index') }}"
                            wire:navigate
                            @class([
                                'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                                'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' => request()->routeIs('agents.properties.*'),
                                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => !request()->routeIs('agents.properties.*'),
                            ])
                        >
                            Propiedades
                        </a>
                        <a
                            href="{{ route('agents.clients.index') }}"
                            wire:navigate
                            @class([
                                'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                                'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' => request()->routeIs('agents.clients.*'),
                                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => !request()->routeIs('agents.clients.*'),
                            ])
                        >
                            Clientes
                        </a>
                        <a
                            href="{{ route('agents.collections.index') }}"
                            wire:navigate
                            @class([
                                'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                                'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' => request()->routeIs('agents.collections.*'),
                                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => !request()->routeIs('agents.collections.*'),
                            ])
                        >
                            Colecciones
                        </a>
                    </nav>
                </div>

                {{-- Right Side: Collection + Profile --}}
                <div class="flex items-center gap-2">
                    {{ $headerActions ?? '' }}

                    {{-- Profile Dropdown --}}
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" size="sm" class="gap-2">
                            <span class="flex size-7 items-center justify-center rounded-full bg-zinc-100 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ auth()->user()->initials() }}
                            </span>
                            <flux:icon name="chevron-down" variant="micro" class="text-zinc-400" />
                        </flux:button>

                        <flux:menu class="w-56">
                            <div class="px-3 py-2">
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-zinc-500">{{ auth()->user()->email }}</p>
                            </div>

                            <flux:menu.separator />

                            <flux:menu.item :href="route('agents.clients.index')" icon="users" wire:navigate>
                                Mis Clientes
                            </flux:menu.item>
                            <flux:menu.item :href="route('agents.collections.index')" icon="folder" wire:navigate>
                                Mis Colecciones
                            </flux:menu.item>

                            <flux:menu.item :href="route('agents.profile.edit')" icon="cog-6-tooth" wire:navigate>
                                Configuracion
                            </flux:menu.item>

                            <flux:menu.separator />

                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full text-left">
                                    Cerrar sesion
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </header>

        {{-- Main Content --}}
        <main>
            {{ $slot }}
        </main>

        <flux:toast />

        @fluxScripts
    </body>
</html>
