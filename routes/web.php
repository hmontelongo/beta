<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Dedup\ReviewCandidates;
use App\Livewire\Admin\Listings\Index as ListingsIndex;
use App\Livewire\Admin\Listings\Show as ListingsShow;
use App\Livewire\Admin\Platforms\Index as PlatformsIndex;
use App\Livewire\Admin\Platforms\Show as PlatformsShow;
use App\Livewire\Admin\Properties\Index as AdminPropertiesIndex;
use App\Livewire\Admin\Properties\Show as PropertiesShow;
use App\Livewire\Admin\Publishers\Index as PublishersIndex;
use App\Livewire\Admin\Publishers\Show as PublishersShow;
use App\Livewire\Admin\ScrapeRuns\Show as ScrapeRunsShow;
use App\Livewire\Agents\Clients\Index as AgentClientsIndex;
use App\Livewire\Agents\Clients\Show as AgentClientsShow;
use App\Livewire\Agents\Collections\Index as AgentCollectionsIndex;
use App\Livewire\Agents\Collections\Show as AgentCollectionsShow;
use App\Livewire\Agents\Properties\Index as AgentPropertiesIndex;
use App\Livewire\Agents\Properties\Show as AgentPropertiesShow;
use App\Livewire\Landing;
use App\Livewire\Public\Collections\Show as PublicCollectionShow;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Public Routes (beta.test)
|--------------------------------------------------------------------------
*/
Route::livewire('/', Landing::class)->name('home');

Route::livewire('c/{collection:share_token}', PublicCollectionShow::class)->name('collections.public.show');

/*
|--------------------------------------------------------------------------
| Admin Routes (admin.beta.test)
|--------------------------------------------------------------------------
*/
Route::domain(config('domains.admin'))->group(function () {
    Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
        Route::livewire('dashboard', Dashboard::class)->name('admin.dashboard');

        Route::livewire('platforms', PlatformsIndex::class)->name('admin.platforms.index');
        Route::livewire('platforms/{platform}', PlatformsShow::class)->name('admin.platforms.show');

        Route::livewire('listings', ListingsIndex::class)->name('admin.listings.index');
        Route::livewire('listings/{listing}', ListingsShow::class)->name('admin.listings.show');

        Route::livewire('properties', AdminPropertiesIndex::class)->name('admin.properties.index');
        Route::livewire('properties/{property}', PropertiesShow::class)->name('admin.properties.show');

        Route::livewire('publishers', PublishersIndex::class)->name('admin.publishers.index');
        Route::livewire('publishers/{publisher}', PublishersShow::class)->name('admin.publishers.show');

        Route::livewire('dedup/review', ReviewCandidates::class)->name('admin.dedup.review');

        Route::livewire('runs/{run}', ScrapeRunsShow::class)->name('admin.runs.show');
    });

    // Settings routes on admin subdomain
    Route::middleware(['auth'])->group(function () {
        Route::redirect('settings', 'settings/profile');

        Route::livewire('settings/profile', Profile::class)->name('admin.profile.edit');
        Route::livewire('settings/password', Password::class)->name('admin.user-password.edit');
        Route::livewire('settings/appearance', Appearance::class)->name('admin.appearance.edit');

        Route::livewire('settings/two-factor', TwoFactor::class)
            ->middleware(
                when(
                    Features::canManageTwoFactorAuthentication()
                        && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                    ['password.confirm'],
                    [],
                ),
            )
            ->name('admin.two-factor.show');
    });
});

/*
|--------------------------------------------------------------------------
| Agent Routes (agents.beta.test) - Placeholder for Phase 4
|--------------------------------------------------------------------------
*/
Route::domain(config('domains.agents'))->group(function () {
    Route::middleware(['auth', 'verified', 'role:agent'])->group(function () {
        // Redirect root to properties search
        Route::redirect('/', '/properties');

        Route::livewire('properties', AgentPropertiesIndex::class)->name('agents.properties.index');
        Route::livewire('properties/{property}', AgentPropertiesShow::class)->name('agents.properties.show');
        Route::livewire('clients', AgentClientsIndex::class)->name('agents.clients.index');
        Route::livewire('clients/{client}', AgentClientsShow::class)->name('agents.clients.show');
        Route::livewire('collections', AgentCollectionsIndex::class)->name('agents.collections.index');
        Route::livewire('collections/{collection}', AgentCollectionsShow::class)->name('agents.collections.show');
    });

    // Settings routes on agents subdomain
    Route::middleware(['auth'])->group(function () {
        Route::redirect('settings', 'settings/profile');

        Route::livewire('settings/profile', Profile::class)->name('agents.profile.edit');
        Route::livewire('settings/password', Password::class)->name('agents.user-password.edit');
        Route::livewire('settings/appearance', Appearance::class)->name('agents.appearance.edit');

        Route::livewire('settings/two-factor', TwoFactor::class)
            ->middleware(
                when(
                    Features::canManageTwoFactorAuthentication()
                        && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                    ['password.confirm'],
                    [],
                ),
            )
            ->name('agents.two-factor.show');
    });
});
