<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your profile information')">
        @if ($user->isAgent())
            {{-- Avatar Section for Agents --}}
            <div class="my-6 flex items-center gap-6">
                <div class="relative shrink-0">
                    @if ($avatar && $avatar->isPreviewable())
                        <img
                            src="{{ $avatar->temporaryUrl() }}"
                            alt="Preview"
                            class="size-20 rounded-full object-cover ring-2 ring-zinc-200 dark:ring-zinc-700"
                        />
                    @elseif ($user->avatar_url)
                        <img
                            src="{{ $user->avatar_url }}"
                            alt="{{ $user->name }}"
                            class="size-20 rounded-full object-cover ring-2 ring-zinc-200 dark:ring-zinc-700"
                        />
                    @else
                        <flux:avatar :name="$user->name" size="xl" />
                    @endif
                </div>

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
                        <div class="flex gap-2">
                            <flux:button as="label" size="sm" variant="subtle">
                                <input type="file" wire:model="avatar" accept="image/*" class="hidden" />
                                {{ $user->avatar_url ? __('Change photo') : __('Upload photo') }}
                            </flux:button>
                            @if ($user->avatar_url)
                                <flux:button
                                    wire:click="deleteAvatar"
                                    wire:confirm="{{ __('Are you sure you want to remove your profile photo?') }}"
                                    size="sm"
                                    variant="ghost"
                                    class="text-red-600"
                                >
                                    {{ __('Remove') }}
                                </flux:button>
                            @endif
                        </div>
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
                    <div class="flex items-center gap-3">
                        <flux:input wire:model="brandColor" type="text" placeholder="#3B82F6" class="w-32" />
                        @if ($brandColor && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandColor))
                            <div class="size-8 rounded-lg ring-1 ring-zinc-200 dark:ring-zinc-700" style="background-color: {{ $brandColor }}"></div>
                        @endif
                    </div>
                    <flux:description>{{ __('Hex color for accents in PDFs and shared pages') }}</flux:description>
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
