<?php

use App\Models\User;

describe('admin subdomain access', function () {
    it('allows admin users to access admin routes', function () {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('http://'.config('domains.admin').'/dashboard')
            ->assertOk();
    });

    it('redirects agent users from admin routes to agents subdomain', function () {
        $this->actingAs(User::factory()->agent()->create());

        $this->get('http://'.config('domains.admin').'/dashboard')
            ->assertRedirect('http://'.config('domains.agents').'/properties');
    });

    it('redirects guests from admin routes to login', function () {
        $this->get('http://'.config('domains.admin').'/dashboard')
            ->assertRedirect('/login');
    });

    it('blocks agent users from all protected admin routes', function () {
        $this->actingAs(User::factory()->agent()->create());

        $adminRoutes = [
            '/platforms',
            '/listings',
            '/properties',
            '/publishers',
            '/dedup/review',
        ];

        foreach ($adminRoutes as $route) {
            $this->get('http://'.config('domains.admin').$route)
                ->assertRedirect('http://'.config('domains.agents').'/properties');
        }
    });
});

describe('agents subdomain access', function () {
    it('allows agent users to access agent routes', function () {
        $this->actingAs(User::factory()->agent()->create());

        $this->get('http://'.config('domains.agents').'/properties')
            ->assertOk();
    });

    it('redirects admin users from agent routes to admin subdomain', function () {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('http://'.config('domains.agents').'/properties')
            ->assertRedirect('http://'.config('domains.admin').'/platforms');
    });

    it('redirects guests from agent routes to login', function () {
        $this->get('http://'.config('domains.agents').'/properties')
            ->assertRedirect('/login');
    });
});

describe('settings routes access', function () {
    it('allows admin users to access admin settings', function () {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('http://'.config('domains.admin').'/settings/profile')
            ->assertOk();
    });

    it('allows agent users to access agent settings', function () {
        $this->actingAs(User::factory()->agent()->create());

        $this->get('http://'.config('domains.agents').'/settings/profile')
            ->assertOk();
    });

    it('redirects guests from settings to login', function () {
        $this->get('http://'.config('domains.admin').'/settings/profile')
            ->assertRedirect('/login');

        $this->get('http://'.config('domains.agents').'/settings/profile')
            ->assertRedirect('/login');
    });
});

describe('cross-subdomain session', function () {
    it('maintains authentication across subdomains', function () {
        $admin = User::factory()->admin()->create();

        // Login and verify session works on admin subdomain
        $this->actingAs($admin);

        $this->get('http://'.config('domains.admin').'/dashboard')
            ->assertOk();

        // Settings should also work (no role middleware, just auth)
        $this->get('http://'.config('domains.admin').'/settings/profile')
            ->assertOk();
    });
});
