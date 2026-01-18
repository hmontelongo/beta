<?php

use App\Livewire\Dedup\ReviewCandidates;
use App\Livewire\Listings\Index as ListingsIndex;
use App\Livewire\Listings\Show as ListingsShow;
use App\Livewire\Platforms\Index as PlatformsIndex;
use App\Livewire\Platforms\Show as PlatformsShow;
use App\Livewire\Properties\Index as PropertiesIndex;
use App\Livewire\Properties\Show as PropertiesShow;
use App\Livewire\ScrapeRuns\Show as ScrapeRunsShow;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('profile.edit');
    Route::get('settings/password', Password::class)->name('user-password.edit');
    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    Route::get('platforms', PlatformsIndex::class)->name('platforms.index');
    Route::get('platforms/{platform}', PlatformsShow::class)->name('platforms.show');

    Route::get('listings', ListingsIndex::class)->name('listings.index');
    Route::get('listings/{listing}', ListingsShow::class)->name('listings.show');

    Route::get('properties', PropertiesIndex::class)->name('properties.index');
    Route::get('properties/{property}', PropertiesShow::class)->name('properties.show');

    Route::get('dedup/review', ReviewCandidates::class)->name('dedup.review');

    Route::get('runs/{run}', ScrapeRunsShow::class)->name('runs.show');
});
