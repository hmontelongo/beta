<div class="mx-auto max-w-2xl px-4 py-8">
    {{-- Header --}}
    <div class="mb-8 text-center">
        <flux:heading size="xl" class="mb-2">Configuracion de colaboracion</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400">
            Decide si otros agentes pueden ofrecer tu propiedad.
        </flux:text>
    </div>

    <flux:card class="space-y-8">
        {{-- Sharing Options --}}
        <flux:radio.group
            wire:model.live="sharingOption"
            variant="cards"
            class="flex-col"
        >
            <flux:radio
                value="private"
                icon="lock-closed"
                label="Solo yo"
                description="Solo tu podras compartir esta propiedad con clientes. Mantendras el control total."
            />
            <flux:radio
                value="collaborative"
                icon="user-group"
                label="Abierta a colaboracion"
                description="Otros agentes podran incluirla en sus colecciones y compartirla con sus clientes."
            />
        </flux:radio.group>

        {{-- Commission Split (only shown when collaborative) --}}
        @if($this->isCollaborative)
            <div
                class="space-y-4"
                x-data
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
            >
                <flux:separator />

                <div>
                    <flux:heading size="sm" class="mb-2">Comision compartida</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        Porcentaje de tu comision que compartes con el agente que cierre la venta.
                    </flux:text>
                </div>

                {{-- Commission Presets --}}
                <div class="flex flex-wrap gap-3">
                    @foreach($commissionPresets as $preset)
                        <flux:button
                            type="button"
                            wire:click="setCommission({{ $preset }})"
                            variant="{{ $commissionSplit == $preset ? 'primary' : 'ghost' }}"
                            class="min-w-[80px]"
                        >
                            {{ (int) $preset }}%
                        </flux:button>
                    @endforeach

                    <div class="flex items-center gap-2">
                        <flux:input
                            wire:model.live.debounce.500ms="customCommission"
                            type="number"
                            placeholder="Otro"
                            min="1"
                            max="100"
                            class="w-24"
                        />
                        <flux:text size="sm" class="text-zinc-500">%</flux:text>
                    </div>
                </div>

                {{-- Commission Info --}}
                @if($commissionSplit)
                    <flux:callout icon="information-circle" class="bg-blue-50 dark:bg-blue-950/30">
                        <flux:callout.text>
                            Si otro agente cierra la venta, recibira el <strong>{{ (int) $commissionSplit }}%</strong> de la comision total.
                            Tu conservas el <strong>{{ 100 - (int) $commissionSplit }}%</strong> restante.
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>
        @endif

        {{-- Non-collaborative Benefits --}}
        @unless($this->isCollaborative)
            <flux:callout icon="shield-check" class="bg-green-50 dark:bg-green-950/30">
                <flux:callout.heading>Propiedad privada</flux:callout.heading>
                <flux:callout.text>
                    Tu propiedad solo sera visible para ti. Puedes cambiar esto despues en cualquier momento.
                </flux:callout.text>
            </flux:callout>
        @endunless

        {{-- Form Actions --}}
        <div class="flex items-center justify-between pt-4">
            <flux:button
                type="button"
                wire:click="back"
                variant="ghost"
                icon="arrow-left"
            >
                Volver
            </flux:button>

            <flux:button
                type="button"
                wire:click="publish"
                variant="primary"
                icon:trailing="check"
                wire:loading.attr="disabled"
                wire:target="publish"
            >
                <span wire:loading.remove wire:target="publish">Publicar propiedad</span>
                <span wire:loading wire:target="publish">Publicando...</span>
            </flux:button>
        </div>
    </flux:card>

    {{-- Step Indicator --}}
    <div class="mt-6 flex justify-center gap-2">
        <div class="h-2 w-8 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-900 dark:bg-white"></div>
    </div>
</div>
