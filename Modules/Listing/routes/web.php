<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Listing\Http\Controllers\ClearOutController;
use Modules\Listing\Http\Controllers\ListingController;
use Modules\Listing\Http\Controllers\WantedController;

Route::middleware('web')->group(function () {
    Route::get('/clear-outs/{clearOut}', [ClearOutController::class, 'show'])
        ->name('clear-outs.show');

    Route::get('/wanted', [WantedController::class, 'index'])
        ->name('wanted.index');

    Route::get('/wanted/{wanted}', [WantedController::class, 'show'])
        ->name('wanted.show');
});

Route::middleware('web')->prefix('listings')->name('listings.')->group(function () {
    Route::get('/', [ListingController::class, 'index'])->name('index');
    Route::get('/create', [ListingController::class, 'create'])->name('create');
    Route::get('/search-suggestions', [ListingController::class, 'searchSuggestions'])->name('search-suggestions');
    Route::post('/', [ListingController::class, 'store'])->name('store');
    Route::middleware(['auth', 'throttle:30,1'])->get('/{listing}/contact', [ListingController::class, 'contact'])->name('contact');
    Route::get('/{listing}', [ListingController::class, 'show'])->name('show');
});
