<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class FavoriteDirectory
{
    public static function listingFavoriteCounts(array $listingIds): array
    {
        $unique = array_values(array_unique(array_map(static fn (int $id): int => $id, $listingIds)));

        if ($unique === []) {
            return [];
        }

        return DB::table('favorite_listings')
            ->whereIn('listing_id', $unique)
            ->selectRaw('listing_id, COUNT(*) as aggregate')
            ->groupBy('listing_id')
            ->pluck('aggregate', 'listing_id')
            ->mapWithKeys(static fn (mixed $count, mixed $listingId): array => [(int) $listingId => (int) $count])
            ->all();
    }

    public static function savedListingCountForUser(int $userId): int
    {
        return (int) DB::table('favorite_listings')->where('user_id', $userId)->count();
    }

    public static function savedSellerCountForUser(int $userId): int
    {
        return (int) DB::table('favorite_sellers')->where('user_id', $userId)->count();
    }
}
