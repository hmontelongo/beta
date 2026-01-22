<div class="space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl" level="1">{{ __('Platforms') }}</flux:heading>
        <flux:subheading>{{ __('Select a platform to manage search queries and scrape runs.') }}</flux:subheading>
    </div>

    {{-- Platform Cards --}}
    @if ($platforms->isEmpty())
        <flux:card class="flex flex-col items-center justify-center py-16">
            <flux:icon name="globe-alt" class="size-12 text-zinc-300 dark:text-zinc-600" />
            <flux:heading size="sm" class="mt-4">{{ __('No platforms configured') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Platforms are configured in the database.') }}</flux:text>
        </flux:card>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($platforms as $platform)
                <a
                    href="{{ route('admin.platforms.show', $platform) }}"
                    wire:navigate
                    wire:key="platform-{{ $platform->id }}"
                    class="group block"
                >
                    <flux:card class="relative transition-all hover:shadow-md dark:hover:border-zinc-600">
                        {{-- Active indicator --}}
                        @if ($platform->active_runs_count > 0)
                            <div class="absolute -right-1 -top-1 flex size-5 items-center justify-center">
                                <span class="absolute size-full animate-ping rounded-full bg-blue-400 opacity-75"></span>
                                <span class="relative size-3 rounded-full bg-blue-500"></span>
                            </div>
                        @endif

                        {{-- Platform Header --}}
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                                    <flux:icon name="globe-alt" class="size-5 text-zinc-600 dark:text-zinc-400" />
                                </div>
                                <div>
                                    <flux:heading size="sm">{{ $platform->name }}</flux:heading>
                                    <flux:text size="xs" class="text-zinc-500">{{ Str::limit($platform->base_url, 25) }}</flux:text>
                                </div>
                            </div>
                            @if ($platform->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </div>

                        <flux:separator class="my-4" />

                        {{-- Stats Row - Horizontal --}}
                        <div class="flex items-center justify-between">
                            <div class="text-center">
                                <flux:text size="xs" class="text-zinc-500">{{ __('Queries') }}</flux:text>
                                <flux:heading size="sm">{{ $platform->search_queries_count }}</flux:heading>
                            </div>
                            <flux:separator vertical class="h-8" />
                            <div class="text-center">
                                <flux:text size="xs" class="text-zinc-500">{{ __('Runs') }}</flux:text>
                                <flux:heading size="sm">{{ $platform->scrape_runs_count }}</flux:heading>
                            </div>
                            <flux:separator vertical class="h-8" />
                            <div class="text-center">
                                <flux:text size="xs" class="text-zinc-500">{{ __('Listings') }}</flux:text>
                                <flux:heading size="sm">{{ number_format($platform->listings_count) }}</flux:heading>
                            </div>
                        </div>

                        {{-- Active Runs Indicator --}}
                        @if ($platform->active_runs_count > 0)
                            <div class="mt-3 flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 dark:bg-blue-900/20">
                                <span class="size-2 animate-pulse rounded-full bg-blue-500"></span>
                                <flux:text size="sm" class="text-blue-700 dark:text-blue-400">
                                    {{ trans_choice(':count active run|:count active runs', $platform->active_runs_count, ['count' => $platform->active_runs_count]) }}
                                </flux:text>
                            </div>
                        @endif

                        {{-- View Details Link --}}
                        <div class="mt-3 flex items-center justify-end">
                            <flux:text size="sm" class="text-zinc-400 transition-colors group-hover:text-zinc-600 dark:group-hover:text-zinc-300">
                                {{ __('View details') }}
                            </flux:text>
                            <flux:icon name="chevron-right" class="ml-1 size-4 text-zinc-400 transition-colors group-hover:text-zinc-600 dark:group-hover:text-zinc-300" />
                        </div>
                    </flux:card>
                </a>
            @endforeach
        </div>
    @endif
</div>
