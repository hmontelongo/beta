@props([
    'images' => [],
    'showThumbnails' => true,
    'maxThumbnails' => null,
    'aspectRatio' => 'aspect-[4/3]',
    'linkToOriginal' => true,
])

@if (count($images) > 0)
    <div
        x-data="{
            currentIndex: 0,
            images: @js($images),
            next() { this.currentIndex = (this.currentIndex + 1) % this.images.length },
            prev() { this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length },
            goTo(index) { this.currentIndex = index }
        }"
        {{ $attributes->class(['space-y-3']) }}
    >
        {{-- Main Image --}}
        <div class="relative {{ $aspectRatio }} overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
            <template x-for="(image, index) in images" :key="index">
                @if ($linkToOriginal)
                    <a
                        :href="image"
                        target="_blank"
                        x-show="currentIndex === index"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        class="absolute inset-0"
                    >
                        <img :src="image" alt="" class="h-full w-full object-cover" />
                    </a>
                @else
                    <img
                        x-show="currentIndex === index"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        :src="image"
                        alt=""
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                @endif
            </template>

            {{-- Navigation Arrows --}}
            <button
                @click.prevent="prev"
                class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 transition"
                x-show="images.length > 1"
            >
                <flux:icon name="chevron-left" class="size-5" />
            </button>
            <button
                @click.prevent="next"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 transition"
                x-show="images.length > 1"
            >
                <flux:icon name="chevron-right" class="size-5" />
            </button>

            {{-- Image Counter --}}
            <div class="absolute bottom-2 right-2 rounded-full bg-black/50 px-3 py-1 text-sm text-white">
                <span x-text="currentIndex + 1"></span> / <span x-text="images.length"></span>
            </div>
        </div>

        {{-- Thumbnails --}}
        @if ($showThumbnails)
            <div class="flex gap-2 overflow-x-auto pb-2" x-show="images.length > 1">
                <template x-for="(image, index) in {{ $maxThumbnails ? "images.slice(0, {$maxThumbnails})" : 'images' }}" :key="'thumb-' + index">
                    <button
                        @click="goTo(index)"
                        class="shrink-0 size-16 rounded-lg overflow-hidden ring-2 transition"
                        :class="currentIndex === index ? 'ring-blue-500' : 'ring-transparent hover:ring-zinc-300'"
                    >
                        <img :src="image" alt="" class="h-full w-full object-cover" />
                    </button>
                </template>
                @if ($maxThumbnails)
                    <template x-if="images.length > {{ $maxThumbnails }}">
                        <div class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-sm text-zinc-500 dark:bg-zinc-800">
                            +<span x-text="images.length - {{ $maxThumbnails }}"></span> {{ __('more') }}
                        </div>
                    </template>
                @endif
            </div>
        @endif
    </div>
@else
    {{-- Empty State --}}
    <div class="{{ $aspectRatio }} flex items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
        <flux:icon name="photo" class="size-12 text-zinc-300 dark:text-zinc-600" />
    </div>
@endif
