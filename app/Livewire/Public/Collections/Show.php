<?php

namespace App\Livewire\Public\Collections;

use App\Models\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Layout('components.layouts.public')]
class Show extends Component
{
    public Collection $collection;

    public function mount(Collection $collection): void
    {
        if (! $collection->isAccessible()) {
            throw new NotFoundHttpException('Collection not found or not accessible.');
        }

        $this->collection = $collection->load(['properties.listings', 'user']);
    }

    public function render(): View
    {
        return view('livewire.public.collections.show')
            ->title($this->collection->name);
    }
}
