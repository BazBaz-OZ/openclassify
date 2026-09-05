<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Panel\App\Http\Controllers\ClearOutController;
use Modules\Panel\App\Http\Controllers\PanelController;
use Modules\Panel\App\Http\Controllers\WantedController;
use Modules\Panel\App\Http\Controllers\VirtualGarageController;

Route::middleware(['web', 'auth'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [PanelController::class, 'index'])->name('index');
    Route::get('/my-listings', [PanelController::class, 'listings'])->name('listings.index');

    Route::get('/wanted', [WantedController::class, 'index'])
        ->name('wanted.index');

    Route::get('/wanted/create', [WantedController::class, 'create'])
        ->middleware('verified')
        ->name('wanted.create');

    Route::post('/wanted', [WantedController::class, 'store'])
        ->middleware('verified')
        ->name('wanted.store');

    Route::get('/wanted/{wanted}/edit', [WantedController::class, 'edit'])
        ->name('wanted.edit');

    Route::put('/wanted/{wanted}', [WantedController::class, 'update'])
        ->name('wanted.update');

    Route::post('/wanted/{wanted}/fulfill', [WantedController::class, 'fulfill'])
        ->name('wanted.fulfill');

    Route::post('/wanted/{wanted}/cancel', [WantedController::class, 'cancel'])
        ->name('wanted.cancel');

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

    Route::get('/virtual-garages', [VirtualGarageController::class, 'index'])
        ->name('virtual-garages.index');

    Route::get('/virtual-garages/create', [VirtualGarageController::class, 'create'])
        ->middleware('verified')
        ->name('virtual-garages.create');

    Route::post('/virtual-garages', [VirtualGarageController::class, 'store'])
        ->middleware('verified')
        ->name('virtual-garages.store');

    Route::get('/virtual-garages/{virtualGarage}/edit', [VirtualGarageController::class, 'edit'])
        ->name('virtual-garages.edit');

    Route::put('/virtual-garages/{virtualGarage}', [VirtualGarageController::class, 'update'])
        ->name('virtual-garages.update');

    Route::post('/virtual-garages/{virtualGarage}/photos', [VirtualGarageController::class, 'uploadPhotos'])
        ->name('virtual-garages.photos.store');

    Route::post('/virtual-garages/{virtualGarage}/photos/{photo}/analyze', [VirtualGarageController::class, 'analyzePhoto'])
        ->name('virtual-garages.photos.analyze');

    Route::put('/virtual-garages/{virtualGarage}/items/{item}', [VirtualGarageController::class, 'updateItem'])
        ->name('virtual-garages.items.update');

    Route::post('/virtual-garages/{virtualGarage}/items/{item}/skip', [VirtualGarageController::class, 'skipItem'])
        ->name('virtual-garages.items.skip');

    Route::delete('/virtual-garages/{virtualGarage}/photos/{photo}', [VirtualGarageController::class, 'deletePhoto'])
        ->name('virtual-garages.photos.destroy');

    Route::post('/virtual-garages/{virtualGarage}/complete', [VirtualGarageController::class, 'complete'])
        ->name('virtual-garages.complete');

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
