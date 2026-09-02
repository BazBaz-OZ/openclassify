<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Panel\App\Http\Controllers\ClearOutController;
use Modules\Panel\App\Http\Controllers\PanelController;

Route::middleware(['web', 'auth'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [PanelController::class, 'index'])->name('index');
    Route::get('/my-listings', [PanelController::class, 'listings'])->name('listings.index');

    Route::get('/clear-outs', [ClearOutController::class, 'index'])
        ->name('clear-outs.index');

    Route::get('/clear-outs/create', [ClearOutController::class, 'create'])
        ->middleware('verified')
        ->name('clear-outs.create');

    Route::post('/clear-outs', [ClearOutController::class, 'store'])
        ->middleware('verified')
        ->name('clear-outs.store');

    Route::get('/clear-outs/{clearOut}/edit', [ClearOutController::class, 'edit'])
        ->name('clear-outs.edit');

    Route::put('/clear-outs/{clearOut}', [ClearOutController::class, 'update'])
        ->name('clear-outs.update');

    Route::post('/clear-outs/{clearOut}/publish', [ClearOutController::class, 'publish'])
        ->name('clear-outs.publish');

    Route::post('/clear-outs/{clearOut}/complete', [ClearOutController::class, 'complete'])
        ->name('clear-outs.complete');
    Route::get('/create-listing', [PanelController::class, 'create'])
        ->middleware('verified')
        ->name('listings.create');
    Route::get('/my-listings/{listing}/edit', [PanelController::class, 'editListing'])->name('listings.edit');
    Route::put('/my-listings/{listing}', [PanelController::class, 'updateListing'])->name('listings.update');
    Route::post('/my-listings/{listing}/remove', [PanelController::class, 'destroyListing'])->name('listings.destroy');
    Route::post('/my-listings/{listing}/mark-sold', [PanelController::class, 'markListingAsSold'])->name('listings.mark-sold');
    Route::post('/my-listings/{listing}/republish', [PanelController::class, 'republishListing'])->name('listings.republish');
    Route::get('/videos', [PanelController::class, 'videos'])->name('videos.index');
    Route::post('/videos', [PanelController::class, 'storeVideo'])->name('videos.store');
    Route::get('/videos/{video}/edit', [PanelController::class, 'editVideo'])->name('videos.edit');
    Route::put('/videos/{video}', [PanelController::class, 'updateVideo'])->name('videos.update');
    Route::delete('/videos/{video}', [PanelController::class, 'destroyVideo'])->name('videos.destroy');
    Route::get('/my-profile', [PanelController::class, 'profile'])->name('profile.edit');
});
