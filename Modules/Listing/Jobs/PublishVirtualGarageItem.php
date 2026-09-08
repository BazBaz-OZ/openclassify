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

        /*
         * An unresolved duplicate must never be
         * published automatically.
         *
         * "Keep anyway" removes the active duplicate
         * marker before the item becomes publishable.
         */
        $duplicate =
            data_get(
                $item->ai_data,
                'duplicate'
            );

        if (
            is_array($duplicate)
            && filled(
                $duplicate['item_id']
                ?? null
            )
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
         * Attach exactly one final marketplace image.
         *
         * Image priority:
         *
         * 1. Seller's explicit manual crop.
         * 2. AI-generated spotlight image.
         * 3. Sanitised original garage photo.
         *
         * This keeps retries safe while ensuring a normal
         * multi-item garage photo does not become the
         * marketplace image for every detected item.
         */
        if (
            $listing
                ->getMedia('listing-images')
                ->isEmpty()
        ) {
            $sourceDisk = null;
            $sourceRelativePath = null;
            $sourceFileName = null;

            $manualCropFile =
                $item->ai_data[
                    'manual_crop_file'
                ] ?? null;

            $spotlightFile =
                $item->ai_data[
                    'spotlight_file'
                ] ?? null;

            /*
             * Seller crop always wins.
             * AI spotlight is the normal fallback.
             */
            foreach (
                [
                    $manualCropFile,
                    $spotlightFile,
                ]
                as $candidate
            ) {
                if (
                    ! is_array($candidate)
                    || ! filled(
                        $candidate['disk']
                        ?? null
                    )
                    || ! filled(
                        $candidate['path']
                        ?? null
                    )
                ) {
                    continue;
                }

                $candidateDisk =
                    (string)
                    $candidate['disk'];

                $candidatePath =
                    (string)
                    $candidate['path'];

                if (
                    ! Storage::disk(
                        $candidateDisk
                    )->exists(
                        $candidatePath
                    )
                ) {
                    continue;
                }

                $sourceDisk =
                    $candidateDisk;

                $sourceRelativePath =
                    $candidatePath;

                $sourceFileName =
                    basename(
                        $candidatePath
                    );

                break;
            }

            /*
             * Last resort: the privacy-sanitised
             * original garage photo.
             */
            if (
                $sourceRelativePath === null
            ) {
                $photo = $item->photo;

                if (
                    $photo
                    && filled($photo->disk)
                    && filled($photo->path)
                    && Storage::disk(
                        (string)
                        $photo->disk
                    )->exists(
                        (string)
                        $photo->path
                    )
                ) {
                    $sourceDisk =
                        (string)
                        $photo->disk;

                    $sourceRelativePath =
                        (string)
                        $photo->path;

                    $sourceFileName =
                        $photo->original_name
                        ?: basename(
                            (string)
                            $photo->path
                        );
                }
            }

            if (
                $sourceDisk !== null
                && $sourceRelativePath !== null
                && $sourceFileName !== null
            ) {
                $sourcePath =
                    Storage::disk(
                        $sourceDisk
                    )->path(
                        $sourceRelativePath
                    );

                if (is_file($sourcePath)) {
                    $mediaDisk =
                        (string)
                        config(
                            'filesystems.default',
                            'public'
                        );

                    if (
                        $mediaDisk === 'local'
                    ) {
                        $mediaDisk =
                            'public';
                    }

                    $listing
                        ->attachListingImage(
                            $sourcePath,
                            $sourceFileName,
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
