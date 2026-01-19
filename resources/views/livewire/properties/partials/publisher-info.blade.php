<div class="space-y-4">
    {{-- Publisher Info (from raw_data) --}}
    @if (!empty($pub['publisher']['name']) || !empty($pub['publisher']['logo']))
        <div class="flex items-center gap-3">
            @if (!empty($pub['publisher']['logo']))
                <img
                    src="{{ $pub['publisher']['logo'] }}"
                    alt="{{ $pub['publisher']['name'] ?? '' }}"
                    class="size-12 rounded-lg object-cover bg-zinc-100"
                />
            @endif
            <div>
                @if (!empty($pub['publisher']['name']))
                    <flux:text class="font-medium">{{ $pub['publisher']['name'] }}</flux:text>
                @endif
                @if (!empty($pub['publisher']['type']))
                    <flux:badge size="xs" color="zinc">{{ ucfirst($pub['publisher']['type']) }}</flux:badge>
                @endif
            </div>
        </div>
    @endif

    {{-- Agent Info --}}
    @if (!empty($pub['agent']))
        <div>
            <flux:text size="sm" class="text-zinc-500">{{ __('Agent') }}</flux:text>
            <flux:text class="font-medium">{{ $pub['agent']['name'] }}</flux:text>
            @if (!empty($pub['agent']['phone']))
                <div class="flex items-center gap-1 mt-1">
                    <flux:icon name="phone" class="size-3 text-zinc-400" />
                    <a href="tel:{{ $pub['agent']['phone'] }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-blue-600">
                        {{ $pub['agent']['phone'] }}
                    </a>
                </div>
            @endif
            @if (!empty($pub['agent']['email']))
                <div class="flex items-center gap-1 mt-1">
                    <flux:icon name="envelope" class="size-3 text-zinc-400" />
                    <a href="mailto:{{ $pub['agent']['email'] }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-blue-600">
                        {{ $pub['agent']['email'] }}
                    </a>
                </div>
            @endif
        </div>
    @endif

    {{-- Agency Info --}}
    @if (!empty($pub['agency']))
        <div>
            <flux:text size="sm" class="text-zinc-500">{{ __('Agency') }}</flux:text>
            <flux:text class="font-medium">{{ $pub['agency']['name'] }}</flux:text>
            @if (!empty($pub['agency']['phone']))
                <div class="flex items-center gap-1 mt-1">
                    <flux:icon name="phone" class="size-3 text-zinc-400" />
                    <a href="tel:{{ $pub['agency']['phone'] }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-blue-600">
                        {{ $pub['agency']['phone'] }}
                    </a>
                </div>
            @endif
            @if (!empty($pub['agency']['email']))
                <div class="flex items-center gap-1 mt-1">
                    <flux:icon name="envelope" class="size-3 text-zinc-400" />
                    <a href="mailto:{{ $pub['agency']['email'] }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-blue-600">
                        {{ $pub['agency']['email'] }}
                    </a>
                </div>
            @endif
        </div>
    @endif

    {{-- WhatsApp (from raw_data or agent) --}}
    @if (!empty($pub['whatsapp']))
        <div class="flex items-center gap-2">
            <flux:icon name="chat-bubble-left" class="size-4 text-green-500" />
            <a
                href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $pub['whatsapp']) }}"
                target="_blank"
                class="text-sm text-green-600 hover:text-green-800 font-medium"
            >
                {{ __('Contact via WhatsApp') }}
            </a>
        </div>
    @endif

    {{-- View Original Listing --}}
    @if (!empty($pub['original_url']))
        <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button
                size="sm"
                variant="ghost"
                icon="arrow-top-right-on-square"
                :href="$pub['original_url']"
                target="_blank"
                class="w-full justify-center"
            >
                {{ __('View on') }} {{ $pub['platform'] }}
            </flux:button>
        </div>
    @endif
</div>
