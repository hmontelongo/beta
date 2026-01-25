<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your profile information')">
        @if ($user->isAgent())
            {{-- Avatar Section for Agents --}}
            <div class="my-6 flex items-center gap-6">
                <flux:file-upload wire:model="avatar" accept="image/*">
                    <div class="relative flex size-20 cursor-pointer items-center justify-center rounded-full border border-zinc-200 bg-zinc-100 transition-colors hover:border-zinc-300 hover:bg-zinc-200 in-data-dragging:border-blue-400 in-data-dragging:bg-blue-50 dark:border-white/10 dark:bg-white/10 dark:hover:border-white/20 dark:hover:bg-white/15 dark:in-data-dragging:border-blue-500 dark:in-data-dragging:bg-blue-900/20">
                        @if ($avatar && $avatar->isPreviewable())
                            <img src="{{ $avatar->temporaryUrl() }}" alt="Preview" class="size-full rounded-full object-cover" />
                        @elseif ($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="size-full rounded-full object-cover" />
                        @else
                            <flux:icon name="user" variant="solid" class="size-8 text-zinc-400 dark:text-zinc-500" />
                        @endif

                        {{-- Upload indicator --}}
                        <div class="absolute -bottom-1 -right-1 flex size-7 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">
                            <flux:icon name="camera" variant="solid" class="size-4 text-zinc-500 dark:text-zinc-400" />
                        </div>
                    </div>
                </flux:file-upload>

                <div class="flex flex-col gap-2">
                    @if ($avatar)
                        <div class="flex gap-2">
                            <flux:button wire:click="saveAvatar" size="sm" variant="primary">
                                {{ __('Save photo') }}
                            </flux:button>
                            <flux:button wire:click="$set('avatar', null)" size="sm" variant="ghost">
                                {{ __('Cancel') }}
                            </flux:button>
                        </div>
                    @else
                        @if ($user->avatar_url)
                            <flux:button
                                wire:click="deleteAvatar"
                                wire:confirm="{{ __('Are you sure you want to remove your profile photo?') }}"
                                size="sm"
                                variant="subtle"
                                icon="trash"
                                class="text-red-600 hover:text-red-700"
                            >
                                {{ __('Remove photo') }}
                            </flux:button>
                        @else
                            <flux:text size="sm" class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Click to upload') }}</flux:text>
                        @endif
                    @endif
                    <flux:text size="sm" class="text-zinc-500">{{ __('JPG, PNG. Max 2MB.') }}</flux:text>
                    <flux:error name="avatar" />
                </div>
            </div>
        @endif

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" type="text" required autofocus autocomplete="name" />
                <flux:error name="name" />
            </flux:field>

            <div>
                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model="email" type="email" required autocomplete="email" />
                    <flux:error name="email" />
                </flux:field>

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="cursor-pointer text-sm" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium text-green-600 dark:text-green-400">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            @if ($user->isAgent())
                {{-- Agent-specific fields --}}
                <flux:separator class="my-6" />

                <flux:heading size="sm">{{ __('Agent Profile') }}</flux:heading>
                <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('How clients see you when sharing properties') }}</flux:text>

                <flux:field>
                    <flux:label>{{ __('Phone') }}</flux:label>
                    <flux:input wire:model="phone" type="tel" placeholder="+52 33 1234 5678" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>WhatsApp</flux:label>
                    <flux:input wire:model="whatsapp" type="tel" placeholder="+52 33 1234 5678" />
                    <flux:description>{{ __('Number where clients can contact you') }}</flux:description>
                    <flux:error name="whatsapp" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Business name') }}</flux:label>
                    <flux:input wire:model="businessName" placeholder="Inmobiliaria Garcia" />
                    <flux:description>{{ __('Displayed instead of your personal name (optional)') }}</flux:description>
                    <flux:error name="businessName" />
                </flux:field>

                <flux:field>
                    <flux:label>Tagline</flux:label>
                    <flux:input wire:model="tagline" placeholder="Tu hogar ideal te espera" />
                    <flux:description>{{ __('Short phrase shown below your name') }}</flux:description>
                    <flux:error name="tagline" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Brand color') }}</flux:label>
                    <div class="space-y-3">
                        {{-- Color picker and input --}}
                        <div class="flex items-center gap-3">
                            <label class="relative cursor-pointer">
                                <input
                                    type="color"
                                    wire:model.live="brandColor"
                                    class="absolute inset-0 size-10 cursor-pointer opacity-0"
                                />
                                <div
                                    class="flex size-10 items-center justify-center rounded-lg ring-1 ring-zinc-200 transition-shadow hover:ring-2 hover:ring-zinc-400 dark:ring-zinc-700 dark:hover:ring-zinc-500"
                                    style="background-color: {{ $brandColor && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandColor) ? $brandColor : '#3B82F6' }}"
                                >
                                    <svg class="size-5 text-white drop-shadow-sm" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243-1.59-1.59" />
                                    </svg>
                                </div>
                            </label>
                            <flux:input wire:model.live="brandColor" type="text" placeholder="#3B82F6" class="w-28 font-mono text-sm" />
                        </div>

                        {{-- Preset colors --}}
                        <div class="flex flex-wrap gap-2">
                            @php
                                $presetColors = [
                                    '#3B82F6' => 'Azul',
                                    '#10B981' => 'Verde',
                                    '#8B5CF6' => 'Morado',
                                    '#F59E0B' => 'Naranja',
                                    '#EF4444' => 'Rojo',
                                    '#EC4899' => 'Rosa',
                                    '#06B6D4' => 'Cyan',
                                    '#6366F1' => 'Indigo',
                                    '#84CC16' => 'Lima',
                                    '#14B8A6' => 'Teal',
                                ];
                            @endphp
                            @foreach ($presetColors as $color => $name)
                                <button
                                    wire:key="color-{{ $color }}"
                                    type="button"
                                    wire:click="$set('brandColor', '{{ $color }}')"
                                    title="{{ $name }}"
                                    @class([
                                        'size-7 rounded-md transition-all hover:scale-110',
                                        'ring-2 ring-offset-2 ring-zinc-900 dark:ring-white dark:ring-offset-zinc-900' => strtoupper($brandColor) === $color,
                                    ])
                                    style="background-color: {{ $color }}"
                                ></button>
                            @endforeach
                        </div>
                    </div>
                    <flux:description class="mt-2">{{ __('Hex color for accents in PDFs and shared pages') }}</flux:description>
                    <flux:error name="brandColor" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Default WhatsApp message') }}</flux:label>
                    <flux:textarea wire:model="defaultWhatsappMessage" rows="3" placeholder="Hola! Te comparto esta coleccion de propiedades: {collection_name}

{link}" />
                    <flux:description>
                        Placeholders: <code class="text-xs">{collection_name}</code>, <code class="text-xs">{client_name}</code>, <code class="text-xs">{link}</code>, <code class="text-xs">{property_count}</code>, <code class="text-xs">{agent_name}</code>
                    </flux:description>
                    <flux:error name="defaultWhatsappMessage" />
                </flux:field>
            @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>

                <flux:text wire:dirty class="text-sm text-amber-600 dark:text-amber-400">
                    {{ __('Unsaved changes') }}
                </flux:text>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
