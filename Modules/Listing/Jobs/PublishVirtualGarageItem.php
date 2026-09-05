<?php

declare(strict_types=1);

namespace Modules\Listing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\VirtualGarage;
use Modules\Listing\Models\VirtualGarageItem;
use Throwable;

class PublishVirtualGarageItem implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $itemId
    ) {
    }

    public function handle(): void
    {
        $item = VirtualGarageItem::query()
            ->with([
                'virtualGarage',
                'photo',
                'listing',
            ])
            ->find($this->itemId);

        if (! $item) {
            return;
        }

        if (
            $item->status
                === VirtualGarageItem::STATUS_SKIPPED
        ) {
            return;
        }

        if (
            $item->status
                === VirtualGarageItem::STATUS_PUBLISHED
            && $item->listing_id !== null
        ) {
            $this->activateGarageIfComplete(
                $item->virtualGarage
            );

            return;
        }

        $garage = $item->virtualGarage;

        if (! $garage) {
            return;
        }

        /*
         * First create and link the listing transactionally.
         *
         * The item keeps STATUS_DRAFT until its image has
         * also been attached successfully.
         *
         * This means a retry can safely resume an item that
         * already has a listing_id without creating another
         * marketplace listing.
         */
        if ($item->listing_id === null) {
            DB::transaction(
                function () use (
                    $item,
                    $garage
                ): void {
                    $lockedItem =
                        VirtualGarageItem::query()
                            ->whereKey(
                                $item->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    if (
                        $lockedItem->listing_id
                            !== null
                    ) {
                        return;
                    }

                    if (
                        $lockedItem->status
                            === VirtualGarageItem::STATUS_SKIPPED
                    ) {
                        return;
                    }

                    if (blank($lockedItem->title)) {
                        throw new \RuntimeException(
                            'Garage item has no title.'
                        );
                    }

                    if (! $lockedItem->category_id) {
                        throw new \RuntimeException(
                            'Garage item has no category.'
                        );
                    }

                    if ($lockedItem->price === null) {
                        throw new \RuntimeException(
                            'Garage item has no price.'
                        );
                    }

                    $description = trim(
                        (string)
                        $lockedItem->description
                    );

                    if (
                        filled(
                            $lockedItem->condition
                        )
                    ) {
                        $conditionLine =
                            'Condition: '
                            .trim(
                                (string)
                                $lockedItem->condition
                            );

                        $description =
                            $description !== ''
                                ? $conditionLine
                                    ."\n\n"
                                    .$description
                                : $conditionLine;
                    }

                    $listing =
                        Listing::createFromFrontend(
                            [
                                'title' =>
                                    trim(
                                        $lockedItem->title
                                    ),

                                'description' =>
                                    $description,

                                'price' =>
                                    (float)
                                    $lockedItem->price,

                                'quantity_total' => 1,

                                'quantity_available' => 1,

                                'currency' =>
                                    $lockedItem->currency
                                        ?: 'AUD',

                                'category_id' =>
                                    $lockedItem
                                        ->category_id,

                                'contact_email' =>
                                    (string)
                                    $garage->user
                                        ?->email,

                                'contact_phone' => null,

                                'country' =>
                                    $garage->country,

                                'city' =>
                                    $garage->city,

                                'custom_fields' => [],
                            ],
                            $garage->user_id
                        );

                    $sortOrder =
                        $garage
                            ->listings()
                            ->count();

                    $garage
                        ->listings()
                        ->syncWithoutDetaching([
                            $listing->getKey() => [
                                'sort_order' =>
                                    $sortOrder,
                            ],
                        ]);

                    $lockedItem->update([
                        'listing_id' =>
                            $listing->getKey(),
                    ]);
                }
            );

            $item->refresh();
        }

        if ($item->listing_id === null) {
            return;
        }

        $listing = Listing::query()
            ->find($item->listing_id);

        if (! $listing) {
            throw new \RuntimeException(
                'Created listing could not be found.'
            );
        }

        /*
         * Attach the source photo only if the listing does
         * not already have one.
         *
         * This is important for retry safety.
         */
        if (
            $listing
                ->getMedia('listing-images')
                ->isEmpty()
        ) {
            $photo = $item->photo;

            if ($photo) {
                $sourcePath =
                    Storage::disk(
                        $photo->disk
                    )->path(
                        $photo->path
                    );

                if (is_file($sourcePath)) {
                    $mediaDisk = (string) config(
                        'filesystems.default',
                        'public'
                    );

                    if ($mediaDisk === 'local') {
                        $mediaDisk = 'public';
                    }

                    $listing->attachListingImage(
                        $sourcePath,
                        $photo->original_name
                            ?: basename(
                                $photo->path
                            ),
                        $mediaDisk
                    );
                }
            }
        }

        $item->update([
            'status' =>
                VirtualGarageItem::STATUS_PUBLISHED,
        ]);

        $this->activateGarageIfComplete(
            $garage
        );
    }

    private function activateGarageIfComplete(
        VirtualGarage $garage
    ): void {
        $remaining = $garage
            ->items()
            ->where(
                'status',
                VirtualGarageItem::STATUS_DRAFT
            )
            ->count();

        if ($remaining > 0) {
            return;
        }

        if (! $garage->listings()->exists()) {
            return;
        }

        $garage->update([
            'status' =>
                VirtualGarage::STATUS_ACTIVE,

            'starts_at' =>
                $garage->starts_at
                    ?? now(),
        ]);
    }

    public function failed(
        ?Throwable $exception
    ): void {
        if ($exception) {
            report($exception);
        }
    }
}
