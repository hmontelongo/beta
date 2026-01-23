<?php

namespace App\Livewire;

use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.landing')]
class Landing extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    public bool $submitted = false;

    public function submit(): void
    {
        $this->validate();

        // TODO: Store email in waitlist table or send to email service
        // For now, just show success state

        $this->submitted = true;

        Flux::toast(
            heading: 'Te avisamos pronto',
            text: 'Te hemos agregado a la lista de espera.',
            variant: 'success',
        );
    }

    public function render()
    {
        return view('livewire.landing');
    }
}
