<?php

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // New users default to agent role and are redirected to agents subdomain
    $response->assertSessionHasNoErrors()
        ->assertRedirect('http://'.config('domains.agents').'/properties');

    $this->assertAuthenticated();
});
