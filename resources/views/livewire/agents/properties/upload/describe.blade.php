<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4">
    <div class="w-full max-w-2xl">
        {{-- Header --}}
        <div class="mb-8 text-center">
            <flux:heading size="xl" class="mb-2">Describe tu propiedad</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                Escribe como si le contaras a un cliente. Nosotros extraemos los datos.
            </flux:text>
        </div>

        {{-- Main Form --}}
        <form wire:submit="continue">
            <flux:card class="space-y-6">
                <flux:textarea
                    wire:model="description"
                    rows="8"
                    placeholder="Ej: Tengo una casa en Providencia, 4 recamaras, 3 banos completos, 350m2 de construccion en terreno de 400m2. Tiene roof garden, alberca y estacionamiento para 3 autos. Esta en venta en 6.5 millones de pesos..."
                    class="text-lg"
                    resize="vertical"
                />

                <flux:error name="description" />

                {{-- Tips --}}
                <flux:callout icon="light-bulb" class="bg-amber-50 dark:bg-amber-950/30">
                    <flux:callout.heading>Tip</flux:callout.heading>
                    <flux:callout.text>
                        Incluye ubicacion, precio, recamaras, banos, tamano y cualquier detalle relevante.
                        Entre mas detalles, mejor.
                    </flux:callout.text>
                </flux:callout>

                {{-- Submit Button --}}
                <div class="flex justify-end">
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon:trailing="arrow-right"
                        wire:loading.attr="disabled"
                        wire:target="continue"
                    >
                        <span wire:loading.remove wire:target="continue">Continuar</span>
                        <span wire:loading wire:target="continue">Procesando...</span>
                    </flux:button>
                </div>
            </flux:card>
        </form>

        {{-- Step Indicator --}}
        <div class="mt-6 flex justify-center gap-2">
            <div class="h-2 w-8 rounded-full bg-zinc-900 dark:bg-white"></div>
            <div class="h-2 w-8 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
            <div class="h-2 w-8 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
            <div class="h-2 w-8 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
        </div>
    </div>
</div>
