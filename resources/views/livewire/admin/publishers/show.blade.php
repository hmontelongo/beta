<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('publishers.index')" wire:navigate aria-label="{{ __('Back to publishers') }}" />
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl" level="1">{{ $publisher->name }}</flux:heading>
                    <flux:badge :color="$publisher->type->color()">
                        <flux:icon :name="$publisher->type->icon()" variant="micro" class="mr-1" />
                        {{ $publisher->type->label() }}
                    </flux:badge>
                </div>
                <flux:subheading>{{ __('Publisher profile and associated properties.') }}</flux:subheading>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Left Column: Details --}}
        <div class="space-y-6">
            {{-- Contact Info Card --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Contact Information') }}</flux:heading>
                <div class="space-y-4">
                    @if ($publisher->phone)
                        <div class="flex items-center gap-3">
                            <flux:icon name="phone" class="text-zinc-400" />
                            <div>
                                <flux:text size="sm" class="text-zinc-500">{{ __('Phone') }}</flux:text>
                                <flux:text>{{ $publisher->phone }}</flux:text>
                            </div>
                        </div>
                    @endif
                    @if ($publisher->whatsapp && $publisher->whatsapp !== $publisher->phone)
                        <div class="flex items-center gap-3">
                            <flux:icon name="chat-bubble-left-right" class="text-green-500" />
                            <div>
                                <flux:text size="sm" class="text-zinc-500">{{ __('WhatsApp') }}</flux:text>
                                <flux:text>{{ $publisher->whatsapp }}</flux:text>
                            </div>
                        </div>
                    @endif
                    @if ($publisher->email)
                        <div class="flex items-center gap-3">
                            <flux:icon name="envelope" class="text-zinc-400" />
                            <div>
                                <flux:text size="sm" class="text-zinc-500">{{ __('Email') }}</flux:text>
                                <flux:text>{{ $publisher->email }}</flux:text>
                            </div>
                        </div>
                    @endif
                    @if (!$publisher->phone && !$publisher->whatsapp && !$publisher->email)
                        <flux:text class="text-zinc-400">{{ __('No contact information available.') }}</flux:text>
                    @endif
                </div>
            </flux:card>

            {{-- Stats Card --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Statistics') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <flux:heading size="xl" class="text-purple-600">{{ $this->stats['properties'] }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Properties') }}</flux:text>
                    </div>
                    <div class="text-center">
                        <flux:heading size="xl">{{ $this->stats['listings'] }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Listings') }}</flux:text>
                    </div>
                </div>
            </flux:card>

            {{-- Platform Profiles Card --}}
            @if (count($this->platformProfiles) > 0)
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4">{{ __('Platform Profiles') }}</flux:heading>
                    <div class="space-y-4">
                        @foreach ($this->platformProfiles as $platform => $profile)
                            <div class="flex items-start justify-between gap-3 pb-3 border-b last:border-b-0 last:pb-0 border-zinc-100 dark:border-zinc-700">
                                <div class="flex items-center gap-2">
                                    @if (!empty($profile['logo']))
                                        <img src="{{ $profile['logo'] }}" alt="{{ $platform }}" class="w-6 h-6 rounded" />
                                    @else
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-zinc-100 dark:bg-zinc-700 text-xs font-medium">
                                            {{ strtoupper(substr($platform, 0, 1)) }}
                                        </span>
                                    @endif
                                    <div>
                                        <flux:text class="font-medium capitalize">{{ $platform }}</flux:text>
                                        @if (!empty($profile['id']))
                                            <flux:text size="sm" class="text-zinc-500">ID: {{ $profile['id'] }}</flux:text>
                                        @endif
                                    </div>
                                </div>
                                @if (!empty($profile['url']))
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="arrow-top-right-on-square"
                                        href="{{ $profile['url'] }}"
                                        target="_blank"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif
        </div>

        {{-- Right Column: Properties --}}
        <div class="lg:col-span-2">
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-2">{{ __('Properties') }}</flux:heading>
                <flux:text size="sm" class="text-zinc-500 mb-4">
                    {{ __('Properties associated with this publisher across all platforms.') }}
                </flux:text>

                @if ($properties->isEmpty())
                    <div class="py-8 text-center">
                        <flux:icon name="home" class="mx-auto w-12 h-12 text-zinc-300" />
                        <flux:text class="mt-2 text-zinc-500">{{ __('No properties found for this publisher.') }}</flux:text>
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Address') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('Listings') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($properties as $property)
                                <flux:table.row wire:key="property-{{ $property->id }}">
                                    <flux:table.cell class="max-w-xs">
                                        <flux:heading size="sm" class="truncate">
                                            {{ $property->address ?? __('Unknown Address') }}
                                        </flux:heading>
                                        <flux:text size="sm" class="truncate text-zinc-500">
                                            {{ $property->colonia }}{{ $property->colonia && $property->city ? ', ' : '' }}{{ $property->city }}
                                        </flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($property->property_type)
                                            <flux:badge size="sm">{{ ucfirst($property->property_type->value) }}</flux:badge>
                                        @else
                                            <flux:text class="text-zinc-400">-</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" color="purple">{{ $property->listings_count }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            :href="route('admin.properties.show', $property)"
                                            wire:navigate
                                        />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            @if ($properties->hasPages())
                <div class="mt-4">
                    {{ $properties->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
