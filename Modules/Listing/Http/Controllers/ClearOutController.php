<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Listing\Models\ClearOut;

class ClearOutController extends Controller
{
    public function show(ClearOut $clearOut): View
    {
        abort_unless(
            in_array($clearOut->status, [
                ClearOut::STATUS_ACTIVE,
                ClearOut::STATUS_COMPLETED,
            ], true),
            404
        );

        $clearOut->load([
            'user:id,name',
            'listings' => fn ($query) => $query
                ->withListingCardRelations()
                ->whereIn('status', ['active', 'sold'])
                ->orderByRaw("CASE WHEN status = 'sold' THEN 1 ELSE 0 END")
                ->latest('id'),
        ]);

        $listings = $clearOut->getRelation('listings');

        $availableCount = $listings
            ->filter(fn ($listing) =>
                $listing->statusValue() === 'active'
                && (int) $listing->quantity_available > 0
            )
            ->count();

        $soldCount = $listings
            ->filter(fn ($listing) => $listing->statusValue() === 'sold')
            ->count();

        $favoriteListingIds = auth()->check()
            ? auth()->user()->favoriteListingIds()
            : [];

        return view('listing::clear-outs.show', [
            'clearOut' => $clearOut,
            'listings' => $listings,
            'availableCount' => $availableCount,
            'soldCount' => $soldCount,
            'favoriteListingIds' => $favoriteListingIds,
        ]);
    }
}
