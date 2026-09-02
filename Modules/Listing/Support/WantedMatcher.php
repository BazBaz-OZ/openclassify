<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\WantedPost;
use Modules\Notification\Models\UserNotification;
use Throwable;

class WantedMatcher
{
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by',
        'for', 'from', 'has', 'have', 'i', 'in', 'is', 'it',
        'looking', 'me', 'my', 'of', 'on', 'or', 'the', 'this',
        'to', 'want', 'wanted', 'with',
    ];

    public static function wantedForListing(
        Listing $listing,
        int $limit = 20
    ): Collection {
        if (
            (string) $listing->statusValue() !== 'active'
            || (int) $listing->quantity_available <= 0
        ) {
            return collect();
        }

        $query = WantedPost::query()
            ->active()
            ->where('user_id', '!=', $listing->user_id);

        if ($listing->category_id) {
            $compatibleCategoryIds = self::compatibleCategoryIds(
                (int) $listing->category_id
            );

            $query->where(function ($query) use ($compatibleCategoryIds): void {
                $query
                    ->whereNull('category_id')
                    ->orWhereIn('category_id', $compatibleCategoryIds);
            });
        }

        if (filled($listing->country)) {
            $country = trim((string) $listing->country);

            $query->where(function ($query) use ($country): void {
                $query
                    ->whereNull('country')
                    ->orWhere('country', '')
                    ->orWhereRaw(
                        'LOWER(country) = LOWER(?)',
                        [$country]
                    );
            });
        }

        if ($listing->price !== null) {
            $price = (float) $listing->price;

            $query->where(function ($query) use ($price): void {
                $query
                    ->whereNull('max_budget')
                    ->orWhere('max_budget', '>=', $price);
            });
        }

        return $query
            ->latest('published_at')
            ->limit(250)
            ->get()
            ->map(function (WantedPost $wanted) use ($listing): array {
                return [
                    'wanted' => $wanted,
                    'score' => self::score($wanted, $listing),
                ];
            })
            ->filter(
                fn (array $match): bool => $match['score'] > 0
            )
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('wanted')
            ->values();
    }

    public static function listingsForWanted(
        WantedPost $wanted,
        int $limit = 12
    ): Collection {
        if ($wanted->status !== WantedPost::STATUS_ACTIVE) {
            return collect();
        }

        $query = Listing::query()
            ->active()
            ->where('quantity_available', '>', 0)
            ->where('user_id', '!=', $wanted->user_id)
            ->withListingCardRelations();

        if ($wanted->category_id) {
            $query->whereIn(
                'category_id',
                self::compatibleCategoryIds(
                    (int) $wanted->category_id
                )
            );
        }

        if (filled($wanted->country)) {
            $country = trim((string) $wanted->country);

            $query->whereRaw(
                'LOWER(country) = LOWER(?)',
                [$country]
            );
        }

        if ($wanted->max_budget !== null) {
            $query
                ->whereNotNull('price')
                ->where(
                    'price',
                    '<=',
                    (float) $wanted->max_budget
                );
        }

        return $query
            ->latest('id')
            ->limit(250)
            ->get()
            ->map(function (Listing $listing) use ($wanted): array {
                return [
                    'listing' => $listing,
                    'score' => self::score($wanted, $listing),
                ];
            })
            ->filter(
                fn (array $match): bool => $match['score'] > 0
            )
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('listing')
            ->values();
    }

    public static function notifyMatchesForListing(
        Listing $listing
    ): void {
        try {
            $matches = self::wantedForListing($listing);

            foreach ($matches as $wanted) {
                UserNotification::publish(
                    (int) $wanted->user_id,
                    UserNotification::TYPE_LISTING,
                    'Possible match for your Wanted post',
                    '"'.$listing->title
                        .'" may match what you are looking for.',
                    route('listings.show', $listing),
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function score(
        WantedPost $wanted,
        Listing $listing
    ): int {
        $wantedTitle = self::tokens((string) $wanted->title);
        $wantedDescription = self::tokens(
            (string) $wanted->description
        );

        $listingTitle = self::tokens((string) $listing->title);
        $listingDescription = self::tokens(
            (string) $listing->description
        );

        $titleMatches = $wantedTitle
            ->intersect($listingTitle)
            ->unique()
            ->count();

        $crossMatches = $wantedTitle
            ->merge($wantedDescription)
            ->unique()
            ->intersect(
                $listingTitle
                    ->merge($listingDescription)
                    ->unique()
            )
            ->unique()
            ->count();

        return ($titleMatches * 5) + $crossMatches;
    }

    private static function compatibleCategoryIds(
        int $categoryId
    ): array {
        $category = Category::query()
            ->whereKey($categoryId)
            ->first();

        if (! $category) {
            return [$categoryId];
        }

        $descendantIds = Category::listingFilterIds($categoryId) ?? [];

        $ancestorIds = $category
            ->breadcrumbTrail()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return collect($descendantIds)
            ->merge($ancestorIds)
            ->push($categoryId)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function tokens(string $value): Collection
    {
        $normalized = Str::lower(
            Str::ascii($value)
        );

        $normalized = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $normalized
        ) ?? '';

        return collect(
            preg_split(
                '/\s+/',
                trim($normalized),
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: []
        )
            ->filter(
                fn (string $word): bool =>
                    strlen($word) >= 2
                    && ! in_array(
                        $word,
                        self::STOP_WORDS,
                        true
                    )
            )
            ->unique()
            ->values();
    }
}
