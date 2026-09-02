<?php

declare(strict_types=1);

namespace Modules\Offer\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Listing\Models\Listing;

class BundleOfferItem extends Model
{
    protected $fillable = [
        'bundle_offer_id',
        'listing_id',
        'quantity',
        'listed_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'listed_price' => 'decimal:2',
    ];

    public function bundleOffer()
    {
        return $this->belongsTo(BundleOffer::class);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
