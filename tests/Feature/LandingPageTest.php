<?php

use App\Livewire\Landing;
use Livewire\Livewire;

test('landing page loads successfully', function () {
    $this->get('/')
        ->assertStatus(200)
        ->assertSeeLivewire(Landing::class);
});

test('landing page displays main headline', function () {
    $this->get('/')
        ->assertSee('De WhatsApp a propuesta');
});

test('landing page displays key features', function () {
    $this->get('/')
        ->assertSee('Una sola busqueda')
        ->assertSee('Colecciones en un tap')
        ->assertSee('Comparte como profesional');
});

test('landing page displays waitlist form', function () {
    $this->get('/')
        ->assertSee('Tu correo profesional')
        ->assertSee('Solicitar acceso');
});

test('waitlist form validates email', function () {
    Livewire::test(Landing::class)
        ->set('email', 'invalid-email')
        ->call('submit')
        ->assertHasErrors(['email']);
});

test('waitlist form accepts valid email', function () {
    Livewire::test(Landing::class)
        ->set('email', 'agent@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);
});

test('authenticated users see dashboard link', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSee('Ir al panel');
});

test('guests see login and register links', function () {
    $this->get('/')
        ->assertSee('Iniciar sesion')
        ->assertSee('Registrarse');
});
