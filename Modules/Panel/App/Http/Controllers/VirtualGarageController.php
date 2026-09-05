<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Listing\Jobs\PublishVirtualGarageItem;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\Location\Models\District;
use Modules\Listing\Models\VirtualGarage;
use Modules\Listing\Models\VirtualGaragePhoto;
use Modules\Listing\Models\VirtualGarageItem;
use Modules\Listing\Support\VirtualGaragePhotoAnalyzer;
use Modules\Listing\Support\AiEntitlement;
use Modules\User\App\Models\Profile;

class VirtualGarageController extends Controller
{
    public function index(Request $request): View
    {
        $garages = VirtualGarage::query()
            ->ownedByUser($request->user()->getKey())
            ->withCount([
                'listings',
                'listings as available_listings_count' => fn ($query) => $query
                    ->where('listings.status', 'active')
                    ->where('listings.quantity_available', '>', 0),
                'listings as sold_listings_count' => fn ($query) => $query
                    ->where('listings.status', 'sold'),
            ])
            ->latest('id')
            ->paginate(20);

        return view('panel::virtual-garages.index', [
            'garages' => $garages,
        ]);
    }

    public function create(Request $request): View
    {
        $userId = $request->user()->getKey();

        $profile = Profile::query()
            ->where('user_id', $userId)
            ->first();

        $latestListing = Listing::query()
            ->ownedByUser($userId)
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->latest('id')
            ->first([
                'city',
                'country',
            ]);

        $defaultCity = trim(
            (string) ($profile?->city ?? '')
        );

        if ($defaultCity === '') {
            $defaultCity = trim(
                (string) ($latestListing?->city ?? '')
            );
        }

        $defaultCountry = trim(
            (string) ($profile?->country ?? '')
        );

        if ($defaultCountry === '') {
            $defaultCountry = trim(
                (string) ($latestListing?->country ?? '')
            );
        }

        if ($defaultCountry === '') {
            $defaultCountry = 'Australia';
        }

        $countries = Country::quickCreateOptions();
        $cities = City::quickCreateOptions();
        $districts = District::quickCreateOptions();

        $australia = collect($countries)->first(
            fn (array $country): bool =>
                mb_strtolower(
                    trim((string) ($country['name'] ?? ''))
                ) === 'australia'
        );

        $australiaId = is_array($australia)
            ? (int) $australia['id']
            : null;

        $locationCities = collect($cities)
            ->when(
                $australiaId,
                fn ($items) => $items->where(
                    'country_id',
                    $australiaId
                )
            )
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $defaultDistrict = collect($districts)->first(
            fn (array $district): bool =>
                mb_strtolower(
                    trim((string) ($district['name'] ?? ''))
                ) === mb_strtolower($defaultCity)
        );

        $selectedLocationCityId =
            is_array($defaultDistrict)
                ? (int) $defaultDistrict['city_id']
                : null;

        return view(
            'panel::virtual-garages.create',
            [
                'defaultCity' => $defaultCity,
                'defaultCountry' => $defaultCountry,
                'locationCities' => $locationCities,
                'locationDistricts' => $districts,
                'selectedLocationCityId' =>
                    $selectedLocationCityId,
            ]
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
        ]);

        $profile = Profile::query()
            ->where(
                'user_id',
                $request->user()->getKey()
            )
            ->first();

        $city = trim(
            (string) ($validated['city'] ?? '')
        );

        $country = trim(
            (string) ($validated['country'] ?? '')
        );

        if ($city === '') {
            $city = trim(
                (string) ($profile?->city ?? '')
            );
        }

        if ($country === '') {
            $country = trim(
                (string) ($profile?->country ?? '')
            );
        }

        if ($country === '') {
            $country = 'Australia';
        }

        $garage = VirtualGarage::query()->create([
            'user_id' => $request->user()->getKey(),
            'title' => trim($validated['title']),
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
            'city' => $city !== ''
                ? $city
                : null,
            'country' => $country,
            'status' => VirtualGarage::STATUS_DRAFT,
        ]);

        return redirect()
            ->route('panel.virtual-garages.edit', $garage)
            ->with(
                'success',
                'Virtual Garage created. Now add your items.'
            );
    }

    public function edit(
        Request $request,
        VirtualGarage $virtualGarage
    ): View {
        $virtualGarage->assertOwnedBy($request->user());

        $virtualGarage->load([
            'photos.items.category',
            'items' => fn ($query) => $query
                ->where(
                    'status',
                    '!=',
                    VirtualGarageItem::STATUS_SKIPPED
                ),
            'items.category',
        ]);

        $categories =
            \Modules\Category\Models\Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        $countries = Country::quickCreateOptions();
        $cities = City::quickCreateOptions();
        $districts = District::quickCreateOptions();

        $australia = collect($countries)->first(
            fn (array $country): bool =>
                mb_strtolower(
                    trim((string) ($country['name'] ?? ''))
                ) === 'australia'
        );

        $australiaId = is_array($australia)
            ? (int) $australia['id']
            : null;

        $locationCities = collect($cities)
            ->when(
                $australiaId,
                fn ($items) => $items->where(
                    'country_id',
                    $australiaId
                )
            )
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $currentDistrict = collect($districts)->first(
            fn (array $district): bool =>
                mb_strtolower(
                    trim((string) ($district['name'] ?? ''))
                ) === mb_strtolower(
                    trim((string) $virtualGarage->city)
                )
        );

        $selectedLocationCityId =
            is_array($currentDistrict)
                ? (int) $currentDistrict['city_id']
                : null;

        $listings = Listing::query()
            ->ownedByUser($request->user()->getKey())
            ->whereIn('status', ['active', 'sold'])
            ->latest('id')
            ->get();

        $selectedListingIds = $virtualGarage
            ->listings()
            ->pluck('listings.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('panel::virtual-garages.edit', [
            'garage' => $virtualGarage,
            'categories' => $categories,
            'listings' => $listings,
            'selectedListingIds' => $selectedListingIds,
            'locationCities' => $locationCities,
            'locationDistricts' => $districts,
            'selectedLocationCityId' =>
                $selectedLocationCityId,
        ]);
    }

    public function update(
        Request $request,
        VirtualGarage $virtualGarage
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy($request->user());

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'bulk_price' => [
                'nullable',
                'numeric',
                'min:1',
                'max:99999999',
            ],
            'allow_bulk_offers' => ['nullable', 'boolean'],
            'listing_ids' => ['nullable', 'array', 'max:100'],
            'listing_ids.*' => ['integer', 'distinct'],
        ]);

        $virtualGarage->update([
            'title' => trim($validated['title']),
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
            'city' => isset($validated['city'])
                ? trim($validated['city'])
                : null,
            'country' => isset($validated['country'])
                ? trim($validated['country'])
                : null,
            'bulk_price' => $validated['bulk_price'] ?? null,
            'allow_bulk_offers' =>
                $request->boolean('allow_bulk_offers'),
        ]);

        /*
         * Optional existing listings.
         * Never sync(), because that could detach
         * AI-created Virtual Garage listings.
         */
        $selectedIds = collect(
            $validated['listing_ids'] ?? []
        )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isNotEmpty()) {
            $validIds = Listing::query()
                ->ownedByUser(
                    $request->user()->getKey()
                )
                ->whereIn(
                    'id',
                    $selectedIds->all()
                )
                ->pluck('id');

            $sortOrder =
                $virtualGarage->listings()->count();

            foreach ($validIds as $listingId) {
                $virtualGarage
                    ->listings()
                    ->syncWithoutDetaching([
                        $listingId => [
                            'sort_order' =>
                                $sortOrder++,
                        ],
                    ]);
            }
        }

        if (
            $request->input('action')
                !== 'publish'
        ) {
            return back()->with(
                'success',
                'Virtual Garage updated.'
            );
        }

        $draftItems = $virtualGarage
            ->items()
            ->where(
                'status',
                VirtualGarageItem::STATUS_DRAFT
            )
            ->get();

        foreach ($draftItems as $item) {
            if (blank($item->title)) {
                return back()->withErrors([
                    'virtual_garage' =>
                        'Every garage item needs a name before publishing.',
                ]);
            }

            if (! $item->category_id) {
                return back()->withErrors([
                    'virtual_garage' =>
                        'Choose a category for every garage item before publishing.',
                ]);
            }

            if ($item->price === null) {
                return back()->withErrors([
                    'virtual_garage' =>
                        'Set a price for every garage item before publishing.',
                ]);
            }
        }

        /*
         * Publishing is deliberately asynchronous.
         *
         * Each draft item gets its own Redis queue job so
         * image conversion cannot block the browser request.
         */
        $queuedCount = $draftItems->count();

        if ($queuedCount === 0) {
            if (! $virtualGarage->listings()->exists()) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'virtual_garage' =>
                            'Add or approve at least one item before publishing your Virtual Garage.',
                    ]);
            }

            $virtualGarage->update([
                'status' =>
                    VirtualGarage::STATUS_ACTIVE,

                'starts_at' =>
                    $virtualGarage->starts_at
                        ?? now(),
            ]);

            return back()->with(
                'success',
                'Virtual Garage is now live.'
            );
        }

        foreach ($draftItems as $draftItem) {
            PublishVirtualGarageItem::dispatch(
                (int) $draftItem->getKey()
            );
        }

        return back()->with(
            'success',
            $queuedCount
                .' item(s) are being published in the background. '
                .'You can leave this page while Sell My Junk finishes the job.'
        );
    }

    public function uploadPhotos(
        Request $request,
        VirtualGarage $virtualGarage
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy($request->user());

        $validated = $request->validate([
            'photos' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],
            'photos.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ]);

        $entitlement = app(
            AiEntitlement::class
        );

        $user = $request->user();

        /*
         * Reject the entire upload before storing any files
         * if it would exceed the user's remaining AI scans.
         */
        $remainingScans = $entitlement->remaining($user);

        if (count($validated['photos']) > $remainingScans) {
            return back()
                ->withErrors([
                    'virtual_garage' =>
                        'You have '
                        .$remainingScans
                        .' AI scan(s) remaining, but selected '
                        .count($validated['photos'])
                        .' photo(s). Please select fewer photos '
                        .'or upgrade your membership.',
                ]);
        }

        $disk = config(
            'filesystems.default',
            'public'
        );

        if ($disk === 'local') {
            $disk = 'public';
        }

        $nextSort = (int) (
            $virtualGarage->photos()
                ->max('sort_order') ?? -1
        ) + 1;

        foreach ($validated['photos'] as $index => $photo) {
            $extension = strtolower(
                $photo->getClientOriginalExtension()
                ?: $photo->guessExtension()
                ?: 'jpg'
            );

            $filename = Str::ulid().'.'.$extension;

            $path = $photo->storeAs(
                'virtual-garages/'
                    .$virtualGarage->getKey()
                    .'/intake',
                $filename,
                $disk
            );

            $garagePhoto =
                VirtualGaragePhoto::query()->create([
                    'virtual_garage_id' =>
                        $virtualGarage->getKey(),
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' =>
                        $photo->getClientOriginalName(),
                    'mime_type' =>
                        $photo->getMimeType(),
                    'size' =>
                        $photo->getSize(),
                    'status' =>
                        VirtualGaragePhoto::STATUS_PENDING,
                    'sort_order' =>
                        $nextSort + $index,
                ]);

            if (! $entitlement->canScan($user)) {
                return back()
                    ->withErrors([
                        'virtual_garage' =>
                            $entitlement
                                ->exhaustedMessage(
                                    $user
                                ),
                    ]);
            }

            $analysis = app(
                VirtualGaragePhotoAnalyzer::class
            )->analyze($photo);

            if (blank($analysis['error'] ?? null)) {
                $entitlement->recordSuccess(
                    $user,
                    'virtual_garage',
                    (int) $garagePhoto->getKey(),
                    [
                        'virtual_garage_id' =>
                            (int)
                            $virtualGarage->getKey(),

                        'detected_items' =>
                            count(
                                $analysis['items']
                                    ?? []
                            ),
                    ]
                );
                foreach (
                    $analysis['items'] ?? []
                    as $itemIndex => $item
                ) {
                    VirtualGarageItem::query()->create([
                        'virtual_garage_id' =>
                            $virtualGarage->getKey(),

                        'virtual_garage_photo_id' =>
                            $garagePhoto->getKey(),

                        'category_id' =>
                            $item['category_id'] ?? null,

                        'title' =>
                            $item['title'],

                        'description' =>
                            $item['description'] ?? null,

                        'suggested_price' =>
                            $item['suggested_price']
                                ?? null,

                        'price' =>
                            $item['suggested_price']
                                ?? null,

                        'currency' => 'AUD',

                        'condition' =>
                            $item['condition'] ?? null,

                        'confidence' =>
                            $item['confidence'] ?? null,

                        'ai_data' => [
                            'source' =>
                                'virtual_garage_ai',
                        ],

                        'status' =>
                            VirtualGarageItem::STATUS_DRAFT,

                        'sort_order' =>
                            $itemIndex,
                    ]);
                }

                $garagePhoto->update([
                    'status' =>
                        VirtualGaragePhoto::STATUS_PROCESSED,
                ]);
            } else {
                $entitlement->recordFailure(
                    $user,
                    'virtual_garage',
                    (int) $garagePhoto->getKey(),
                    [
                        'virtual_garage_id' =>
                            (int)
                            $virtualGarage->getKey(),

                        'error' =>
                            (string)
                            ($analysis['error'] ?? ''),
                    ]
                );
            }
        }

        $detectedCount =
            $virtualGarage->items()
                ->whereIn(
                    'virtual_garage_photo_id',
                    $virtualGarage->photos()
                        ->latest('id')
                        ->limit(
                            count($validated['photos'])
                        )
                        ->pluck('id')
                )
                ->count();

        return back()->with(
            'success',
            count($validated['photos'])
                .' garage photo(s) uploaded. '
                .$detectedCount
                .' item(s) detected by AI.'
        );
    }

    public function analyzePhoto(
        Request $request,
        VirtualGarage $virtualGarage,
        VirtualGaragePhoto $photo
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy($request->user());

        abort_unless(
            (int) $photo->virtual_garage_id
                === (int) $virtualGarage->getKey(),
            404
        );

        $user = $request->user();

        $entitlement = app(
            AiEntitlement::class
        );

        /*
         * Retry AI is also an AI scan and must obey
         * the same account allowance.
         */
        if (! $entitlement->canScan($user)) {
            return back()
                ->withErrors([
                    'virtual_garage_ai' =>
                        $entitlement->exhaustedMessage(
                            $user
                        ),
                ]);
        }

        $path = \Illuminate\Support\Facades\Storage::disk(
            $photo->disk
        )->path($photo->path);

        abort_unless(is_file($path), 404);

        $file = new \Illuminate\Http\UploadedFile(
            $path,
            $photo->original_name ?: basename($photo->path),
            $photo->mime_type ?: null,
            null,
            true
        );

        $analysis = app(
            VirtualGaragePhotoAnalyzer::class
        )->analyze($file);

        if (filled($analysis['error'] ?? null)) {
            $entitlement->recordFailure(
                $user,
                'virtual_garage',
                (int) $photo->getKey(),
                [
                    'virtual_garage_id' =>
                        (int) $virtualGarage->getKey(),

                    'retry' => true,
                ]
            );

            return back()->withErrors([
                'virtual_garage_ai' =>
                    $analysis['error'],
            ]);
        }

        $entitlement->recordSuccess(
            $user,
            'virtual_garage',
            (int) $photo->getKey(),
            [
                'virtual_garage_id' =>
                    (int) $virtualGarage->getKey(),

                'detected_items' =>
                    count(
                        $analysis['items'] ?? []
                    ),

                'retry' => true,
            ]
        );

        /*
         * Re-analysis replaces any unpublished AI drafts
         * previously created from this photo.
         */
        $photo->items()
            ->whereNull('listing_id')
            ->delete();

        foreach (
            $analysis['items'] ?? []
            as $itemIndex => $item
        ) {
            VirtualGarageItem::query()->create([
                'virtual_garage_id' =>
                    $virtualGarage->getKey(),

                'virtual_garage_photo_id' =>
                    $photo->getKey(),

                'category_id' =>
                    $item['category_id'] ?? null,

                'title' =>
                    $item['title'],

                'description' =>
                    $item['description'] ?? null,

                'suggested_price' =>
                    $item['suggested_price']
                        ?? null,

                'price' =>
                    $item['suggested_price']
                        ?? null,

                'currency' => 'AUD',

                'condition' =>
                    $item['condition'] ?? null,

                'confidence' =>
                    $item['confidence'] ?? null,

                'ai_data' => [
                    'source' =>
                        'virtual_garage_ai',
                ],

                'status' =>
                    VirtualGarageItem::STATUS_DRAFT,

                'sort_order' =>
                    $itemIndex,
            ]);
        }

        $photo->update([
            'status' =>
                VirtualGaragePhoto::STATUS_PROCESSED,
        ]);

        return back()->with(
            'success',
            count($analysis['items'] ?? [])
                .' item(s) detected by AI.'
        );
    }

    public function updateItem(
        Request $request,
        VirtualGarage $virtualGarage,
        VirtualGarageItem $item
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy($request->user());

        abort_unless(
            (int) $item->virtual_garage_id
                === (int) $virtualGarage->getKey(),
            404
        );

        abort_if(
            $item->listing_id !== null,
            409,
            'Published items cannot be edited here.'
        );

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:150',
            ],

            'category_id' => [
                'nullable',
                'integer',
            ],

            'price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999',
            ],

            'condition' => [
                'nullable',
                'string',
                'max:100',
            ],

            'description' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ]);

        $categoryId = null;

        if (! empty($validated['category_id'])) {
            $categoryExists =
                \Modules\Category\Models\Category::query()
                    ->whereKey(
                        (int) $validated['category_id']
                    )
                    ->where('is_active', true)
                    ->exists();

            if (! $categoryExists) {
                return back()->withErrors([
                    'category_id' =>
                        'Please choose a valid category.',
                ]);
            }

            $categoryId =
                (int) $validated['category_id'];
        }

        $item->update([
            'title' =>
                trim($validated['title']),

            'category_id' =>
                $categoryId,

            'price' =>
                $validated['price'] ?? null,

            'condition' =>
                isset($validated['condition'])
                    ? trim($validated['condition'])
                    : null,

            'description' =>
                isset($validated['description'])
                    ? trim($validated['description'])
                    : null,
        ]);

        return back()->with(
            'success',
            'Garage item updated.'
        );
    }

    public function skipItem(
        Request $request,
        VirtualGarage $virtualGarage,
        VirtualGarageItem $item
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy($request->user());

        abort_unless(
            (int) $item->virtual_garage_id
                === (int) $virtualGarage->getKey(),
            404
        );

        abort_if(
            $item->listing_id !== null,
            409,
            'Published items cannot be skipped here.'
        );

        $item->update([
            'status' =>
                VirtualGarageItem::STATUS_SKIPPED,
        ]);

        return back()->with(
            'success',
            'Item removed from this Virtual Garage.'
        );
    }

    public function deletePhoto(
        Request $request,
        VirtualGarage $virtualGarage,
        VirtualGaragePhoto $photo
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy($request->user());

        abort_unless(
            (int) $photo->virtual_garage_id
                === (int) $virtualGarage->getKey(),
            404
        );

        $photo->delete();

        return back()->with(
            'success',
            'Garage photo removed.'
        );
    }

    public function complete(
        Request $request,
        VirtualGarage $virtualGarage
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy($request->user());

        $virtualGarage->update([
            'status' => VirtualGarage::STATUS_COMPLETED,
            'ends_at' => now(),
        ]);

        return back()->with(
            'success',
            'Virtual Garage completed.'
        );
    }
}
