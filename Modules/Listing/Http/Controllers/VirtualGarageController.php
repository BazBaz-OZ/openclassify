<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Listing\Models\VirtualGarage;

class VirtualGarageController extends Controller
{
    public function index(): View
    {
        $garages = VirtualGarage::query()
            ->where('status', VirtualGarage::STATUS_ACTIVE)
            ->with('user:id,name')
            ->withCount([
                'listings',
                'listings as available_listings_count' => fn ($query) => $query
                    ->where('listings.status', 'active')
                    ->where('listings.quantity_available', '>', 0),
                'listings as sold_listings_count' => fn ($query) => $query
                    ->where('listings.status', 'sold'),
            ])
            ->latest('starts_at')
            ->latest('id')
            ->paginate(18);

        return view('listing::virtual-garages.index', [
            'garages' => $garages,
        ]);
    }

    public function show(VirtualGarage $virtualGarage): View
    {
        abort_unless(
            in_array($virtualGarage->status, [
                VirtualGarage::STATUS_ACTIVE,
                VirtualGarage::STATUS_COMPLETED,
            ], true),
            404
        );

        $virtualGarage->load([
            'user:id,name',
            'listings' => fn ($query) => $query
                ->withListingCardRelations()
                ->whereIn('listings.status', ['active', 'sold'])
                ->orderByRaw(
                    "CASE WHEN listings.status = 'sold' THEN 1 ELSE 0 END"
                )
                ->orderBy('virtual_garage_listing.sort_order')
                ->orderBy('listings.id'),
        ]);

        $listings = $virtualGarage->getRelation('listings');

        $availableListings = $listings
            ->filter(fn ($listing) =>
                $listing->statusValue() === 'active'
                && (int) $listing->quantity_available > 0
            );

        $availableCount = $availableListings->count();

        $soldCount = $listings
            ->filter(fn ($listing) =>
                $listing->statusValue() === 'sold'
            )
            ->count();

        $remainingListedTotal = $availableListings
            ->sum(fn ($listing) =>
                (float) $listing->price
                * max(1, (int) $listing->quantity_available)
            );

        $favoriteListingIds = auth()->check()
            ? auth()->user()->favoriteListingIds()
            : [];

        return view('listing::virtual-garages.show', [
            'garage' => $virtualGarage,
            'listings' => $listings,
            'availableCount' => $availableCount,
            'soldCount' => $soldCount,
            'remainingListedTotal' => $remainingListedTotal,
            'favoriteListingIds' => $favoriteListingIds,
        ]);
    }
}
