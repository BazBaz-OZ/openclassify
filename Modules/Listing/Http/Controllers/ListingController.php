<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Favorite\App\Models\FavoriteSearch;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\ListingCustomFieldSchemaBuilder;
use Modules\Listing\Support\WantedMatcher;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\Location\Models\District;
use Modules\Offer\Models\Offer;
use Modules\Report\Models\Report;
use Modules\Review\Models\Review;
use Modules\Theme\Support\ThemeManager;

class ListingController extends Controller
{
    public function __construct(private ThemeManager $themes) {}

    public function index()
    {
        $search = trim((string) request('search', ''));

        $categoryId = request()->integer('category');
        $categoryId = $categoryId > 0 ? $categoryId : null;

        $cityId = request()->integer('city');
        $cityId = $cityId > 0 ? $cityId : null;

        $districtId = request()->integer('district');
        $districtId = $districtId > 0 ? $districtId : null;

        $sellerUserId = request()->integer('user');
        $sellerUserId = $sellerUserId > 0 ? $sellerUserId : null;

        $minPriceInput = trim((string) request('min_price', ''));
        $maxPriceInput = trim((string) request('max_price', ''));
        $minPrice = is_numeric($minPriceInput) ? max((float) $minPriceInput, 0) : null;
        $maxPrice = is_numeric($maxPriceInput) ? max((float) $maxPriceInput, 0) : null;

        $dateFilter = (string) request('date_filter', 'all');
        $allowedDateFilters = ['all', 'today', 'week', 'month'];
        if (! in_array($dateFilter, $allowedDateFilters, true)) {
            $dateFilter = 'all';
        }

        $sort = (string) request('sort', 'smart');
        $allowedSorts = ['smart', 'newest', 'oldest', 'price_asc', 'price_desc'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'smart';
        }

        $australia = Country::resolveLookup('Australia');
        $countryId = $australia ? (int) $australia->getKey() : null;

        $cities = $countryId
            ? City::query()
                ->where('country_id', $countryId)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'country_id'])
            : collect();

        $selectedCity = $cityId
            ? $cities->firstWhere('id', $cityId)
            : null;

        if (! $selectedCity) {
            $cityId = null;
            $districtId = null;
        }

        $districts = $selectedCity
            ? District::query()
                ->where('city_id', $selectedCity->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'city_id'])
            : collect();

        $selectedDistrict = $districtId
            ? $districts->firstWhere('id', $districtId)
            : null;

        if (! $selectedDistrict) {
            $districtId = null;
        }

        $locationNames = null;

        if ($selectedDistrict) {
            $locationNames = [(string) $selectedDistrict->name];
        } elseif ($selectedCity) {
            $locationNames = [
                (string) $selectedCity->name,
                ...$districts->pluck('name')->map(fn ($name) => (string) $name)->all(),
            ];
        }

        $listingDirectory = Category::listingDirectory($categoryId);

        $browseFilters = [
            'search' => $search,
            'country' => 'Australia',
            'city_names' => $locationNames,
            'user_id' => $sellerUserId,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'date_filter' => $dateFilter,
        ];

        $allListingsTotal = Listing::query()
            ->active()
            ->forBrowseFilters($browseFilters)
            ->count();

        $listingsQuery = Listing::query()
            ->active()
            ->withListingCardRelations()
            ->forBrowseFilters([
                ...$browseFilters,
                'category_ids' => $listingDirectory['filterIds'],
            ])
            ->applyBrowseSort($sort);

        $filteredListingsTotal = (clone $listingsQuery)->count();

        $listings = $listingsQuery
            ->paginate(16)
            ->withQueryString();

        $categories = $listingDirectory['categories'];
        $selectedCategory = $listingDirectory['selectedCategory'];

        $favoriteListingIds = [];
        $isCurrentSearchSaved = false;
        $conversationListingMap = [];

        if (auth()->check()) {
            $userId = (int) auth()->id();

            $favoriteListingIds = auth()->user()->favoriteListingIds();
            $conversationListingMap = Conversation::listingMapForBuyer($userId);

            $isCurrentSearchSaved = FavoriteSearch::isSavedForUser(auth()->user(), [
                'search' => $search,
                'category' => $categoryId,
            ]);
        }

        return view($this->themes->view('listing', 'index'), compact(
            'listings',
            'search',
            'categoryId',
            'countryId',
            'cityId',
            'districtId',
            'sellerUserId',
            'minPriceInput',
            'maxPriceInput',
            'dateFilter',
            'sort',
            'cities',
            'districts',
            'selectedCategory',
            'categories',
            'favoriteListingIds',
            'isCurrentSearchSaved',
            'conversationListingMap',
            'allListingsTotal',
            'filteredListingsTotal',
        ));
    }

    public function show(Listing $listing)
    {
        $listing->trackViewBy(auth()->id());

        $listing->loadMissing([
            'user:id,name,email',
            'category:id,name,parent_id,slug',
            'category.parent:id,name,parent_id,slug',
            'category.parent.parent:id,name,parent_id,slug',
            'clearOut:id,user_id,title,slug,status',
            'videos' => fn ($query) => $query->published()->ordered(),
        ]);
        $presentableCustomFields = ListingCustomFieldSchemaBuilder::presentableValues(
            $listing->category_id ? (int) $listing->category_id : null,
            $listing->custom_fields ?? [],
        );
        $gallery = $listing->galleryImageData();
        $listingVideos = $listing->getRelation('videos');
        $relatedListings = $listing->relatedSuggestions(12);
        $wantedMatches = WantedMatcher::wantedForListing(
            $listing,
            10
        );
        $themePillCategories = Category::themePills(10);
        $breadcrumbCategories = $listing->category
            ? $listing->category->breadcrumbTrail()
            : collect();

        $isListingFavorited = false;
        $isSellerFavorited = false;
        $detailConversation = null;

        if (auth()->check()) {
            $userId = (int) auth()->id();

            $isListingFavorited = in_array((int) $listing->getKey(), auth()->user()->favoriteListingIds(), true);

            if ($listing->user_id) {
                $isSellerFavorited = auth()->user()
                    ->favoriteSellers()
                    ->whereKey($listing->user_id)
                    ->exists();
            }

            if ($listing->user_id && (int) $listing->user_id !== $userId) {
                $detailConversation = Conversation::detailForBuyerListing(
                    (int) $listing->getKey(),
                    $userId,
                );
            }
        }

        $sellerId = $listing->getAttribute('user_id') === null ? null : (int) $listing->getAttribute('user_id');
        $sellerReviewSummary = $sellerId === null
            ? ['total' => 0, 'average' => 0.0]
            : Review::summaryForSeller($sellerId);
        $bestOffer = Offer::highestPendingForListing((int) $listing->getKey());
        $reportReasons = Report::reasons();

        return view($this->themes->view('listing', 'show'), compact(
            'listing',
            'sellerId',
            'sellerReviewSummary',
            'bestOffer',
            'reportReasons',
            'isListingFavorited',
            'isSellerFavorited',
            'presentableCustomFields',
            'detailConversation',
            'gallery',
            'listingVideos',
            'relatedListings',
            'wantedMatches',
            'themePillCategories',
            'breadcrumbCategories',
        ));
    }

    public function contact(Listing $listing): JsonResponse
    {
        abort_unless($listing->canRevealContactTo(auth()->user()), 403);

        return response()->json($listing->contactDetailsFor(auth()->user()));
    }


    public function searchSuggestions(): JsonResponse
    {
        $search = trim((string) request('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json([
                'listings' => [],
                'categories' => [],
            ]);
        }

        $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $listings = Listing::query()
            ->active()
            ->searchTerm($search)
            ->orderByRaw(
                'CASE WHEN title ILIKE ? THEN 0 ELSE 1 END',
                [$search.'%']
            )
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'city'])
            ->map(fn (Listing $listing): array => [
                'type' => 'listing',
                'id' => (int) $listing->getKey(),
                'label' => (string) $listing->title,
                'meta' => (string) ($listing->city ?? ''),
                'url' => route('listings.show', $listing),
            ])
            ->values();

        $categories = Category::query()
            ->active()
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhere('name', 'ilike', "%{$term}%");
                }
            })
            ->ordered()
            ->limit(3)
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'type' => 'category',
                'id' => (int) $category->getKey(),
                'label' => (string) $category->name,
                'meta' => 'Category',
                'url' => route('listings.index', ['category' => $category->getKey()]),
            ])
            ->values();

        return response()->json([
            'listings' => $listings,
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return redirect()->route('panel.listings.create');
    }

    public function store()
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return redirect()
            ->route('panel.listings.create')
            ->with('success', 'You were redirected to the listing creation screen.');
    }
}
