<?php

namespace App\Livewire\Concerns;

use Flux\Flux;

trait ShowsWhatsAppTip
{
    /**
     * Show a tip toast if the agent doesn't have WhatsApp configured.
     */
    protected function showWhatsAppTipIfNeeded(): void
    {
        if (! auth()->user()->whatsapp) {
            Flux::toast(
                heading: 'Tip',
                text: 'Agrega tu WhatsApp en tu perfil para que los clientes te contacten directamente.',
                variant: 'warning',
            );
        }
    }
}
