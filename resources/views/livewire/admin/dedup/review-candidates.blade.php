<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Review Listing Groups') }}</flux:heading>
            <flux:subheading>{{ __('Review potential matches and approve or reject groups for AI processing.') }}</flux:subheading>
        </div>
        <flux:badge size="lg" color="amber">{{ $this->pendingCount }} {{ __('pending') }}</flux:badge>
    </div>

    @if ($this->group)
        {{-- AI Error Alert --}}
        @if ($this->group->rejection_reason)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('AI Processing Failed') }}</flux:callout.heading>
                <flux:callout.text class="mb-3">{{ $this->group->rejection_reason }}</flux:callout.text>
                <flux:button
                    wire:click="retryAiProcessing"
                    wire:loading.attr="disabled"
                    wire:target="retryAiProcessing"
                    size="sm"
                    variant="filled"
                    icon="arrow-path"
                >
                    <span wire:loading.remove wire:target="retryAiProcessing">{{ __('Retry AI Processing') }}</span>
                    <span wire:loading wire:target="retryAiProcessing">{{ __('Retrying...') }}</span>
                </flux:button>
            </flux:callout>
        @endif

        {{-- Group Info --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <flux:text size="sm" class="text-zinc-500">{{ __('Match Score') }}</flux:text>
                        @if ($this->group->match_score !== null)
                            <flux:heading size="lg" class="{{ $this->group->match_score >= 0.8 ? 'text-green-600' : ($this->group->match_score >= 0.6 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ number_format($this->group->match_score * 100) }}%
                            </flux:heading>
                        @else
                            <flux:heading size="lg" class="text-zinc-400">{{ __('N/A') }}</flux:heading>
                        @endif
                    </div>
                    <flux:separator vertical class="h-12" />
                    <div class="text-center">
                        <flux:text size="sm" class="text-zinc-500">{{ __('Comparing') }}</flux:text>
                        @if ($this->group->matched_property_id)
                            <flux:heading size="lg">1 <span class="text-sm font-normal text-zinc-500">{{ __('vs Property') }}</span></flux:heading>
                        @else
                            <flux:heading size="lg">{{ $this->group->listings->count() }} {{ __('listings') }}</flux:heading>
                        @endif
                    </div>
                    <div class="text-center">
                        <flux:text size="sm" class="text-zinc-500">{{ __('Status') }}</flux:text>
                        <flux:badge :color="$this->group->status->color()">
                            {{ $this->group->status->label() }}
                        </flux:badge>
                    </div>
                </div>
                <div class="flex gap-2">
                    <flux:tooltip content="{{ __('These listings are NOT the same property. Separate and re-process them individually.') }}">
                        <flux:button
                            wire:click="rejectGroup"
                            wire:loading.attr="disabled"
                            wire:target="rejectGroup"
                            variant="danger"
                            icon="x-mark"
                        >
                            <span wire:loading.remove wire:target="rejectGroup">{{ __('Not a Match') }}</span>
                            <span wire:loading wire:target="rejectGroup">{{ __('Separating...') }}</span>
                        </flux:button>
                    </flux:tooltip>
                    <flux:tooltip content="{{ __('Confirm these listings represent the same property. Send to AI for property creation.') }}">
                        <flux:button
                            wire:click="approveGroup"
                            wire:loading.attr="disabled"
                            wire:target="approveGroup"
                            variant="primary"
                            icon="check"
                        >
                            <span wire:loading.remove wire:target="approveGroup">{{ __('Confirm Match') }}</span>
                            <span wire:loading wire:target="approveGroup">{{ __('Confirming...') }}</span>
                        </flux:button>
                    </flux:tooltip>
                </div>
            </div>
        </flux:card>

        {{-- Matched Property Alert --}}
        @if ($this->group->matchedProperty)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Potential Duplicate of Existing Property') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('This listing may be a duplicate of an existing property. Compare below and decide if they should be merged.') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Listings Grid --}}
        <div class="grid gap-6 {{ $this->group->matchedProperty ? 'lg:grid-cols-2' : ($this->group->listings->count() > 2 ? 'lg:grid-cols-3' : 'lg:grid-cols-2') }}">
            {{-- Matched Property Listings (if exists) --}}
            @if ($this->group->matchedProperty)
                @php
                    $property = $this->group->matchedProperty;
                    $propertyListing = $property->listings->first();
                @endphp
                @if ($propertyListing)
                    <flux:card class="border-2 border-amber-300 dark:border-amber-700 bg-amber-50/50 dark:bg-amber-900/10">
                        {{-- Header --}}
                        <div class="mb-4 flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <flux:badge size="sm" color="amber">{{ __('Existing Property') }} #{{ $property->id }}</flux:badge>
                                </div>
                                <flux:heading size="sm" class="truncate">
                                    {{ $propertyListing->raw_data['title'] ?? 'Untitled' }}
                                </flux:heading>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ $propertyListing->platform->name }} · {{ $propertyListing->external_id }}
                                </flux:text>
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="arrow-top-right-on-square"
                                    :href="route('admin.properties.show', $property)"
                                    target="_blank"
                                    title="{{ __('View Property') }}"
                                />
                            </div>
                        </div>

                        {{-- Image --}}
                        @php
                            $firstImage = collect($propertyListing->raw_data['images'] ?? [])
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
                        @if (!empty($propertyListing->raw_data['operations']))
                            <div class="mb-4">
                                @foreach ($propertyListing->raw_data['operations'] as $op)
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

                        {{-- Features --}}
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500">{{ __('Type') }}</flux:text>
                                <flux:text>{{ ucfirst($propertyListing->raw_data['property_type'] ?? '-') }}</flux:text>
                            </div>
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500">{{ __('Bedrooms') }}</flux:text>
                                <flux:text>{{ $propertyListing->raw_data['bedrooms'] ?? '-' }}</flux:text>
                            </div>
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500">{{ __('Bathrooms') }}</flux:text>
                                <flux:text>{{ $propertyListing->raw_data['bathrooms'] ?? '-' }}</flux:text>
                            </div>
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500">{{ __('Built Size') }}</flux:text>
                                <flux:text>{{ isset($propertyListing->raw_data['built_size_m2']) ? number_format($propertyListing->raw_data['built_size_m2']) . ' m²' : '-' }}</flux:text>
                            </div>
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500">{{ __('Lot Size') }}</flux:text>
                                <flux:text>{{ isset($propertyListing->raw_data['lot_size_m2']) ? number_format($propertyListing->raw_data['lot_size_m2']) . ' m²' : '-' }}</flux:text>
                            </div>
                        </div>

                        <flux:separator class="my-4" />

                        {{-- Location --}}
                        <div class="space-y-1">
                            @if (!empty($propertyListing->raw_data['address']))
                                <flux:text size="sm">{{ $propertyListing->raw_data['address'] }}</flux:text>
                            @endif
                            <flux:text size="sm" class="text-zinc-500">
                                {{ collect([$propertyListing->raw_data['colonia'] ?? null, $propertyListing->raw_data['city'] ?? null, $propertyListing->raw_data['state'] ?? null])->filter()->implode(', ') }}
                            </flux:text>
                            @if (!empty($propertyListing->raw_data['latitude']) && !empty($propertyListing->raw_data['longitude']))
                                <flux:text size="xs" class="text-zinc-400">
                                    {{ number_format($propertyListing->raw_data['latitude'], 6) }}, {{ number_format($propertyListing->raw_data['longitude'], 6) }}
                                </flux:text>
                            @endif
                        </div>
                    </flux:card>
                @endif
            @endif

            {{-- New Listing Cards --}}
            @foreach ($this->group->listings as $listing)
                <flux:card>
                    {{-- Header --}}
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm" class="truncate">
                                    {{ $listing->raw_data['title'] ?? 'Untitled' }}
                                </flux:heading>
                                @if ($listing->is_primary_in_group)
                                    <flux:badge size="sm" color="blue">{{ __('Primary') }}</flux:badge>
                                @endif
                            </div>
                            <flux:text size="sm" class="text-zinc-500">
                                {{ $listing->platform->name }} · {{ $listing->external_id }}
                            </flux:text>
                        </div>
                        <div class="flex items-center gap-1">
                            @if ($this->group->listings->count() > 1)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="x-mark"
                                    wire:click="removeListingFromGroup({{ $listing->id }})"
                                    wire:loading.attr="disabled"
                                    wire:confirm="{{ __('Remove this listing from the group? It will be re-processed separately.') }}"
                                    title="{{ __('Remove from group') }}"
                                />
                            @endif
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="arrow-top-right-on-square"
                                :href="route('listings.show', $listing)"
                                target="_blank"
                            />
                        </div>
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

                    {{-- Features --}}
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

        {{-- Rejection Reason (optional) --}}
        <flux:card class="p-4">
            <flux:input
                wire:model="rejectionReason"
                label="{{ __('Rejection Reason (optional)') }}"
                placeholder="{{ __('Why are these listings not a match?') }}"
            />
        </flux:card>

        {{-- Queue List --}}
        @if ($this->groups->count() > 1)
            <flux:card>
                <flux:heading size="sm" class="mb-4">{{ __('Review Queue') }}</flux:heading>
                <div class="space-y-2">
                    @foreach ($this->groups as $g)
                        <button
                            wire:click="selectGroup({{ $g->id }})"
                            class="w-full flex items-center justify-between p-3 rounded-lg text-left transition {{ $g->id === $this->currentGroupId ? 'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800' }}"
                        >
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="truncate">
                                    @foreach ($g->listings->take(3) as $listing)
                                        {{ Str::limit($listing->raw_data['title'] ?? $listing->external_id, 25) }}{{ !$loop->last ? ' • ' : '' }}
                                    @endforeach
                                    @if ($g->listings->count() > 3)
                                        <span class="text-zinc-400">+{{ $g->listings->count() - 3 }} {{ __('more') }}</span>
                                    @endif
                                </flux:text>
                                <flux:text size="xs" class="text-zinc-500">
                                    @if ($g->matched_property_id)
                                        1 {{ __('listing vs Property') }}
                                    @else
                                        {{ $g->listings->count() }} {{ __('listings') }}
                                    @endif
                                </flux:text>
                            </div>
                            @if ($g->match_score !== null)
                                <flux:badge size="sm" :color="$g->match_score >= 0.8 ? 'green' : ($g->match_score >= 0.6 ? 'amber' : 'red')">
                                    {{ number_format($g->match_score * 100) }}%
                                </flux:badge>
                            @else
                                <flux:badge size="sm" color="blue">{{ __('Unique') }}</flux:badge>
                            @endif
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
            <flux:text class="mt-2 text-zinc-500">{{ __('No listing groups need review right now.') }}</flux:text>
            <flux:button :href="route('listings.index')" wire:navigate class="mt-4">
                {{ __('Back to Listings') }}
            </flux:button>
        </flux:card>
    @endif
</div>
