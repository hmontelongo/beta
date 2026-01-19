<?php

use App\Livewire\Dedup\ReviewCandidates;
use App\Livewire\Listings\Index as ListingsIndex;
use App\Livewire\Listings\Show as ListingsShow;
use App\Livewire\Platforms\Index as PlatformsIndex;
use App\Livewire\Platforms\Show as PlatformsShow;
use App\Livewire\Properties\Index as PropertiesIndex;
use App\Livewire\Properties\Show as PropertiesShow;
use App\Livewire\Publishers\Index as PublishersIndex;
use App\Livewire\Publishers\Show as PublishersShow;
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

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
    Route::livewire('settings/password', Password::class)->name('user-password.edit');
    Route::livewire('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::livewire('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    Route::livewire('platforms', PlatformsIndex::class)->name('platforms.index');
    Route::livewire('platforms/{platform}', PlatformsShow::class)->name('platforms.show');

    Route::livewire('listings', ListingsIndex::class)->name('listings.index');
    Route::livewire('listings/{listing}', ListingsShow::class)->name('listings.show');

    Route::livewire('properties', PropertiesIndex::class)->name('properties.index');
    Route::livewire('properties/{property}', PropertiesShow::class)->name('properties.show');

    Route::livewire('publishers', PublishersIndex::class)->name('publishers.index');
    Route::livewire('publishers/{publisher}', PublishersShow::class)->name('publishers.show');

    Route::livewire('dedup/review', ReviewCandidates::class)->name('dedup.review');

    Route::livewire('runs/{run}', ScrapeRunsShow::class)->name('runs.show');
});
