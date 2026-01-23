<?php

use App\Livewire\Landing;
use Livewire\Livewire;

test('landing page loads successfully', function () {
    $this->get('/')->assertOk();
});

test('landing page renders the landing component', function () {
    Livewire::test(Landing::class)->assertOk();
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

test('authenticated users see link to their dashboard', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSee($user->homeUrl());
});

test('guests see authentication links', function () {
    $this->get('/')
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});
