@php
    $steps = [
        1 => __('panel::messages.photos'),
        2 => __('site::messages.category'),
        3 => __('site::messages.details'),
        4 => __('panel::messages.overview'),
    ];
@endphp

<div class="shell shell--narrow page">
    <div class="stack stack--loose">
        <header class="stack stack--tight">
            <p class="text-eyebrow">{{ __('panel::messages.new_listing') }}</p>
            <h1 class="title-page">{{ $this->currentStepTitle }}</h1>
            <p class="text-muted">{{ $this->currentStepHint }}</p>
        </header>

        <ol class="chip-row" aria-label="{{ __('panel::messages.new_listing') }}">
            @foreach($steps as $number => $label)
                <li>
                    <button
                        type="button"
                        class="pill {{ $currentStep === $number ? 'is-active' : '' }}"
                        wire:click="goToStep({{ $number }})"
                        @disabled($number > $currentStep)
                    >
                        <span>{{ $number }}</span>
                        <span>{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>

        @if($publishError)
            <div class="alert alert--critical" role="alert">
                <x-ui.icon name="shield"/>
                <span>{{ $publishError }}</span>
            </div>
        @endif

        @error('photos')
            <div class="alert alert--critical" role="alert"><x-ui.icon name="shield"/><span>{{ $message }}</span></div>
        @enderror

        @error('photos.*')
            <div class="alert alert--critical" role="alert"><x-ui.icon name="shield"/><span>{{ $message }}</span></div>
        @enderror

        @error('cameraPhoto')
            <div class="alert alert--critical" role="alert"><x-ui.icon name="shield"/><span>{{ $message }}</span></div>
        @enderror

        @error('videos')
            <div class="alert alert--critical" role="alert"><x-ui.icon name="shield"/><span>{{ $message }}</span></div>
        @enderror

        @error('videos.*')
            <div class="alert alert--critical" role="alert"><x-ui.icon name="shield"/><span>{{ $message }}</span></div>
        @enderror

        <section class="card">
            <div class="card__body">
                @if($currentStep === 1)
                    <div
                        class="upload"
                        data-upload-choice-guard
                        style="pointer-events:none"
                    >
                        <label class="upload__control" for="quick-camera-photo">
                            <x-ui.icon name="image"/>
                            <span class="title-card">Take Photo</span>
                            <span class="text-muted">Use your phone camera</span>
                            <input
                                id="quick-camera-photo"
                                type="file"
                                class="visually-hidden"
                                wire:model="cameraPhoto"
                                accept="image/*"
                                capture="environment"
                            >
                        </label>

                        <div wire:loading wire:target="cameraPhoto" class="text-muted">{{ __('panel::messages.uploading') }}</div>

                        <label class="upload__control" for="quick-photos">
                            <x-ui.icon name="image"/>
                            <span class="title-card">{{ __('panel::messages.photos') }}</span>
                            <span class="text-muted">{{ __('panel::messages.photos_hint') }}</span>
                            <input id="quick-photos" type="file" class="visually-hidden" wire:model="photos" multiple accept="image/*">
                        </label>

                        <div wire:loading wire:target="photos" class="text-muted">{{ __('panel::messages.uploading') }}</div>

                        @if($garagePhotoUrl !== '')
                            <div class="upload__grid">
                                <figure
                                    class="upload__preview"
                                    style="position:relative"
                                >
                                    <img
                                        src="{{ $garagePhotoUrl }}"
                                        alt="{{ $garagePhotoName }}"
                                    >

                                    <span
                                        class="badge badge--positive"
                                        style="
                                            position:absolute;
                                            bottom:6px;
                                            left:6px;
                                        "
                                    >
                                        Virtual Garage
                                    </span>
                                </figure>
                            </div>
                        @endif

                        @if($photos !== [])
                            <div class="upload__grid">
                                @foreach($photos as $index => $photo)
                                    <figure class="upload__preview" style="position:relative">
                                        <img src="{{ $photo->temporaryUrl() }}" alt="">
                                        <button
                                            type="button"
                                            class="icon-button"
                                            style="position:absolute;top:4px;inset-inline-end:4px;background:var(--surface-overlay);width:28px;height:28px"
                                            wire:click="removePhoto({{ $index }})"
                                            aria-label="{{ __('favorite::messages.remove') }}"
                                        ><x-ui.icon name="close"/></button>
                                    </figure>
                                @endforeach
                            </div>
                        @endif

                        <div class="upload">
                            <label class="upload__control" for="quick-videos">
                                <x-ui.icon name="video"/>
                                <span class="title-card">{{ __('panel::messages.videos') }}</span>
                                <input id="quick-videos" type="file" class="visually-hidden" wire:model="videos" multiple accept="video/*">
                            </label>

                            @if($videos !== [])
                                <ul class="stack stack--tight">
                                    @foreach($videos as $index => $video)
                                        <li class="row row--between">
                                            <span class="text-body text-clamp-1">{{ $video->getClientOriginalName() }}</span>
                                            <button type="button" class="button button--ghost button--small" wire:click="removeVideo({{ $index }})">
                                                {{ __('favorite::messages.remove') }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @elseif($currentStep === 2)
                    <div class="stack">
                        @php
                            $aiUser = auth()->user();

                            $aiEntitlement = $aiUser
                                ? app(
                                    \Modules\Listing\Support\AiEntitlement::class
                                )
                                : null;

                            $aiAllowance = $aiEntitlement
                                ? $aiEntitlement->allowance($aiUser)
                                : 0;

                            $aiRemaining = $aiEntitlement
                                ? $aiEntitlement->remaining($aiUser)
                                : 0;
                        @endphp

                        @if($aiEntitlement)
                            <div class="alert">
                                <x-ui.icon name="sparkle"/>

                                <span>
                                    AI scans:
                                    <strong>
                                        {{ $aiRemaining }}
                                        of
                                        {{ $aiAllowance }}
                                    </strong>
                                    remaining
                                </span>
                            </div>
                        @endif

                        @if($detectedError)
                            <div class="alert alert--caution"><x-ui.icon name="sparkle"/><span>{{ $detectedError }}</span></div>
                        @elseif($detectedCategoryId)
                            <div class="alert alert--positive"><x-ui.icon name="sparkle"/><span>{{ $detectedReason }}</span></div>
                        @endif

                        <div class="field">
                            <label class="field__label" for="category-search">{{ __('site::messages.search') }}</label>
                            <span class="input-affix">
                                <x-ui.icon name="search" class="input-affix__icon"/>
                                <input id="category-search" type="search" class="input" wire:model.live.debounce.300ms="categorySearch">
                            </span>
                        </div>

                        @php
                            /*
                             * Work out which top-level category contains
                             * the AI suggested category.
                             */
                            $aiSuggestedCategory = collect($categories)
                                ->firstWhere(
                                    'id',
                                    $detectedCategoryId
                                );

                            $aiSuggestedRootId =
                                $aiSuggestedCategory
                                    ? (
                                        $aiSuggestedCategory['parent_id']
                                            ?: $aiSuggestedCategory['id']
                                    )
                                    : null;
                        @endphp

                        @if($activeParentCategoryId)
                            <div class="row">
                                <button
                                    type="button"
                                    class="button button--ghost button--small"
                                    wire:click="backToRootCategories"
                                >
                                    <x-ui.icon name="arrow-left"/>
                                    <span>
                                        {{ __('site::messages.all_categories') }}
                                    </span>
                                </button>

                                <span class="text-muted">
                                    {{ $this->currentParentName }}
                                </span>
                            </div>

                            <div class="nav-list">
                                @foreach($this->currentCategories as $category)
                                    @php
                                        $isAiSuggested =
                                            $detectedCategoryId ===
                                            $category['id'];

                                        $isSelected =
                                            $selectedCategoryId ===
                                            $category['id'];
                                    @endphp

                                    <button
                                        type="button"
                                        class="
                                            nav-list__item
                                            {{ $isSelected ? 'is-active' : '' }}
                                            {{ $isAiSuggested ? 'is-ai-suggested' : '' }}
                                        "
                                        wire:click="
                                            selectCategory(
                                                {{ $category['id'] }}
                                            )
                                        "
                                    >
                                        <span class="row">
                                            <span>
                                                {{ $category['name'] }}
                                            </span>

                                            @if($isAiSuggested)
                                                <span class="badge">
                                                    AI suggestion
                                                </span>
                                            @endif
                                        </span>

                                        @if($isSelected)
                                            <x-ui.icon name="check"/>
                                        @else
                                            <x-ui.icon name="chevron-right"/>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid--categories">
                                @foreach($this->rootCategories as $category)
                                    @php
                                        $isAiSuggestedRoot =
                                            $aiSuggestedRootId ===
                                            $category['id'];
                                    @endphp

                                    <button
                                        type="button"
                                        class="
                                            category-card
                                            {{ $isAiSuggestedRoot
                                                ? 'is-ai-suggested'
                                                : '' }}
                                        "
                                        wire:click="
                                            enterCategory(
                                                {{ $category['id'] }}
                                            )
                                        "
                                    >
                                        <span class="category-card__icon">
                                            @if(filled($category['icon'] ?? null))
                                                <img
                                                    src="{{ asset(
                                                        $category['icon']
                                                    ) }}"
                                                    alt=""
                                                >
                                            @else
                                                <x-ui.icon name="grid"/>
                                            @endif
                                        </span>

                                        <span class="category-card__name">
                                            {{ $category['name'] }}
                                        </span>

                                        @if($isAiSuggestedRoot)
                                            <span class="category-card__ai">
                                                <x-ui.icon name="sparkle"/>
                                                AI suggestion
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @error('selectedCategoryId')<p class="field__error">{{ $message }}</p>@enderror
                    </div>
                @elseif($currentStep === 3)
                    <div class="stack">
                        <div class="field">
                            <label class="field__label" for="listing-title">{{ __('panel::messages.title') }}</label>
                            <input id="listing-title" type="text" class="input" wire:model.blur="listingTitle" maxlength="150">
                            @error('listingTitle')<p class="field__error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label class="field__label" for="listing-description">{{ __('panel::messages.description') }}</label>
                            <textarea id="listing-description" class="textarea" rows="6" wire:model.blur="description" maxlength="4000"></textarea>
                            @error('description')<p class="field__error">{{ $message }}</p>@enderror
                        </div>

                        @unless($this->isFreeStuff)
                            <div class="field">
                                <label class="field__label" for="listing-price">{{ __('panel::messages.price') }}</label>
                                <input id="listing-price" type="number" step="0.01" min="0.01" class="input" wire:model.blur="price">
                                @error('price')<p class="field__error">{{ $message }}</p>@enderror
                            </div>
                        @endunless

                        <div class="field">
                            <label class="field__label" for="listing-fulfilment">
                                How will the buyer receive the item?
                            </label>
                            <select
                                id="listing-fulfilment"
                                class="select"
                                wire:model.live="fulfilmentMethod"
                            >
                                <option value="">Select pickup or delivery</option>
                                <option value="pickup">Pickup only</option>
                                <option value="delivery">Australia-wide delivery</option>
                                <option value="both">Pickup + Australia-wide delivery</option>
                            </select>
                            <p class="field__hint">
                                Choose pickup if the buyer needs to collect the item from your area.
                            </p>
                            @error('fulfilmentMethod')<p class="field__error">{{ $message }}</p>@enderror
                        </div>

                        @if(in_array($fulfilmentMethod, ['pickup', 'both'], true))
                            <div class="field-set">
                                <p class="field-set__legend">Pickup location</p>

                                <div class="field__row field__row--two">
                                    <div class="field">
                                        <label class="field__label" for="listing-city">City</label>
                                        <select id="listing-city" class="select" wire:model.live="selectedCityId">
                                            <option value="">Select city</option>
                                            @foreach($this->availableCities as $city)
                                                <option value="{{ $city['id'] }}">{{ $city['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('selectedCityId')<p class="field__error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field">
                                        <label class="field__label" for="listing-suburb">Suburb / Area</label>
                                        <select
                                            id="listing-suburb"
                                            class="select"
                                            wire:model.live="selectedDistrictId"
                                            @disabled($this->availableDistricts === [])
                                        >
                                            <option value="">Select suburb / area</option>
                                            @foreach($this->availableDistricts as $district)
                                                <option value="{{ $district['id'] }}">{{ $district['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('selectedDistrictId')<p class="field__error">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <p class="field__hint">
                                    Only your suburb / area is shown publicly — not your street address.
                                </p>
                            </div>
                        @endif

                        <div class="field">
                            <label class="field__label" for="listing-quantity">Quantity</label>
                            <input
                                id="listing-quantity"
                                type="number"
                                min="1"
                                max="1000000"
                                step="1"
                                class="input"
                                wire:model.blur="quantity"
                            >
                            <p class="field__hint">How many of this item do you have available?</p>
                            @error('quantity')<p class="field__error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field-set">
                            <p class="field-set__legend">Size &amp; weight <span class="text-muted">(optional)</span></p>

                            <div class="field__row field__row--three">
                                <div class="field">
                                    <label class="field__label" for="listing-width">Width</label>
                                    <input
                                        id="listing-width"
                                        type="number"
                                        inputmode="decimal"
                                        step="0.01"
                                        min="0.01"
                                        class="input"
                                        wire:model.blur="width"
                                        placeholder="e.g. 85"
                                    >
                                    @error('width')<p class="field__error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label class="field__label" for="listing-height">Height</label>
                                    <input
                                        id="listing-height"
                                        type="number"
                                        inputmode="decimal"
                                        step="0.01"
                                        min="0.01"
                                        class="input"
                                        wire:model.blur="height"
                                        placeholder="e.g. 120"
                                    >
                                    @error('height')<p class="field__error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label class="field__label" for="listing-depth">Depth</label>
                                    <input
                                        id="listing-depth"
                                        type="number"
                                        inputmode="decimal"
                                        step="0.01"
                                        min="0.01"
                                        class="input"
                                        wire:model.blur="depth"
                                        placeholder="e.g. 4"
                                    >
                                    @error('depth')<p class="field__error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="field__row field__row--three">
                                <div class="field">
                                    <label class="field__label" for="listing-dimension-unit">Dimension unit</label>
                                    <select
                                        id="listing-dimension-unit"
                                        class="select"
                                        wire:model="dimensionUnit"
                                    >
                                        <option value="mm">mm</option>
                                        <option value="cm">cm</option>
                                        <option value="m">m</option>
                                    </select>
                                    @error('dimensionUnit')<p class="field__error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label class="field__label" for="listing-weight">Weight</label>
                                    <input
                                        id="listing-weight"
                                        type="number"
                                        inputmode="decimal"
                                        step="0.1"
                                        min="0.1"
                                        class="input"
                                        wire:model.blur="weight"
                                        placeholder="e.g. 6.5"
                                    >
                                    @error('weight')<p class="field__error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label class="field__label" for="listing-weight-unit">Weight unit</label>
                                    <select
                                        id="listing-weight-unit"
                                        class="select"
                                        wire:model="weightUnit"
                                    >
                                        <option value="g">g</option>
                                        <option value="kg">kg</option>
                                    </select>
                                    @error('weightUnit')<p class="field__error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <p class="field__hint">
                                Useful for furniture, artwork, mirrors, appliances and other bulky items.
                            </p>
                        </div>

                        @if($listingCustomFields !== [])
                            <div class="field-set">
                                <p class="field-set__legend">{{ __('site::messages.details') }}</p>
                                @foreach($listingCustomFields as $field)
                                    <div class="field">
                                        <label class="field__label" for="custom-{{ $field['name'] }}">{{ $field['label'] }}</label>
                                        @if(($field['type'] ?? 'text') === 'select')
                                            <select id="custom-{{ $field['name'] }}" class="select" wire:model="customFieldValues.{{ $field['name'] }}">
                                                <option value="">—</option>
                                                @foreach($field['options'] ?? [] as $option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @elseif(($field['type'] ?? 'text') === 'boolean')
                                            <label class="checkbox">
                                                <input type="checkbox" wire:model="customFieldValues.{{ $field['name'] }}">
                                                <span>{{ $field['label'] }}</span>
                                            </label>
                                        @elseif(($field['type'] ?? 'text') === 'textarea')
                                            <textarea id="custom-{{ $field['name'] }}" class="textarea" rows="3" wire:model="customFieldValues.{{ $field['name'] }}"></textarea>
                                        @else
                                            <input
                                                id="custom-{{ $field['name'] }}"
                                                type="{{ ($field['type'] ?? 'text') === 'number' ? 'number' : 'text' }}"
                                                class="input"
                                                wire:model="customFieldValues.{{ $field['name'] }}"
                                            >
                                        @endif
                                        @if(filled($field['help_text'] ?? null))
                                            <p class="field__hint">{{ $field['help_text'] }}</p>
                                        @endif
                                        @error('customFieldValues.'.$field['name'])<p class="field__error">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="stack">
                        <dl class="spec-list">
                            <div class="spec-list__row">
                                <dt class="spec-list__label">{{ __('panel::messages.title') }}</dt>
                                <dd class="spec-list__value">{{ $listingTitle }}</dd>
                            </div>
                            <div class="spec-list__row">
                                <dt class="spec-list__label">{{ __('site::messages.category') }}</dt>
                                <dd class="spec-list__value">{{ $this->selectedCategoryPath }}</dd>
                            </div>
                            <div class="spec-list__row">
                                <dt class="spec-list__label">{{ __('panel::messages.price') }}</dt>
                                <dd class="spec-list__value">{{ $price }}</dd>
                            </div>
                            <div class="spec-list__row">
                                <dt class="spec-list__label">Quantity</dt>
                                <dd class="spec-list__value">{{ $quantity }}</dd>
                            </div>
                            @if(in_array($fulfilmentMethod, ['pickup', 'both'], true))
                                <div class="spec-list__row">
                                    <dt class="spec-list__label">Pickup</dt>
                                    <dd class="spec-list__value">
                                        {{ collect([$this->selectedDistrictName, $this->selectedCityName])->filter()->implode(', ') }}
                                    </dd>
                                </div>
                            @endif

                            @if(in_array($fulfilmentMethod, ['delivery', 'both'], true))
                                <div class="spec-list__row">
                                    <dt class="spec-list__label">Delivery</dt>
                                    <dd class="spec-list__value">Australia-wide</dd>
                                </div>
                            @endif
                        </dl>

                        @if(filled($description))
                            <div class="prose">{{ $description }}</div>
                        @endif

                        @if($photos !== [])
                            <div class="upload__grid">
                                @foreach($photos as $photo)
                                    <figure class="upload__preview"><img src="{{ $photo->temporaryUrl() }}" alt=""></figure>
                                @endforeach
                            </div>
                        @endif

                        @if($videos !== [])
                            <div class="stack stack--tight">
                                <p class="field-set__legend">
                                    Videos
                                </p>

                                @foreach($videos as $video)
                                    <div class="row row--between">
                                        <span class="row">
                                            <x-ui.icon name="video"/>
                                            <span class="text-body text-clamp-1">
                                                {{ $video->getClientOriginalName() }}
                                            </span>
                                        </span>

                                        <span class="badge badge--positive">
                                            Attached
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="card__foot">
                <div class="row row--between">
                    @if($currentStep > 1)
                        <button type="button" class="button button--ghost" wire:click="goToStep({{ $currentStep - 1 }})">
                            <x-ui.icon name="arrow-left"/>
                            <span>{{ __('site::messages.back') }}</span>
                        </button>
                    @else
                        <a href="{{ route('panel.listings.index') }}" class="button button--ghost">{{ __('panel::messages.cancel') }}</a>
                    @endif

                    @if($currentStep === 1)
                        <button type="button" class="button button--primary" wire:click="goToCategoryStep">{{ __('site::messages.apply') }}</button>
                    @elseif($currentStep === 2)
                        <button type="button" class="button button--primary" wire:click="goToDetailsStep">{{ __('site::messages.apply') }}</button>
                    @elseif($currentStep === 3)
                        <button type="button" class="button button--primary" wire:click="goToPreviewStep">{{ __('site::messages.apply') }}</button>
                    @else
                        <button type="button" class="button button--primary button--large" wire:click="publishListing" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="publishListing">{{ __('site::messages.post_listing_cta') }}</span>
                            <span wire:loading wire:target="publishListing">{{ __('panel::messages.publishing') }}</span>
                        </button>
                    @endif
                </div>
            </div>
        </section>
    </div>
</div>

<script>
(() => {
    const enableUploadChoices = () => {
        document.querySelectorAll('[data-upload-choice-guard]').forEach((element) => {
            window.setTimeout(() => {
                element.style.pointerEvents = '';
            }, 650);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enableUploadChoices, { once: true });
    } else {
        enableUploadChoices();
    }

    document.addEventListener('livewire:navigated', enableUploadChoices);
})();
</script>
