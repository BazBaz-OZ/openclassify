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

            $user = $request->user();
            $subscription = $user->subscription('default');

            /*
             * Existing paid customer:
             * change the price on the existing subscription instead of
             * creating another Stripe subscription.
             */
            if ($subscription && $subscription->valid()) {
                if ($subscription->hasPrice($priceId)) {
                    return redirect()->route(
                        'membership',
                        [
                            'checkout' => 'current',
                            'plan' => $plan,
                        ]
                    );
                }

                $subscription->swap($priceId);

                return redirect()->route(
                    'membership',
                    [
                        'checkout' => 'updated',
                        'plan' => $plan,
                    ]
                );
            }

            /*
             * Free customer:
             * start a new Stripe Checkout subscription.
             */
            return $user
                ->newSubscription(
                    'default',
                    $priceId
                )
                ->checkout([
                    'success_url' =>
                        route(
                            'membership',
                            [
                                'checkout' => 'success',
                                'plan' => $plan,
                            ]
                        ),

                    'cancel_url' =>
                        route(
                            'membership',
                            [
                                'checkout' => 'cancelled',
                                'plan' => $plan,
                            ]
                        ),
                ]);
        }
    )
        ->middleware('auth')
        ->name('membership.checkout');
});
