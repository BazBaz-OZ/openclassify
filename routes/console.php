<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Listing\Models\Listing;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('listings:expire', function () {
    $expiredCount = 0;

    Listing::query()
        ->where('status', 'active')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->orderBy('id')
        ->chunkById(
            100,
            function ($listings) use (&$expiredCount): void {
                foreach ($listings as $listing) {
                    /*
                     * Re-check through the model so this stays
                     * safe if a listing changes while the job
                     * is processing a chunk.
                     */
                    if (
                        $listing->statusValue() !== 'active'
                        || ! $listing->expires_at
                        || $listing->expires_at->isFuture()
                    ) {
                        continue;
                    }

                    $listing->forceFill([
                        'status' => 'expired',
                    ])->save();

                    $expiredCount++;
                }
            }
        );

    $this->info(
        "Expired {$expiredCount} listing(s)."
    );
})->purpose(
    'Expire active marketplace listings whose expiry time has passed'
);

Schedule::command('listings:expire')
    ->hourly()
    ->withoutOverlapping();

if (config('demo.enabled')) {
    Schedule::command('demo:cleanup')->hourly();
}
