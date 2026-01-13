<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Review Duplicates') }}</flux:heading>
            <flux:subheading>{{ __('Review potential duplicate listings and confirm or reject matches.') }}</flux:subheading>
        </div>
        <flux:badge size="lg" color="amber">{{ $this->pendingCount }} {{ __('pending') }}</flux:badge>
    </div>

    @if ($this->candidate)
        {{-- Match Scores --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <flux:text size="sm" class="text-zinc-500">{{ __('Overall') }}</flux:text>
                        <flux:heading size="lg" class="{{ $this->candidate->overall_score >= 0.8 ? 'text-green-600' : ($this->candidate->overall_score >= 0.6 ? 'text-amber-600' : 'text-red-600') }}">
                            {{ number_format($this->candidate->overall_score * 100) }}%
                        </flux:heading>
                    </div>
                    <flux:separator vertical class="h-12" />
                    <div class="text-center">
                        <flux:text size="sm" class="text-zinc-500">{{ __('Location') }}</flux:text>
                        <flux:text class="font-semibold">{{ number_format($this->candidate->coordinate_score * 100) }}%</flux:text>
                    </div>
                    <div class="text-center">
                        <flux:text size="sm" class="text-zinc-500">{{ __('Address') }}</flux:text>
                        <flux:text class="font-semibold">{{ number_format($this->candidate->address_score * 100) }}%</flux:text>
                    </div>
                    <div class="text-center">
                        <flux:text size="sm" class="text-zinc-500">{{ __('Features') }}</flux:text>
                        <flux:text class="font-semibold {{ $this->candidate->features_score < 0.5 ? 'text-red-600' : '' }}">
                            {{ number_format($this->candidate->features_score * 100) }}%
                        </flux:text>
                    </div>
                    @if ($this->candidate->distance_meters !== null)
                        <div class="text-center">
                            <flux:text size="sm" class="text-zinc-500">{{ __('Distance') }}</flux:text>
                            <flux:text class="font-semibold">{{ number_format($this->candidate->distance_meters) }}m</flux:text>
                        </div>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:button
                        wire:click="rejectMatch"
                        wire:loading.attr="disabled"
                        variant="danger"
                        icon="x-mark"
                    >
                        {{ __('Different') }}
                    </flux:button>
                    <flux:button
                        wire:click="confirmMatch"
                        wire:loading.attr="disabled"
                        variant="primary"
                        icon="check"
                    >
                        {{ __('Same Property') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        {{-- Side by Side Comparison --}}
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ([$this->candidate->listingA, $this->candidate->listingB] as $listing)
                <flux:card>
                    {{-- Header --}}
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <flux:heading size="sm" class="truncate">
                                {{ $listing->raw_data['title'] ?? 'Untitled' }}
                            </flux:heading>
                            <flux:text size="sm" class="text-zinc-500">
                                {{ $listing->platform->name }} · {{ $listing->external_id }}
                            </flux:text>
                        </div>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-top-right-on-square"
                            :href="route('listings.show', $listing)"
                            target="_blank"
                        />
                    </div>

                    {{-- Image --}}
                    @php
                        $firstImage = collect($listing->raw_data['images'] ?? [])
                            ->map(fn ($img) => is_array($img) ? $img['url'] : $img)
                            ->filter(fn ($url) => !str_contains($url, '.svg') && !str_contains($url, 'placeholder'))
                            ->first();
                    @endphp
                    @if ($firstImage)
                        <div class="mb-4 aspect-video overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                            <img src="{{ $firstImage }}" alt="Property" class="h-full w-full object-cover" />
                        </div>
                    @endif

                    {{-- Price --}}
                    @if (!empty($listing->raw_data['operations']))
                        <div class="mb-4">
                            @foreach ($listing->raw_data['operations'] as $op)
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" :color="$op['type'] === 'rent' ? 'blue' : 'green'">
                                        {{ $op['type'] === 'rent' ? 'Rent' : 'Sale' }}
                                    </flux:badge>
                                    <flux:heading size="lg">
                                        ${{ number_format($op['price'] ?? 0) }}
                                        <span class="text-sm font-normal text-zinc-500">{{ $op['currency'] ?? 'MXN' }}</span>
                                    </flux:heading>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Features Comparison --}}
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Type') }}</flux:text>
                            <flux:text>{{ ucfirst($listing->raw_data['property_type'] ?? '-') }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Bedrooms') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['bedrooms'] ?? '-' }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Bathrooms') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['bathrooms'] ?? '-' }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Built Size') }}</flux:text>
                            <flux:text>{{ isset($listing->raw_data['built_size_m2']) ? number_format($listing->raw_data['built_size_m2']) . ' m²' : '-' }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Lot Size') }}</flux:text>
                            <flux:text>{{ isset($listing->raw_data['lot_size_m2']) ? number_format($listing->raw_data['lot_size_m2']) . ' m²' : '-' }}</flux:text>
                        </div>
                    </div>

                    <flux:separator class="my-4" />

                    {{-- Location --}}
                    <div class="space-y-1">
                        @if (!empty($listing->raw_data['address']))
                            <flux:text size="sm">{{ $listing->raw_data['address'] }}</flux:text>
                        @endif
                        <flux:text size="sm" class="text-zinc-500">
                            {{ collect([$listing->raw_data['colonia'] ?? null, $listing->raw_data['city'] ?? null, $listing->raw_data['state'] ?? null])->filter()->implode(', ') }}
                        </flux:text>
                        @if (!empty($listing->raw_data['latitude']) && !empty($listing->raw_data['longitude']))
                            <flux:text size="xs" class="text-zinc-400">
                                {{ number_format($listing->raw_data['latitude'], 6) }}, {{ number_format($listing->raw_data['longitude'], 6) }}
                            </flux:text>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        {{-- Queue List --}}
        @if ($this->candidates->count() > 1)
            <flux:card>
                <flux:heading size="sm" class="mb-4">{{ __('Review Queue') }}</flux:heading>
                <div class="space-y-2">
                    @foreach ($this->candidates as $c)
                        <button
                            wire:click="selectCandidate({{ $c->id }})"
                            class="w-full flex items-center justify-between p-3 rounded-lg text-left transition {{ $c->id === $this->currentCandidateId ? 'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800' }}"
                        >
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="truncate">
                                    {{ Str::limit($c->listingA->raw_data['title'] ?? $c->listingA->external_id, 30) }}
                                    <span class="text-zinc-400">vs</span>
                                    {{ Str::limit($c->listingB->raw_data['title'] ?? $c->listingB->external_id, 30) }}
                                </flux:text>
                            </div>
                            <flux:badge size="sm" :color="$c->overall_score >= 0.8 ? 'green' : ($c->overall_score >= 0.6 ? 'amber' : 'red')">
                                {{ number_format($c->overall_score * 100) }}%
                            </flux:badge>
                        </button>
                    @endforeach
                </div>
            </flux:card>
        @endif
    @else
        {{-- Empty State --}}
        <flux:card class="p-12 text-center">
            <flux:icon name="check-circle" class="mx-auto h-12 w-12 text-green-500" />
            <flux:heading size="lg" class="mt-4">{{ __('All caught up!') }}</flux:heading>
            <flux:text class="mt-2 text-zinc-500">{{ __('No duplicate candidates need review right now.') }}</flux:text>
            <flux:button :href="route('listings.index')" wire:navigate class="mt-4">
                {{ __('Back to Listings') }}
            </flux:button>
        </flux:card>
    @endif
</div>
