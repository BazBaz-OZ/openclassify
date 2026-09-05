<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Site\App\Http\Controllers\HomeController;
use Modules\Site\App\Http\Controllers\LanguageController;
use Modules\Site\App\Http\Controllers\PublicMediaController;

Route::get('/storage/{path}', [PublicMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.legacy');

Route::middleware('web')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::view(
        '/membership',
        'site::membership.index'
    )->name('membership');
    Route::get('/lang/{locale}', [LanguageController::class, 'switch'])->name('lang.switch');
    Route::get('/dashboard', fn () => auth()->check()
        ? redirect()->route('panel.listings.index')
        : redirect()->route('login'))
        ->name('dashboard');

    Route::post(
        '/membership/checkout/{plan}',
        function (
            \Illuminate\Http\Request $request,
            string $plan
        ) {
            abort_unless(
                in_array($plan, ['member', 'pro'], true),
                404
            );

            $priceId = config(
                "membership.plans.{$plan}.stripe_price_id"
            );

            abort_if(
                blank($priceId),
                500,
                'Stripe price is not configured.'
            );

            return $request->user()
                ->newSubscription(
                    'default',
                    $priceId
                )
                ->checkout([
                    'success_url' =>
                        route('membership')
                        .'?checkout=success',

                    'cancel_url' =>
                        route('membership')
                        .'?checkout=cancelled',
                ]);
        }
    )
        ->middleware('auth')
        ->name('membership.checkout');
});
