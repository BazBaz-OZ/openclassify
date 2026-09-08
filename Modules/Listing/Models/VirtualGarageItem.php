<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VirtualGarageItem extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'virtual_garage_id',
        'virtual_garage_photo_id',
        'category_id',
        'listing_id',
        'title',
        'description',
        'suggested_price',
        'price',
        'currency',
        'condition',
        'confidence',
        'bounding_box',
        'ai_data',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'suggested_price' => 'decimal:2',
        'price' => 'decimal:2',
        'confidence' => 'float',
        'bounding_box' => 'array',
        'ai_data' => 'array',
        'sort_order' => 'integer',
    ];

    public function virtualGarage()
    {
        return $this->belongsTo(VirtualGarage::class);
    }

    public function photo()
    {
        return $this->belongsTo(
            VirtualGaragePhoto::class,
            'virtual_garage_photo_id'
        );
    }

    public function category()
    {
        return $this->belongsTo(
            \Modules\Category\Models\Category::class
        );
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function hasManualCrop(): bool
    {
        $crop = $this->ai_data['manual_crop_file'] ?? null;

        return is_array($crop)
            && filled($crop['disk'] ?? null)
            && filled($crop['path'] ?? null);
    }

    public function spotlightImageUrl(): ?string
    {
        $spotlight =
            $this->ai_data[
                'spotlight_file'
            ] ?? null;

        if (
            is_array($spotlight)
            && filled(
                $spotlight['disk']
                ?? null
            )
            && filled(
                $spotlight['path']
                ?? null
            )
        ) {
            return Storage::disk(
                (string)
                $spotlight['disk']
            )->url(
                (string)
                $spotlight['path']
            );
        }

        return null;
    }

    public function previewImageUrl(): ?string
    {
        /*
         * A seller's explicit manual crop always
         * wins over AI-generated presentation.
         */
        $crop =
            $this->ai_data[
                'manual_crop_file'
            ] ?? null;

        if (
            is_array($crop)
            && filled(
                $crop['disk']
                ?? null
            )
            && filled(
                $crop['path']
                ?? null
            )
        ) {
            return Storage::disk(
                (string) $crop['disk']
            )->url(
                (string) $crop['path']
            );
        }

        $spotlight =
            $this->spotlightImageUrl();

        if ($spotlight) {
            return $spotlight;
        }

        return $this->photo?->url();
    }

    public function initialPhotoCrop(): array
    {
        $saved = $this->ai_data['manual_crop'] ?? null;

        if (
            is_array($saved)
            && isset($saved['center_x'], $saved['center_y'], $saved['zoom'])
            && is_numeric($saved['center_x'])
            && is_numeric($saved['center_y'])
            && is_numeric($saved['zoom'])
        ) {
            return [
                'center_x' => round(
                    max(0, min(1, (float) $saved['center_x'])),
                    5
                ),
                'center_y' => round(
                    max(0, min(1, (float) $saved['center_y'])),
                    5
                ),
                'zoom' => round(
                    max(1, min(4, (float) $saved['zoom'])),
                    3
                ),
            ];
        }

        $box = $this->bounding_box;

        if (
            is_array($box)
            && isset($box['x'], $box['y'], $box['width'], $box['height'])
            && is_numeric($box['x'])
            && is_numeric($box['y'])
            && is_numeric($box['width'])
            && is_numeric($box['height'])
        ) {
            $width = max(0.02, (float) $box['width']);
            $height = max(0.02, (float) $box['height']);
            $largestSide = max($width, $height);

            return [
                'center_x' => round(
                    max(0, min(1, (float) $box['x'] + ($width / 2))),
                    5
                ),
                'center_y' => round(
                    max(0, min(1, (float) $box['y'] + ($height / 2))),
                    5
                ),
                'zoom' => round(
                    max(1, min(4, 0.65 / $largestSide)),
                    3
                ),
            ];
        }

        return [
            'center_x' => 0.5,
            'center_y' => 0.5,
            'zoom' => 1.0,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (VirtualGarageItem $item): void {
            $crop = $item->ai_data['manual_crop_file'] ?? null;

            if (
                is_array($crop)
                && filled($crop['disk'] ?? null)
                && filled($crop['path'] ?? null)
            ) {
                Storage::disk(
                    (string) $crop['disk']
                )->delete(
                    (string) $crop['path']
                );
            }
        });
    }
}
