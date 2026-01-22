<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Dedup\ReviewCandidates;
use App\Livewire\Admin\Listings\Index as ListingsIndex;
use App\Livewire\Admin\Listings\Show as ListingsShow;
use App\Livewire\Admin\Platforms\Index as PlatformsIndex;
use App\Livewire\Admin\Platforms\Show as PlatformsShow;
use App\Livewire\Admin\Properties\Index as PropertiesIndex;
use App\Livewire\Admin\Properties\Show as PropertiesShow;
use App\Livewire\Admin\Publishers\Index as PublishersIndex;
use App\Livewire\Admin\Publishers\Show as PublishersShow;
use App\Livewire\Admin\ScrapeRuns\Show as ScrapeRunsShow;
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
Route::get('/', function () {
    return view('welcome');
})->name('home');

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

        Route::livewire('properties', PropertiesIndex::class)->name('admin.properties.index');
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

        // Placeholder - will be implemented in Phase 4
        Route::get('/properties', function () {
            return 'Agent properties search - Coming in Phase 4';
        })->name('agents.properties.index');
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
