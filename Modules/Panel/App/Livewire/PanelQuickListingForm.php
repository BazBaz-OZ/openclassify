<?php

declare(strict_types=1);

namespace Modules\Panel\App\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\WantedMatcher;
use Modules\Listing\Models\ListingCustomField;
use Modules\Listing\Models\VirtualGaragePhoto;
use Modules\Listing\Support\ListingCustomFieldSchemaBuilder;
use Modules\Listing\Support\ListingPanelHelper;
use Modules\Listing\Support\QuickListingCategorySuggester;
use Modules\Listing\Support\AiEntitlement;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\Location\Models\District;
use Modules\Site\App\Support\LocalMedia;
use Modules\User\App\Models\Profile;
use Modules\Video\Models\Video;
use Throwable;

class PanelQuickListingForm extends Component
{
    use WithFileUploads;

    private const TOTAL_STEPS = 5;

    private const DRAFT_SESSION_KEY = 'panel_quick_listing_draft';

    private const PUBLISH_TOKEN_SESSION_KEY = 'panel_quick_listing_publish_token';

    private const OTHER_CITY_ID = -1;

    public array $photos = [];

    public array $videos = [];

    public array $categories = [];

    public array $countries = [];

    public array $cities = [];

    public array $districts = [];

    public array $listingCustomFields = [];

    public array $customFieldValues = [];

    public int $currentStep = 1;

    public string $categorySearch = '';

    public ?int $selectedCategoryId = null;

    public ?int $activeParentCategoryId = null;

    public ?int $detectedCategoryId = null;

    public ?float $detectedConfidence = null;

    public ?string $detectedReason = null;

    public ?string $detectedError = null;

    public array $detectedAlternatives = [];

    public bool $isDetecting = false;

    public string $listingTitle = '';

    public string $price = '';

    public int $quantity = 1;

    public string $description = '';

    public ?int $selectedCountryId = null;

    public ?int $selectedCityId = null;

    public ?int $selectedDistrictId = null;

    public bool $isPublishing = false;

    public bool $shouldPersistDraft = true;

    public ?string $publishError = null;

    public string $publishToken = '';

    public ?int $garagePhotoId = null;

    public ?int $virtualGarageId = null;

    public string $garagePhotoUrl = '';

    public string $garagePhotoName = '';

    public function mount(): void
    {
        if ($this->publishToken === '') {
            $this->publishToken = (string) session()->get(
                self::PUBLISH_TOKEN_SESSION_KEY,
                ''
            );

            if ($this->publishToken === '') {
                $this->publishToken = (string) Str::uuid();

                session()->put(
                    self::PUBLISH_TOKEN_SESSION_KEY,
                    $this->publishToken
                );
            }
        }

        $this->loadCategories();
        $this->loadLocations();
        $this->selectAustralia();
        $this->hydrateLocationDefaultsFromProfile();

        if (request()->filled('garage_photo')) {
            $this->loadGarageSourcePhoto();
        } else {
            $this->restoreDraft();
        }

        $this->ensureSelectedDistrictBelongsToAustralia();
    }

    public function render()
    {
        return view('panel::quick-create');
    }

    public function dehydrate(): void
    {
        if (! $this->shouldPersistDraft) {
            return;
        }

        $this->persistDraft();
    }

    public function updatedPhotos(): void
    {
        $this->validatePhotos();

        /*
         * A new/changed photo must receive a fresh AI
         * classification. Never reuse the previous
         * photo's detected category.
         */
        $this->detectedCategoryId = null;
        $this->detectedConfidence = null;
        $this->detectedReason = null;
        $this->detectedError = null;
        $this->detectedAlternatives = [];
        $this->activeParentCategoryId = null;
    }

    public function updatedVideos(): void
    {
        $this->validateVideos();
    }

    public function updatedSelectedCountryId(): void
    {
        $this->selectedCityId = null;
        $this->selectedDistrictId = null;
    }

    public function updatedSelectedDistrictId(): void
    {
        $this->ensureSelectedDistrictBelongsToAustralia();
    }

    public function removePhoto(int $index): void
    {
        if (! isset($this->photos[$index])) {
            return;
        }

        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function removeVideo(int $index): void
    {
        if (! isset($this->videos[$index])) {
            return;
        }

        unset($this->videos[$index]);
        $this->videos = array_values($this->videos);
    }

    public function goToStep(int $step): void
    {
        $this->publishError = null;
        $this->currentStep = max(1, min(self::TOTAL_STEPS, $step));
    }

    public function goToCategoryStep(): void
    {
        $this->publishError = null;
        $this->validatePhotos();
        $this->validateVideos();
        $this->currentStep = 2;

        $provider = (string) config('quick-listing.ai_provider', 'openai');
        $providerKey = config("ai.providers.{$provider}.key");

        if (
            filled($providerKey)
            && ! $this->isDetecting
            && ! $this->detectedCategoryId
        ) {
            $this->detectCategoryFromImage();
        }
    }

    public function goToDetailsStep(): void
    {
        $this->publishError = null;
        $this->validateCategoryStep();
        $this->currentStep = 3;
    }

    public function goToFeaturesStep(): void
    {
        $this->publishError = null;
        $this->validateCategoryStep();
        $this->validateDetailsStep();
        $this->currentStep = 4;
    }

    public function goToPreviewStep(): void
    {
        $this->publishError = null;
        $this->validateCategoryStep();
        $this->validateDetailsStep();
        $this->validateCustomFieldsStep();
        $this->currentStep = 5;
    }

    public function detectCategoryFromImage(): void
    {
        $image = null;

        if (
            isset($this->photos[0])
            && $this->photos[0] instanceof TemporaryUploadedFile
        ) {
            $temporaryPhoto = $this->photos[0];

            $temporaryPath =
                $temporaryPhoto->getRealPath();

            if (
                is_string($temporaryPath)
                && $temporaryPath !== ''
                && is_file($temporaryPath)
            ) {
                $image = new UploadedFile(
                    $temporaryPath,
                    $temporaryPhoto
                        ->getClientOriginalName(),
                    $temporaryPhoto
                        ->getMimeType(),
                    null,
                    true
                );
            }
        } elseif ($this->garagePhotoId) {
            $garagePhoto = VirtualGaragePhoto::query()
                ->with('virtualGarage:id,user_id')
                ->find($this->garagePhotoId);

            $user = auth()->user();

            if (
                $garagePhoto
                && $user
                && $garagePhoto->virtualGarage
                && (int) $garagePhoto->virtualGarage->user_id
                    === (int) $user->getKey()
            ) {
                $path = Storage::disk(
                    $garagePhoto->disk
                )->path($garagePhoto->path);

                if (is_file($path)) {
                    $image = new UploadedFile(
                        $path,
                        $garagePhoto->original_name
                            ?: basename($garagePhoto->path),
                        $garagePhoto->mime_type ?: null,
                        null,
                        true
                    );
                }
            }
        }

        if (! $image instanceof UploadedFile) {
            return;
        }

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $entitlement = app(
            AiEntitlement::class
        );

        if (! $entitlement->canScan($user)) {
            $this->detectedError =
                $entitlement->exhaustedMessage(
                    $user
                );

            $this->detectedReason = null;
            $this->detectedAlternatives = [];

            return;
        }

        $this->isDetecting = true;
        $this->detectedError = null;
        $this->detectedReason = null;
        $this->detectedAlternatives = [];

        try {
            $result = app(
                QuickListingCategorySuggester::class
            )->suggestFromImage($image);

            $this->detectedCategoryId =
                $result['category_id'];

            $this->detectedConfidence =
                $result['confidence'];

            $this->detectedReason =
                $result['reason'];

            $this->detectedError =
                $result['error'];

            $this->detectedAlternatives =
                $result['alternatives'];

            if (blank($result['error'] ?? null)) {
                \Log::info(
                    'SMJ AI usage success hook reached',
                    [
                        'user_id' => $user->getKey(),
                        'feature' => 'listing_category',
                    ]
                );

                $entitlement->recordSuccess(
                    $user,
                    'listing_category',
                    null,
                    [
                        'detected' =>
                            (bool) (
                                $result['detected']
                                    ?? false
                            ),
                    ]
                );
            } else {
                $entitlement->recordFailure(
                    $user,
                    'listing_category',
                    null,
                    [
                        'error' =>
                            (string)
                            ($result['error'] ?? ''),
                    ]
                );
            }

            if ($this->detectedCategoryId) {
                $this->selectCategory(
                    $this->detectedCategoryId
                );
            }
        } finally {
            $this->isDetecting = false;
        }
    }

    public function enterCategory(int $categoryId): void
    {
        if (! $this->categoryExists($categoryId)) {
            return;
        }

        $this->activeParentCategoryId = $categoryId;
        $this->categorySearch = '';
    }

    public function backToRootCategories(): void
    {
        $this->activeParentCategoryId = null;
        $this->categorySearch = '';
    }

    public function selectCategory(int $categoryId): void
    {
        if (! $this->categoryExists($categoryId)) {
            return;
        }

        $this->publishError = null;
        $this->selectedCategoryId = $categoryId;

        if ($this->isFreeStuff) {
            $this->price = '0';
        }

        $this->loadListingCustomFields();
    }

    public function publishListing(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            $this->redirectRoute('verification.notice');

            return;
        }

        if ($this->isPublishing) {
            return;
        }

        $this->isPublishing = true;
        $this->publishError = null;
        $this->resetErrorBag();

        try {
            $this->validatePhotos();
            $this->validateVideos();
            $this->validateCategoryStep();
            $this->validateDetailsStep();
            $this->validateCustomFieldsStep();

            $listing = $this->createListing();

            WantedMatcher::notifyMatchesForListing($listing);
        } catch (ValidationException $exception) {
            $this->isPublishing = false;
            $this->handlePublishValidationFailure($exception);

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->isPublishing = false;
            $this->publishError = 'The listing could not be created. Please try again.';
            session()->flash('error', 'The listing could not be created. Please try again.');

            return;
        }

        $this->isPublishing = false;
        session()->flash('success', 'Your listing has been created successfully.');
        $this->clearDraft();

        // A completed publish must never reuse this idempotency token.
        session()->forget(self::PUBLISH_TOKEN_SESSION_KEY);
        $this->publishToken = (string) Str::uuid();

        if (Route::has('panel.listings.edit')) {
            $this->redirectRoute('panel.listings.edit', ['listing' => $listing->getRouteKey()]);

            return;
        }

        $this->redirectRoute('panel.listings.index');
    }

    public function getIsFreeStuffProperty(): bool
    {
        if (! $this->selectedCategoryId) {
            return false;
        }

        $categories = collect($this->categories)->keyBy('id');
        $selected = $categories->get((int) $this->selectedCategoryId);

        if (! is_array($selected)) {
            return false;
        }

        if (mb_strtolower(trim((string) ($selected['name'] ?? ''))) === 'free stuff') {
            return true;
        }

        $parentId = (int) ($selected['parent_id'] ?? 0);

        if ($parentId < 1) {
            return false;
        }

        $parent = $categories->get($parentId);

        return is_array($parent)
            && mb_strtolower(trim((string) ($parent['name'] ?? ''))) === 'free stuff';
    }

    public function getRootCategoriesProperty(): array
    {
        return collect($this->categories)
            ->whereNull('parent_id')
            ->values()
            ->all();
    }

    public function getCurrentCategoriesProperty(): array
    {
        if (! $this->activeParentCategoryId) {
            return [];
        }

        $search = trim((string) $this->categorySearch);
        $all = collect($this->categories);
        $parent = $all->firstWhere('id', $this->activeParentCategoryId);
        $children = $all->where('parent_id', $this->activeParentCategoryId)->values();

        $combined = collect();

        if (is_array($parent)) {
            $combined->push($parent);
        }

        $combined = $combined->concat($children);

        return $combined
            ->when(
                $search !== '',
                fn (Collection $categories): Collection => $categories->filter(
                    fn (array $category): bool => str_contains(
                        mb_strtolower($category['name']),
                        mb_strtolower($search)
                    )
                )
            )
            ->values()
            ->all();
    }

    public function getCurrentParentNameProperty(): string
    {
        if (! $this->activeParentCategoryId) {
            return 'Category Selection';
        }

        $category = collect($this->categories)->firstWhere('id', $this->activeParentCategoryId);

        return (string) ($category['name'] ?? 'Category Selection');
    }

    public function getCurrentStepTitleProperty(): string
    {
        return match ($this->currentStep) {
            1 => 'Photos',
            2 => 'Category',
            3 => 'Basics',
            4 => 'Details',
            5 => 'Review',
            default => 'New Listing',
        };
    }

    public function getCurrentStepHintProperty(): string
    {
        return match ($this->currentStep) {
            1 => 'Add photos and optional videos first.',
            2 => 'Pick the right category.',
            3 => 'Add the basics.',
            4 => 'Add extra details if needed.',
            5 => 'Check everything before publishing.',
            default => 'Create a new listing.',
        };
    }

    public function getSelectedCategoryNameProperty(): ?string
    {
        if (! $this->selectedCategoryId) {
            return null;
        }

        $category = collect($this->categories)->firstWhere('id', $this->selectedCategoryId);

        return $category['name'] ?? null;
    }

    public function getSelectedCategoryPathProperty(): string
    {
        if (! $this->selectedCategoryId) {
            return '';
        }

        return implode(' › ', $this->categoryPathParts($this->selectedCategoryId));
    }

    public function getDetectedAlternativeNamesProperty(): array
    {
        if ($this->detectedAlternatives === []) {
            return [];
        }

        $categoriesById = collect($this->categories)->keyBy('id');

        return collect($this->detectedAlternatives)
            ->map(fn (int $id): ?string => $categoriesById[$id]['name'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function getAvailableCitiesProperty(): array
    {
        if (! $this->selectedCountryId) {
            return [];
        }

        $cities = collect($this->cities)
            ->where('country_id', $this->selectedCountryId)
            ->values()
            ->all();

        if ($cities !== []) {
            return $cities;
        }

        return [[
            'id' => self::OTHER_CITY_ID,
            'name' => 'Other',
            'country_id' => $this->selectedCountryId,
        ]];
    }

    public function getDistrictGroupsProperty(): array
    {
        $groups = [];

        foreach ($this->availableCities as $city) {
            $districts = collect($this->districts)
                ->where('city_id', (int) $city['id'])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            if ($districts === []) {
                continue;
            }

            $groups[] = [
                'city' => $city['name'],
                'districts' => $districts,
            ];
        }

        return $groups;
    }

    public function getAvailableDistrictsProperty(): array
    {
        if (! $this->selectedCityId) {
            return [];
        }

        return collect($this->districts)
            ->where('city_id', (int) $this->selectedCityId)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function getSelectedDistrictNameProperty(): ?string
    {
        if (! $this->selectedDistrictId) {
            return null;
        }

        $district = collect($this->districts)
            ->firstWhere('id', (int) $this->selectedDistrictId);

        return is_array($district)
            ? ($district['name'] ?? null)
            : null;
    }

    public function getSelectedCountryNameProperty(): ?string
    {
        if (! $this->selectedCountryId) {
            return null;
        }

        $country = collect($this->countries)->firstWhere('id', $this->selectedCountryId);

        return $country['name'] ?? null;
    }

    public function getSelectedCityNameProperty(): ?string
    {
        if (! $this->selectedCityId) {
            return null;
        }

        if ((int) $this->selectedCityId === self::OTHER_CITY_ID) {
            return 'Other';
        }

        $city = collect($this->cities)->firstWhere('id', $this->selectedCityId);

        return $city['name'] ?? null;
    }

    public function getPreviewCustomFieldsProperty(): array
    {
        return ListingCustomFieldSchemaBuilder::presentableValues(
            $this->selectedCategoryId,
            $this->sanitizedCustomFieldValues(),
        );
    }

    public function getTitleCharactersProperty(): int
    {
        return mb_strlen($this->listingTitle);
    }

    public function getDescriptionCharactersProperty(): int
    {
        return mb_strlen($this->description);
    }

    public function getCurrentUserNameProperty(): string
    {
        return (string) (auth()->user()?->name ?: 'User');
    }

    public function getCurrentUserInitialProperty(): string
    {
        return Str::upper(Str::substr($this->currentUserName, 0, 1));
    }

    public function categoryIconComponent(?string $icon): string
    {
        return match ($icon) {
            'car' => 'heroicon-o-truck',
            'laptop', 'computer' => 'heroicon-o-computer-desktop',
            'shirt' => 'heroicon-o-swatch',
            'home', 'sofa' => 'heroicon-o-home-modern',
            'briefcase' => 'heroicon-o-briefcase',
            'wrench' => 'heroicon-o-wrench-screwdriver',
            'football' => 'heroicon-o-trophy',
            'phone', 'mobile' => 'heroicon-o-device-phone-mobile',
            default => 'heroicon-o-tag',
        };
    }

    private function loadGarageSourcePhoto(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $photoId = request()->integer('garage_photo');

        if ($photoId < 1) {
            return;
        }

        $photo = VirtualGaragePhoto::query()
            ->with('virtualGarage:id,user_id')
            ->findOrFail($photoId);

        abort_unless(
            $photo->virtualGarage
                && (int) $photo->virtualGarage->user_id
                    === (int) $user->getKey(),
            404
        );

        abort_unless(
            $photo->status === VirtualGaragePhoto::STATUS_PENDING,
            404
        );

        $this->garagePhotoId = (int) $photo->getKey();
        $this->virtualGarageId =
            (int) $photo->virtual_garage_id;
        $this->garagePhotoUrl = $photo->url();
        $this->garagePhotoName =
            (string) ($photo->original_name ?: 'Garage photo');

        $this->photos = [];
        $this->videos = [];
        $this->currentStep = 1;
    }

    private function validatePhotos(): void
    {
        $hasGaragePhoto = $this->garagePhotoId !== null;

        $this->validate([
            'photos' => [
                $hasGaragePhoto ? 'nullable' : 'required',
                'array',
                $hasGaragePhoto ? 'min:0' : 'min:1',
                'max:'.config('quick-listing.max_photo_count', 20),
            ],
            'photos.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:'.config('quick-listing.max_photo_size_kb', 5120),
            ],
        ]);
    }

    private function validateVideos(): void
    {
        $this->validate([
            'videos' => [
                'nullable',
                'array',
                'max:'.config('video.max_listing_videos', 5),
            ],
            'videos.*' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/webm,video/x-matroska,video/x-msvideo',
                'max:'.config('video.max_upload_size_kb', 102400),
            ],
        ]);
    }

    private function validateCategoryStep(): void
    {
        $this->validate([
            'selectedCategoryId' => [
                'required',
                'integer',
                Rule::in(collect($this->categories)->pluck('id')->all()),
            ],
        ], [
            'selectedCategoryId.required' => 'Please choose a category.',
            'selectedCategoryId.in' => 'Please choose a valid category.',
        ]);
    }

    private function validateDetailsStep(): void
    {
        $this->validate([
            'listingTitle' => ['required', 'string', 'max:70'],
            'price' => $this->isFreeStuff
                ? ['nullable', 'numeric', 'min:0']
                : ['required', 'numeric', 'min:0.01'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'description' => ['required', 'string', 'max:1450'],
            'selectedCountryId' => ['required', 'integer', Rule::in(collect($this->countries)->pluck('id')->all())],
            'selectedDistrictId' => [
                'nullable',
                'integer',
                Rule::in(collect($this->districts)->pluck('id')->all()),
            ],
        ], [
            'listingTitle.required' => 'A title is required.',
            'listingTitle.max' => 'The title may not exceed 70 characters.',
            'price.required' => 'A price is required.',
            'price.numeric' => 'The price must be numeric.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'description.required' => 'A description is required.',
            'description.max' => 'The description may not exceed 1450 characters.',
            'selectedCountryId.required' => 'Please choose a country.',
        ]);
    }

    private function validateCustomFieldsStep(): void
    {
        $rules = [];

        foreach ($this->listingCustomFields as $field) {
            $fieldRules = [];
            $name = $field['name'];
            $statePath = "customFieldValues.{$name}";
            $type = $field['type'];
            $isRequired = (bool) $field['is_required'];

            if ($type === ListingCustomField::TYPE_BOOLEAN) {
                $fieldRules[] = 'nullable';
                $fieldRules[] = 'boolean';
            } else {
                $fieldRules[] = $isRequired ? 'required' : 'nullable';
            }

            $fieldRules = [
                ...$fieldRules,
                ...$this->customFieldTypeRules($type),
            ];

            if ($type === ListingCustomField::TYPE_SELECT) {
                $options = collect($field['options'] ?? [])->map(fn ($option): string => (string) $option)->all();
                $fieldRules[] = Rule::in($options);
            }

            $rules[$statePath] = $fieldRules;
        }

        if ($rules !== []) {
            $this->validate($rules);
        }
    }

    private function customFieldTypeRules(string $type): array
    {
        return match ($type) {
            ListingCustomField::TYPE_TEXT => ['string', 'max:255'],
            ListingCustomField::TYPE_TEXTAREA => ['string', 'max:2000'],
            ListingCustomField::TYPE_NUMBER => ['numeric'],
            ListingCustomField::TYPE_DATE => ['date'],
            default => ['sometimes'],
        };
    }

    private function createListing(): Listing
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $payload = [
            'title' => trim($this->listingTitle),
            'description' => trim($this->description),
            'price' => $this->isFreeStuff ? 0.0 : (float) $this->price,
            'quantity_total' => $this->quantity,
            'quantity_available' => $this->quantity,
            'currency' => ListingPanelHelper::defaultCurrency(),
            'category_id' => $this->selectedCategoryId,
            'status' => 'active',
            'custom_fields' => $this->sanitizedCustomFieldValues(),
            'contact_email' => (string) $user->email,
            'contact_phone' => Profile::phoneForUser($user),
            'country' => $this->selectedCountryName,
            'city' => $this->selectedDistrictName,
        ];

        $listing = Listing::query()
            ->where('user_id', $user->getKey())
            ->where('creation_token', $this->publishToken)
            ->first();

        if (! $listing) {
            $payload['creation_token'] = $this->publishToken;

            $listing = Listing::createFromFrontend(
                $payload,
                $user->getKey()
            );
        }

        $mediaDisk = $this->frontendMediaDisk();

        foreach ($this->photos as $photo) {
            if (! $photo instanceof TemporaryUploadedFile) {
                continue;
            }

            $listing->attachListingImage(
                $photo->getRealPath(),
                $photo->getClientOriginalName(),
                $mediaDisk
            );
        }

        if ($this->garagePhotoId) {
            $garagePhoto = VirtualGaragePhoto::query()
                ->with('virtualGarage:id,user_id')
                ->findOrFail($this->garagePhotoId);

            abort_unless(
                $garagePhoto->virtualGarage
                    && (int) $garagePhoto->virtualGarage->user_id
                        === (int) $user->getKey(),
                404
            );

            if (
                $garagePhoto->status
                    === VirtualGaragePhoto::STATUS_PENDING
            ) {
                $sourcePath = Storage::disk(
                    $garagePhoto->disk
                )->path($garagePhoto->path);

                $listing->attachListingImage(
                    $sourcePath,
                    $garagePhoto->original_name
                        ?: basename($garagePhoto->path),
                    $mediaDisk
                );

                $garagePhoto->virtualGarage
                    ->listings()
                    ->syncWithoutDetaching([
                        $listing->getKey(),
                    ]);

                $garagePhoto->forceFill([
                    'listing_id' => $listing->getKey(),
                    'status' =>
                        VirtualGaragePhoto::STATUS_PROCESSED,
                ])->save();
            }
        }

        foreach ($this->videos as $index => $video) {
            if (! $video instanceof TemporaryUploadedFile) {
                continue;
            }

            Video::createFromTemporaryUpload($listing, $video, [
                'disk' => $mediaDisk,
                'sort_order' => $index + 1,
                'title' => pathinfo($video->getClientOriginalName(), PATHINFO_FILENAME),
            ]);
        }

        return $listing;
    }

    private function sanitizedCustomFieldValues(): array
    {
        $fieldsByName = collect($this->listingCustomFields)->keyBy('name');

        return collect($this->customFieldValues)
            ->filter(fn ($value, $key): bool => $fieldsByName->has((string) $key))
            ->map(function ($value, $key) use ($fieldsByName): mixed {
                $field = $fieldsByName->get((string) $key);
                $type = (string) ($field['type'] ?? ListingCustomField::TYPE_TEXT);

                return match ($type) {
                    ListingCustomField::TYPE_NUMBER => is_numeric($value) ? (float) $value : null,
                    ListingCustomField::TYPE_BOOLEAN => (bool) $value,
                    default => is_string($value) ? trim($value) : $value,
                };
            })
            ->filter(function ($value, $key) use ($fieldsByName): bool {
                $field = $fieldsByName->get((string) $key);
                $type = (string) ($field['type'] ?? ListingCustomField::TYPE_TEXT);

                if ($type === ListingCustomField::TYPE_BOOLEAN) {
                    return true;
                }

                return ! is_null($value) && $value !== '';
            })
            ->all();
    }

    private function loadCategories(): void
    {
        $this->categories = Category::panelQuickCatalog();
    }

    private function loadLocations(): void
    {
        $this->countries = Country::quickCreateOptions();
        $this->cities = City::quickCreateOptions();
        $this->districts = District::quickCreateOptions();
    }

    private function selectAustralia(): void
    {
        $australia = collect($this->countries)->first(
            fn (array $country): bool =>
                mb_strtolower(trim((string) ($country['name'] ?? ''))) === 'australia'
        );

        $this->selectedCountryId = is_array($australia)
            ? (int) $australia['id']
            : null;
    }

    private function ensureSelectedDistrictBelongsToAustralia(): void
    {
        if (! $this->selectedDistrictId) {
            $this->selectedCityId = null;

            return;
        }

        $district = collect($this->districts)
            ->firstWhere('id', (int) $this->selectedDistrictId);

        if (! is_array($district)) {
            $this->selectedDistrictId = null;
            $this->selectedCityId = null;

            return;
        }

        $cityId = (int) ($district['city_id'] ?? 0);

        $cityIsAustralian = collect($this->availableCities)
            ->contains(fn (array $city): bool => (int) $city['id'] === $cityId);

        if (! $cityIsAustralian) {
            $this->selectedDistrictId = null;
            $this->selectedCityId = null;

            return;
        }

        $this->selectedCityId = $cityId;
    }

    private function loadListingCustomFields(): void
    {
        $this->listingCustomFields = ListingCustomField::panelFieldDefinitions($this->selectedCategoryId);

        $allowed = collect($this->listingCustomFields)->pluck('name')->all();
        $this->customFieldValues = collect($this->customFieldValues)->only($allowed)->all();

        foreach ($this->listingCustomFields as $field) {
            if ($field['type'] === ListingCustomField::TYPE_BOOLEAN && ! array_key_exists($field['name'], $this->customFieldValues)) {
                $this->customFieldValues[$field['name']] = false;
            }
        }
    }

    private function hydrateLocationDefaultsFromProfile(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $profile = Profile::detailsForUser($user);

        if (! $profile) {
            return;
        }

        $profileCity = trim((string) ($profile->city ?? ''));

        if ($profileCity !== '' && $this->selectedCountryId) {
            $district = collect($this->districts)->first(
                fn (array $district): bool =>
                    mb_strtolower((string) $district['name']) === mb_strtolower($profileCity)
            );

            if (is_array($district)) {
                $this->selectedDistrictId = (int) $district['id'];
                $this->selectedCityId = (int) $district['city_id'];
            }
        }
    }

    private function categoryExists(int $categoryId): bool
    {
        return collect($this->categories)->contains(fn (array $category): bool => $category['id'] === $categoryId);
    }

    private function frontendMediaDisk(): string
    {
        return LocalMedia::disk();
    }

    private function handlePublishValidationFailure(ValidationException $exception): void
    {
        $errors = $exception->errors();

        foreach ($errors as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError($key, $message);
            }
        }

        $this->currentStep = $this->stepForValidationErrors(array_keys($errors));
        $this->publishError = collect($errors)->flatten()->filter()->first() ?: 'Please fix the highlighted fields before publishing.';
    }

    private function stepForValidationErrors(array $keys): int
    {
        $normalizedKeys = collect($keys)->map(fn ($key) => (string) $key)->values();

        if ($normalizedKeys->contains(fn ($key) => str_starts_with($key, 'photos') || str_starts_with($key, 'videos'))) {
            return 1;
        }

        if ($normalizedKeys->contains('selectedCategoryId')) {
            return 2;
        }

        if ($normalizedKeys->contains(fn ($key) => in_array($key, [
            'listingTitle',
            'price',
            'quantity',
            'description',
            'selectedCountryId',
            'selectedDistrictId',
        ], true))) {
            return 3;
        }

        if ($normalizedKeys->contains(fn ($key) => str_starts_with($key, 'customFieldValues.'))) {
            return 4;
        }

        return 5;
    }

    private function restoreDraft(): void
    {
        $draft = session()->get($this->draftSessionKey(), []);

        if (! is_array($draft) || $draft === []) {
            return;
        }

        $this->currentStep = max(1, min(self::TOTAL_STEPS, (int) ($draft['currentStep'] ?? 1)));
        $this->categorySearch = (string) ($draft['categorySearch'] ?? '');
        $this->selectedCategoryId = isset($draft['selectedCategoryId']) ? (int) $draft['selectedCategoryId'] : null;
        $this->activeParentCategoryId = isset($draft['activeParentCategoryId']) ? (int) $draft['activeParentCategoryId'] : null;
        $provider = (string) config('quick-listing.ai_provider', 'openai');
        $providerKey = config("ai.providers.{$provider}.key");

        if (filled($providerKey)) {
            $this->detectedCategoryId = isset($draft['detectedCategoryId']) ? (int) $draft['detectedCategoryId'] : null;
            $this->detectedConfidence = isset($draft['detectedConfidence']) ? (float) $draft['detectedConfidence'] : null;
            $this->detectedReason = isset($draft['detectedReason']) ? (string) $draft['detectedReason'] : null;
            $this->detectedError = isset($draft['detectedError']) ? (string) $draft['detectedError'] : null;
            $this->detectedAlternatives = collect($draft['detectedAlternatives'] ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } else {
            $this->detectedCategoryId = null;
            $this->detectedConfidence = null;
            $this->detectedReason = null;
            $this->detectedError = null;
            $this->detectedAlternatives = [];
        }
        $this->listingTitle = (string) ($draft['listingTitle'] ?? '');
        $this->price = (string) ($draft['price'] ?? '');
        $this->quantity = max(1, (int) ($draft['quantity'] ?? 1));
        $this->description = (string) ($draft['description'] ?? '');
        $this->selectedDistrictId = isset($draft['selectedDistrictId']) ? (int) $draft['selectedDistrictId'] : null;
        $this->customFieldValues = is_array($draft['customFieldValues'] ?? null) ? $draft['customFieldValues'] : [];

        if ($this->selectedCategoryId) {
            $this->loadListingCustomFields();
        }
    }

    private function persistDraft(): void
    {
        session()->put($this->draftSessionKey(), [
            'currentStep' => $this->currentStep,
            'categorySearch' => $this->categorySearch,
            'selectedCategoryId' => $this->selectedCategoryId,
            'activeParentCategoryId' => $this->activeParentCategoryId,
            'detectedCategoryId' => $this->detectedCategoryId,
            'detectedConfidence' => $this->detectedConfidence,
            'detectedReason' => $this->detectedReason,
            'detectedError' => null,
            'detectedAlternatives' => $this->detectedAlternatives,
            'listingTitle' => $this->listingTitle,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'description' => $this->description,
            'selectedDistrictId' => $this->selectedDistrictId,
            'customFieldValues' => $this->customFieldValues,
        ]);
    }

    private function clearDraft(): void
    {
        $this->shouldPersistDraft = false;
        session()->forget($this->draftSessionKey());
    }

    private function draftSessionKey(): string
    {
        $userId = auth()->id() ?: 'guest';

        return self::DRAFT_SESSION_KEY.'.'.$userId;
    }

    private function categoryPathParts(int $categoryId): array
    {
        $byId = collect($this->categories)->keyBy('id');
        $parts = [];
        $currentId = $categoryId;

        while ($currentId && $byId->has($currentId)) {
            $category = $byId->get($currentId);

            if (! is_array($category)) {
                break;
            }

            $parts[] = (string) $category['name'];
            $currentId = $category['parent_id'] ?? null;
        }

        return array_reverse($parts);
    }
}
