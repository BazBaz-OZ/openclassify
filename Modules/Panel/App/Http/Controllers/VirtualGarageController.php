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
            'items.historicalListing',
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

        $hasUnresolvedDuplicate =
            static function (
                VirtualGarageItem $item
            ): bool {
                $duplicate =
                    data_get(
                        $item->ai_data,
                        'duplicate'
                    );

                return
                    is_array($duplicate)
                    && filled(
                        $duplicate['item_id']
                        ?? null
                    );
            };

        $duplicateItems =
            $draftItems
                ->filter(
                    $hasUnresolvedDuplicate
                )
                ->values();

        /*
         * Initial publication is deliberately strict.
         *
         * A brand-new garage must have all duplicate
         * decisions resolved before it goes live.
         */
        if (
            $virtualGarage->status
                === VirtualGarage::STATUS_DRAFT
            && $duplicateItems->isNotEmpty()
        ) {
            return back()->withErrors([
                'virtual_garage' =>
                    'Resolve every possible duplicate before publishing. '
                    .'Choose Keep anyway or Skip duplicate for each flagged item.',
            ]);
        }

        /*
         * An already-live garage may publish newly
         * reviewed items without being blocked by other
         * items that still need duplicate review.
         */
        $publishItems =
            $virtualGarage->status
                === VirtualGarage::STATUS_ACTIVE
                ? $draftItems
                    ->reject(
                        $hasUnresolvedDuplicate
                    )
                    ->values()
                : $draftItems;

        foreach ($publishItems as $item) {
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
         * Each reviewed draft item gets its own Redis
         * queue job so image conversion cannot block
         * the browser request.
         */
        $queuedCount =
            $publishItems->count();

        if ($queuedCount === 0) {
            if ($duplicateItems->isNotEmpty()) {
                return back()->withErrors([
                    'virtual_garage' =>
                        'There are no reviewed items ready to publish. '
                        .'Resolve the remaining possible duplicates first.',
                ]);
            }

            if (! $virtualGarage->listings()->exists()) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'virtual_garage' =>
                            'Add or approve at least one item before publishing your Virtual Garage.',
                    ]);
            }

            if (
                $virtualGarage->status
                    === VirtualGarage::STATUS_DRAFT
            ) {
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

            return back()->with(
                'success',
                'There are no new reviewed items to publish.'
            );
        }

        foreach ($publishItems as $draftItem) {
            PublishVirtualGarageItem::dispatch(
                (int) $draftItem->getKey()
            );
        }

        $message =
            $queuedCount
            .' item(s) are being published in the background. '
            .'You can leave this page while Sell My Junk finishes the job.';

        if ($duplicateItems->isNotEmpty()) {
            $message .=
                ' '
                .$duplicateItems->count()
                .' possible duplicate(s) were left as drafts for review.';
        }

        return back()->with(
            'success',
            $message
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
                'max:'.config('quick-listing.max_photo_size_kb', 20480),
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
        $photoCount =
            count($validated['photos']);

        /*
         * Reserve the whole batch before storing photo #1.
         * This prevents another request from consuming scans
         * halfway through this upload.
         */
        $reservations = $entitlement->reserveScans(
            $user,
            $photoCount,
            'virtual_garage',
            null,
            [
                'virtual_garage_id' =>
                    (int) $virtualGarage->getKey(),
                'batch_upload' => true,
            ]
        );

        if ($reservations === null) {
            $remainingScans =
                $entitlement->remaining($user);
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

        try {
            foreach ($validated['photos'] as $index => $photo) {
            /*
             * Privacy protection:
             *
             * Re-encode the uploaded image before it is permanently
             * stored or sent to AI. This removes GPS/EXIF/XMP/IPTC
             * metadata that could disclose where the photo was taken.
             */
            app(
                \Modules\Listing\Support\UploadedImageSanitizer::class
            )->sanitize($photo);

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
                        \Illuminate\Support\Facades\Storage::disk(
                            $disk
                        )->size($path),
                    'status' =>
                        VirtualGaragePhoto::STATUS_PENDING,
                    'sort_order' =>
                        $nextSort + $index,
                ]);

            $reservation =
                $reservations[$index] ?? null;

            if (! $reservation) {
                throw new \RuntimeException(
                    'AI reservation missing for garage photo.'
                );
            }

            $analysis = app(
                VirtualGaragePhotoAnalyzer::class
            )->analyze($photo);

            if (blank($analysis['error'] ?? null)) {
                $entitlement->completeSuccess(
                    $reservation,
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
                $duplicateDetector = app(
                    \Modules\Listing\Support\VirtualGarageDuplicateDetector::class
                );

                foreach (
                    $analysis['items'] ?? []
                    as $itemIndex => $item
                ) {
                    $duplicateMatch =
                        $duplicateDetector->findStrongMatch(
                            (int) $virtualGarage->getKey(),
                            (int) $garagePhoto->getKey(),
                            (string) $item['title']
                        );

                    $itemAiData = [
                        'source' =>
                            'virtual_garage_ai',
                    ];

                    if ($duplicateMatch) {
                        $itemAiData['duplicate'] = [
                            'match' => 'strong',

                            'item_id' =>
                                (int) $duplicateMatch->getKey(),

                            'title' =>
                                $duplicateMatch->title,
                        ];
                    }

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

                        'bounding_box' =>
                            $item['bounding_box'] ?? null,

                        'ai_data' =>
                            $itemAiData,

                        'status' =>
                            VirtualGarageItem::STATUS_DRAFT,

                        'sort_order' =>
                            $itemIndex,
                    ]);
                }

                /*
                 * QUEUE_INITIAL_UPLOAD_SPOTLIGHTS
                 *
                 * AI detection is complete. Generate the
                 * presentation images in the queue so the
                 * browser request can return immediately.
                 */
                \Modules\Listing\Jobs\GenerateVirtualGaragePhotoSpotlights::dispatch(
                    (int) $garagePhoto->getKey()
                );

                $garagePhoto->update([
                    'status' =>
                        VirtualGaragePhoto::STATUS_PROCESSED,
                ]);
            } else {
                $entitlement->completeFailure(
                    $reservation,
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

        } catch (\Throwable $exception) {
            $entitlement->failPendingReservations(
                $reservations,
                [
                    'virtual_garage_id' =>
                        (int) $virtualGarage->getKey(),
                    'batch_upload' => true,
                    'aborted' => true,
                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw $exception;
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
        $path = \Illuminate\Support\Facades\Storage::disk(
            $photo->disk
        )->path($photo->path);

        /*
         * Verify the stored source exists before consuming
         * an AI reservation. A missing file should return 404
         * without temporarily reducing the user's allowance.
         */
        abort_unless(is_file($path), 404);

        $reservation = $entitlement->reserveScan(
            $user,
            'virtual_garage',
            (int) $photo->getKey(),
            [
                'virtual_garage_id' =>
                    (int) $virtualGarage->getKey(),
                'retry' => true,
            ]
        );

        if (! $reservation) {
            return back()
                ->withErrors([
                    'virtual_garage_ai' =>
                        $entitlement->exhaustedMessage(
                            $user
                        ),
                ]);
        }

        $file = new \Illuminate\Http\UploadedFile(
            $path,
            $photo->original_name ?: basename($photo->path),
            $photo->mime_type ?: null,
            null,
            true
        );

        try {
            $analysis = app(
                VirtualGaragePhotoAnalyzer::class
            )->analyze($file);
        } catch (\Throwable $exception) {
            $entitlement->failPendingReservations(
                [$reservation],
                [
                    'virtual_garage_id' =>
                        (int) $virtualGarage->getKey(),
                    'retry' => true,
                    'aborted' => true,
                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw $exception;
        }

        if (filled($analysis['error'] ?? null)) {
            $entitlement->completeFailure(
                $reservation,
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

        $entitlement->completeSuccess(
            $reservation,
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

        $duplicateDetector = app(
            \Modules\Listing\Support\VirtualGarageDuplicateDetector::class
        );

        foreach (
            $analysis['items'] ?? []
            as $itemIndex => $item
        ) {
            $duplicateMatch =
                $duplicateDetector->findStrongMatch(
                    (int) $virtualGarage->getKey(),
                    (int) $photo->getKey(),
                    (string) $item['title']
                );

            $itemAiData = [
                'source' =>
                    'virtual_garage_ai',
            ];

            if ($duplicateMatch) {
                $itemAiData['duplicate'] = [
                    'match' => 'strong',

                    'item_id' =>
                        (int) $duplicateMatch->getKey(),

                    'title' =>
                        $duplicateMatch->title,
                ];
            }

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

                'bounding_box' =>
                    $item['bounding_box'] ?? null,

                'ai_data' =>
                    $itemAiData,

                'status' =>
                    VirtualGarageItem::STATUS_DRAFT,

                'sort_order' =>
                    $itemIndex,
            ]);
        }

        /*
         * QUEUE_RETRY_UPLOAD_SPOTLIGHTS
         *
         * AI detection is complete. Generate the
         * presentation images in the queue so the
         * browser request can return immediately.
         */
        \Modules\Listing\Jobs\GenerateVirtualGaragePhotoSpotlights::dispatch(
            (int) $photo->getKey()
        );

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

    public function updateItemPhoto(
        Request $request,
        VirtualGarage $virtualGarage,
        VirtualGarageItem $item
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy(
            $request->user()
        );

        abort_unless(
            (int) $item->virtual_garage_id
                === (int) $virtualGarage->getKey(),
            404
        );

        /*
         * Validate the action FIRST.
         *
         * "Use original" must not care about crop
         * coordinates at all.
         */
        $action = $request->validate([
            'photo_action' => [
                'required',
                'string',
                'in:save_crop,use_original',
            ],
        ])['photo_action'];

        $cropper = app(
            \Modules\Listing\Support\VirtualGarageItemManualCropper::class
        );

        if ($action === 'use_original') {
            $cropper->clear($item);

            /*
             * If already published, restore the
             * sanitised original there as well.
             */
            if (
                $item->listing_id !== null
                && $item->photo
            ) {
                $listing =
                    \Modules\Listing\Models\Listing::query()
                        ->find(
                            $item->listing_id
                        );

                if ($listing) {
                    $sourcePath =
                        \Illuminate\Support\Facades\Storage::disk(
                            $item->photo->disk
                        )->path(
                            $item->photo->path
                        );

                    if (is_file($sourcePath)) {
                        $listing->replacePublicImage(
                            $sourcePath,
                            'garage-source-'
                                .$item->getKey()
                                .'-'
                                .basename(
                                    $item->photo->path
                                )
                        );
                    }
                }
            }

            return back()->with(
                'success',
                'Original garage photo restored.'
            );
        }

        /*
         * Only crop saves need crop coordinates.
         */
        $validated = $request->validate([
            'crop_center_x' => [
                'required',
                'numeric',
                'between:0,1',
            ],

            'crop_center_y' => [
                'required',
                'numeric',
                'between:0,1',
            ],

            'crop_zoom' => [
                'required',
                'numeric',
                'min:1',
                'max:4',
            ],

            'crop_rotation' => [
                'required',
                'numeric',
                'min:-180',
                'max:180',
            ],
        ]);

        $aiData =
            is_array($item->ai_data)
                ? $item->ai_data
                : [];

        $aiData['manual_crop'] = [
            'center_x' =>
                (float)
                $validated['crop_center_x'],

            'center_y' =>
                (float)
                $validated['crop_center_y'],

            'zoom' =>
                (float)
                $validated['crop_zoom'],

            'rotation' =>
                (float)
                $validated['crop_rotation'],

            'aspect_ratio' => '4:3',
        ];

        $item->forceFill([
            'ai_data' => $aiData,
        ])->save();

        /*
         * The browser has already rendered the seller's
         * pan / zoom / rotation into a small 4:3 image.
         *
         * Store that derivative instead of asking GD to
         * rotate the full-resolution phone photo.
         */
        $dataUrl =
            $request->input(
                'cropped_image'
            );

        if (
            ! is_string($dataUrl)
            || ! str_starts_with(
                $dataUrl,
                'data:image/'
            )
        ) {
            return back()->withErrors([
                'photo' =>
                    'The adjusted image was not received. Please try again.',
            ]);
        }

        if (
            strlen($dataUrl)
            > 12 * 1024 * 1024
        ) {
            return back()->withErrors([
                'photo' =>
                    'The adjusted image is too large.',
            ]);
        }

        $result =
            $cropper->storeRenderedCrop(
                $item,
                $dataUrl
            );

        if (! $result) {
            return back()->withErrors([
                'photo' =>
                    'The adjusted photo could not be created.',
            ]);
        }

        if (
            $item->listing_id !== null
        ) {
            $listing =
                \Modules\Listing\Models\Listing::query()
                    ->find(
                        $item->listing_id
                    );

            if ($listing) {
                $cropPath =
                    \Illuminate\Support\Facades\Storage::disk(
                        (string)
                        $result['disk']
                    )->path(
                        (string)
                        $result['path']
                    );

                if (is_file($cropPath)) {
                    $listing->replacePublicImage(
                        $cropPath,
                        basename(
                            (string)
                            $result['path']
                        )
                    );
                }
            }
        }

        return back()->with(
            'success',
            'Adjusted item photo saved.'
        );
    }

    public function keepDuplicate(
        Request $request,
        VirtualGarage $virtualGarage,
        VirtualGarageItem $item
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy(
            $request->user()
        );

        abort_unless(
            (int) $item->virtual_garage_id
                === (int) $virtualGarage->getKey(),
            404
        );

        abort_if(
            $item->listing_id !== null,
            409,
            'Published items cannot be changed here.'
        );

        $aiData =
            is_array($item->ai_data)
                ? $item->ai_data
                : [];

        $duplicate =
            $aiData['duplicate']
            ?? null;

        /*
         * Preserve the seller's decision for
         * diagnostics/auditing, but remove the
         * active warning.
         */
        if (is_array($duplicate)) {
            $aiData['duplicate_kept'] = [
                'item_id' =>
                    $duplicate['item_id']
                    ?? null,

                'title' =>
                    $duplicate['title']
                    ?? null,

                'kept_at' =>
                    now()->toIso8601String(),
            ];
        }

        unset(
            $aiData['duplicate']
        );

        $item->forceFill([
            'ai_data' => $aiData,
        ])->save();

        return back()->with(
            'success',
            'Item kept. Duplicate warning dismissed.'
        );
    }

    public function skipDuplicates(
        Request $request,
        VirtualGarage $virtualGarage
    ): RedirectResponse {
        $virtualGarage->assertOwnedBy(
            $request->user()
        );

        /*
         * Only unresolved duplicate drafts are affected.
         *
         * Published, previously kept, clean and already
         * skipped items are deliberately left untouched.
         */
        $items = $virtualGarage
            ->items()
            ->where(
                'status',
                VirtualGarageItem::STATUS_DRAFT
            )
            ->whereNull('listing_id')
            ->get()
            ->filter(
                static function (
                    VirtualGarageItem $item
                ): bool {
                    $duplicate =
                        data_get(
                            $item->ai_data,
                            'duplicate'
                        );

                    return
                        is_array($duplicate)
                        && filled(
                            $duplicate['item_id']
                            ?? null
                        );
                }
            );

        $skippedCount = 0;

        foreach ($items as $item) {
            $item->update([
                'status' =>
                    VirtualGarageItem::STATUS_SKIPPED,
            ]);

            $skippedCount++;
        }

        if ($skippedCount === 0) {
            return back()->with(
                'success',
                'There are no unresolved duplicates to skip.'
            );
        }

        return back()->with(
            'success',
            $skippedCount
                .' duplicate item(s) skipped.'
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
