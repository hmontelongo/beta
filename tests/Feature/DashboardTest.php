<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get('http://'.config('domains.admin').'/dashboard')
        ->assertRedirect('/login');
});

test('authenticated admin users can visit the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('http://'.config('domains.admin').'/dashboard')
        ->assertStatus(200);
});

test('agent users are redirected from admin dashboard', function () {
    $this->actingAs(User::factory()->agent()->create());

    $this->get('http://'.config('domains.admin').'/dashboard')
        ->assertRedirect();
});
