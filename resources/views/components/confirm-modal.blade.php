@props([
    'name',
    'title',
    'message',
    'cancelText' => 'Cancelar',
])

<flux:modal :name="$name" class="min-w-[22rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:text class="mt-2">{{ $message }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">{{ $cancelText }}</flux:button>
            </flux:modal.close>
            {{ $slot }}
        </div>
    </div>
</flux:modal>
