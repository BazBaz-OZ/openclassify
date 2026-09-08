<?php

declare(strict_types=1);

namespace Modules\Listing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Listing\Models\VirtualGarageItem;
use Modules\Listing\Support\VirtualGarageItemSpotlighter;

class GenerateVirtualGaragePhotoSpotlights implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public int $photoId
    ) {
    }

    public function handle(
        VirtualGarageItemSpotlighter $spotlighter
    ): void {
        $items =
            VirtualGarageItem::query()
                ->where(
                    'virtual_garage_photo_id',
                    $this->photoId
                )
                ->whereNotNull(
                    'bounding_box'
                )
                ->with('photo')
                ->orderBy('sort_order')
                ->get();

        foreach ($items as $item) {
            /*
             * A seller-selected manual image always wins.
             */
            if ($item->hasManualCrop()) {
                continue;
            }

            $spotlighter->generate($item);
        }
    }
}
