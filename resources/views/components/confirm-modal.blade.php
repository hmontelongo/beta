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
            <flux:modal.close class="flex-1">
                <flux:button variant="ghost" class="w-full">{{ $cancelText }}</flux:button>
            </flux:modal.close>
            <div class="flex-1 [&>*]:w-full">
                {{ $slot }}
            </div>
        </div>
    </div>
</flux:modal>
