<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Model;

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
}
