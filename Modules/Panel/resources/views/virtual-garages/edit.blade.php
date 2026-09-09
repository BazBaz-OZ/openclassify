@extends('panel::layouts.panel', ['panelSection' => 'virtual-garages'])

@section('title', $garage->title)

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">{{ $garage->title }}</h1>

        <p class="text-muted">
            Build and manage your Virtual Garage.
        </p>
    </div>

    <span class="badge">
        {{ ucfirst($garage->status) }}
    </span>
</header>

<section class="card">
    <div class="card__head">
        <div>
            <h2 class="card__title">Add items from photos</h2>

            <p class="text-muted">
                Upload photos of items in your garage. These photos will
                become the starting point for creating listings.
            </p>
        </div>

        @if($garage->photos->isNotEmpty())
            <span class="badge">
                {{ $garage->photos->count() }}
                {{ \Illuminate\Support\Str::plural(
                    'photo',
                    $garage->photos->count()
                ) }}
            </span>
        @endif
    </div>

    <div class="card__body stack stack--loose">

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

                @if($aiRemaining <= 0)
                    @if(\Illuminate\Support\Facades\Route::has('membership'))
                        <a
                            href="{{ route('membership') }}"
                            class="button button--small"
                        >
                            View membership options
                        </a>
                    @else
                        <span class="text-muted">
                            Upgrade for more AI scans.
                        </span>
                    @endif
                @endif
            </div>
        @endif

        <form
            method="POST"
            action="{{ route(
                'panel.virtual-garages.photos.store',
                $garage
            ) }}"
            enctype="multipart/form-data"
            class="stack"
            onsubmit="
                const button = this.querySelector(
                    '[data-garage-upload-button]'
                );

                if (button) {
                    button.disabled = true;
                    button.innerHTML =
                        'Uploading & analysing...';
                }
            "
        >
            @csrf

            <div class="field">
                <label
                    class="field__label"
                    for="garage-photos"
                >
                    Garage photos
                </label>

                <input
                    id="garage-photos"
                    name="photos[]"
                    type="file"
                    class="input"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    required
                >

                <p class="field__hint">
                    Select several photos at once. JPG, PNG or WebP,
                    maximum 10 MB per photo.
                </p>

                @error('photos')
                    <p class="field__error">
                        {{ $message }}
                    </p>
                @enderror

                @error('photos.*')
                    <p class="field__error">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <button
                    type="submit"
                    class="button button--primary"
                    data-garage-upload-button
                >
                    <x-ui.icon name="plus"/>
                    <span>Upload Garage Photos</span>
                </button>
            </div>
        </form>

        @if($garage->photos->isNotEmpty())
            <div
                style="
                    display:grid;
                    grid-template-columns:
                        repeat(auto-fill,minmax(180px,1fr));
                    gap:var(--space-4);
                "
            >
                @foreach($garage->photos as $photo)
                    <article class="card">
                        <div
                            style="
                                aspect-ratio:4/3;
                                overflow:hidden;
                                background:var(--color-surface-muted);
                            "
                        >
                            <img
                                src="{{ $photo->url() }}"
                                alt="Garage intake photo"
                                loading="lazy"
                                style="
                                    width:100%;
                                    height:100%;
                                    object-fit:cover;
                                    display:block;
                                "
                            >
                        </div>

                        <div class="card__body stack stack--tight">
                            <div class="row row--between row--wrap">
                                <span class="badge">
                                    {{ ucfirst($photo->status) }}
                                </span>

                                @if($photo->size)
                                    <span class="text-muted">
                                        {{ number_format(
                                            $photo->size / 1048576,
                                            1
                                        ) }} MB
                                    </span>
                                @endif
                            </div>

                            @if(filled($photo->original_name))
                                <p
                                    class="text-muted text-clamp-1"
                                    title="{{ $photo->original_name }}"
                                >
                                    {{ $photo->original_name }}
                                </p>
                            @endif

                            @if(
                                in_array(
                                    $photo->status,
                                    [
                                        \Modules\Listing\Models\VirtualGaragePhoto::STATUS_PENDING,
                                        \Modules\Listing\Models\VirtualGaragePhoto::STATUS_PROCESSED,
                                    ],
                                    true
                                )
                            )
                                <form
                                    method="POST"
                                    action="{{ route(
                                        'panel.virtual-garages.photos.analyze',
                                        [
                                            'virtualGarage' => $garage,
                                            'photo' => $photo,
                                        ]
                                    ) }}"
                                    onsubmit="
                                        const b = this.querySelector('button');
                                        b.disabled = true;
                                        b.textContent = 'Analysing...';
                                    "
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="button button--primary button--small"
                                    >
                                        {{
                                            $photo->status ===
                                            \Modules\Listing\Models\VirtualGaragePhoto::STATUS_PROCESSED
                                                ? 'Retry AI'
                                                : 'Analyse with AI'
                                        }}
                                    </button>
                                </form>
                            @else
                                <p class="text-muted">
                                    {{ $photo->items->count() }}
                                    {{ \Illuminate\Support\Str::plural(
                                        'item',
                                        $photo->items->count()
                                    ) }}
                                    detected
                                </p>
                            @endif

                            <form
                                method="POST"
                                action="{{ route(
                                    'panel.virtual-garages.photos.destroy',
                                    [
                                        'virtualGarage' => $garage,
                                        'photo' => $photo,
                                    ]
                                ) }}"
                                onsubmit="return confirm('Remove this garage photo?');"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="button button--ghost button--small"
                                >
                                    Delete
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="text-muted">
                No garage photos uploaded yet.
            </div>
        @endif

    </div>
</section>

<section class="card">
    <div class="card__head">
        <div>
            <h2 class="card__title">
                AI detected items
            </h2>

            <p class="text-muted">
                Review what Sell My Junk found in your photos.
                Nothing is published yet.
            </p>
        </div>

        @if($garage->items->isNotEmpty())
            <span class="badge">
                {{ $garage->items->count() }}
                {{ \Illuminate\Support\Str::plural(
                    'item',
                    $garage->items->count()
                ) }}
            </span>
        @endif
    </div>

    <div class="card__body">

        @if($garage->items->isNotEmpty())

            <div
                style="
                    display:grid;
                    grid-template-columns:
                        repeat(auto-fill,minmax(280px,1fr));
                    gap:var(--space-4);
                "
            >

                @foreach($garage->items as $item)

                    <article class="card">

                        {{-- VG_PREVIEW_INDEPENDENT_OF_SOURCE --}}
                        @php
                            $previewImageUrl =
                                $item->previewImageUrl();
                        @endphp

                        @if($previewImageUrl)
                            <div
                                style="
                                    aspect-ratio:4/3;
                                    overflow:hidden;
                                    background:var(--color-surface-muted);
                                "
                            >
                                <img
                                    src="{{ $previewImageUrl }}"
                                    alt="{{ $item->title }}"
                                    loading="lazy"
                                    style="
                                        width:100%;
                                        height:100%;
                                        object-fit:cover;
                                        display:block;
                                    "
                                >
                            </div>
                        @endif

                        @if($item->photo)
                            @php
                                $photoCrop =
                                    $item->initialPhotoCrop();
                            @endphp

                            <div
                                class="card__body stack stack--tight"
                                style="
                                    padding-top:var(--space-3);
                                    border-top:1px solid var(--color-border);
                                "
                            >
                                <form
                                    method="POST"
                                    action="{{ route('panel.virtual-garages.items.photo', ['virtualGarage' => $garage, 'item' => $item]) }}"
                                    class="stack stack--tight js-vg-photo-editor"
                                    data-source-url="{{ $item->photo->url() }}"
                                    data-initial-center-x="{{ $photoCrop['center_x'] }}"
                                    data-initial-center-y="{{ $photoCrop['center_y'] }}"
                                    data-initial-zoom="{{ $photoCrop['zoom'] }}"
                                    data-initial-rotation="{{ data_get($item->ai_data, 'manual_crop.rotation', 0) }}"
                                >
                                    @csrf
                                    @method('PUT')

                                    <input
                                        type="hidden"
                                        name="crop_center_x"
                                        value="{{ $photoCrop['center_x'] }}"
                                        data-vg-input="center_x"
                                    >

                                    <input
                                        type="hidden"
                                        name="crop_center_y"
                                        value="{{ $photoCrop['center_y'] }}"
                                        data-vg-input="center_y"
                                    >

                                    <input
                                        type="hidden"
                                        name="crop_zoom"
                                        value="{{ $photoCrop['zoom'] }}"
                                        data-vg-input="zoom"
                                    >

                                    <input
                                        type="hidden"
                                        name="crop_rotation"
                                        value="{{ data_get($item->ai_data, 'manual_crop.rotation', 0) }}"
                                        data-vg-input="rotation"
                                    >

                                    <input
                                        type="hidden"
                                        name="cropped_image"
                                        value=""
                                        data-vg-input="cropped_image"
                                    >

                                    <div
                                        class="row row--wrap"
                                        style="gap:var(--space-2);"
                                    >
                                        <button
                                            type="button"
                                            class="button button--secondary button--small"
                                            data-vg-toggle
                                        >
                                            {{ $item->hasManualCrop() ? 'Adjust photo again' : 'Adjust photo' }}
                                        </button>

                                        @if($item->hasManualCrop())
                                            <span class="badge">
                                                Photo adjusted
                                            </span>
                                        @endif

                                        <button
                                            type="submit"
                                            class="button button--ghost button--small"
                                            name="photo_action"
                                            value="use_original"
                                        >
                                            Use original
                                        </button>
                                    </div>

                                    <div
                                        data-vg-panel
                                        hidden
                                        class="stack stack--tight"
                                    >
                                        <p class="text-muted">
                                            Drag with one finger. Pinch with two fingers to zoom, and twist with two fingers to rotate the photo.
                                        </p>

                                        <div class="vg-photo-editor__viewport" data-vg-viewport>
                                            <img
                                                src="{{ $item->photo->url() }}"
                                                alt="{{ $item->title }}"
                                                draggable="false"
                                                data-vg-image
                                            >
                                        </div>

                                        <label
                                            class="stack stack--tight"
                                            style="gap:var(--space-1);"
                                        >
                                            <span>Zoom</span>

                                            <input
                                                type="range"
                                                min="1"
                                                max="4"
                                                step="0.01"
                                                value="{{ $photoCrop['zoom'] }}"
                                                data-vg-zoom
                                            >
                                        </label>

                                        <label
                                            class="stack stack--tight"
                                            style="gap:var(--space-1);"
                                        >
                                            <span>
                                                Rotation:
                                                <strong data-vg-rotation-value>
                                                    {{ round((float) data_get($item->ai_data, 'manual_crop.rotation', 0)) }}°
                                                </strong>
                                            </span>

                                            <input
                                                type="range"
                                                min="-180"
                                                max="180"
                                                step="1"
                                                value="{{ data_get($item->ai_data, 'manual_crop.rotation', 0) }}"
                                                data-vg-rotation
                                            >
                                        </label>

                                        <div
                                            class="row row--wrap"
                                            style="gap:var(--space-2);"
                                        >
                                            <button
                                                type="button"
                                                class="button button--secondary button--small"
                                                data-vg-reset
                                            >
                                                Reset suggested position
                                            </button>

                                            <button
                                                type="submit"
                                                class="button button--primary button--small"
                                                name="photo_action"
                                                value="save_crop"
                                            >
                                                Save photo
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endif

                        <div class="card__body stack stack--tight">

                            @php
                                $historicalListing =
                                    $item->historicalListing;

                                $listingRemoved =
                                    $item->listing_id !== null
                                    && $historicalListing !== null
                                    && $historicalListing->trashed();

                                $listingUnavailable =
                                    $item->listing_id !== null
                                    && $historicalListing === null;

                                $itemStatusLabel =
                                    match ($item->status) {
                                        \Modules\Listing\Models\VirtualGarageItem::STATUS_PUBLISHED =>
                                            $listingRemoved
                                                ? 'LISTING REMOVED'
                                                : (
                                                    $listingUnavailable
                                                        ? 'LISTING UNAVAILABLE'
                                                        : 'PUBLISHED'
                                                ),

                                        \Modules\Listing\Models\VirtualGarageItem::STATUS_DRAFT =>
                                            'AI DRAFT',

                                        default =>
                                            strtoupper(
                                                (string) $item->status
                                            ),
                                    };
                            @endphp

                            <div class="row row--between row--wrap">
                                <div class="row row--wrap">
                                    <span class="badge">
                                        {{ $itemStatusLabel }}
                                    </span>

                                    @if(app()->environment('local'))
                                        <span class="text-muted">
                                            #{{ $item->getKey() }}
                                        </span>
                                    @endif
                                </div>

                                @if($item->confidence !== null)
                                    <span class="text-muted">
                                        {{ round(
                                            $item->confidence * 100
                                        ) }}% confidence
                                    </span>
                                @endif
                            </div>

                            <h3 class="card__title">
                                {{ $item->title }}
                            </h3>

                            @if(
                                $item->status ===
                                    \Modules\Listing\Models\VirtualGarageItem::STATUS_PUBLISHED
                                && $item->listing_id !== null
                            )
                                @if(
                                    $historicalListing !== null
                                    && !$historicalListing->trashed()
                                )
                                    <p>
                                        <strong>Listing:</strong>

                                        <a
                                            href="{{ route(
                                                'listings.show',
                                                $historicalListing
                                            ) }}"
                                        >
                                            View listing
                                        </a>

                                        <span class="text-muted">
                                            ({{ ucfirst(
                                                $historicalListing->statusValue()
                                            ) }})
                                        </span>
                                    </p>

                                @elseif($listingRemoved)
                                    <p class="text-muted">
                                        <strong>Listing:</strong>
                                        Removed by seller
                                    </p>

                                @elseif($listingUnavailable)
                                    <p class="text-muted">
                                        <strong>Listing:</strong>
                                        Unavailable
                                    </p>
                                @endif
                            @endif

                            @if($item->category)
                                <p>
                                    <strong>Category:</strong>
                                    {{ $item->category->name }}
                                </p>
                            @endif

                            @if($item->suggested_price !== null)
                                <p>
                                    <strong>AI suggested price:</strong>
                                    ${{ number_format(
                                        (float) $item->suggested_price,
                                        2
                                    ) }}
                                </p>
                            @endif

                            <p>
                                <strong>Your price:</strong>

                                @if($item->price !== null)
                                    ${{ number_format(
                                        (float) $item->price,
                                        2
                                    ) }}
                                @else
                                    Not set
                                @endif
                            </p>

                            @if(filled($item->condition))
                                <p>
                                    <strong>Condition:</strong>
                                    {{ $item->condition }}
                                </p>
                            @endif

                            @if(filled($item->description))
                                <p class="text-muted">
                                    {{ $item->description }}
                                </p>
                            @endif

                            @php
                                $duplicate =
                                    data_get(
                                        $item->ai_data,
                                        'duplicate'
                                    );

                                $hasDuplicate =
                                    is_array($duplicate)
                                    && filled(
                                        $duplicate['item_id']
                                        ?? null
                                    );
                            @endphp

                            @if($hasDuplicate)
                                <div
                                    class="stack stack--tight"
                                    style="
                                        padding:var(--space-3);
                                        border:1px solid var(--color-border);
                                        border-radius:var(--radius-md);
                                        background:var(--color-surface-muted);
                                    "
                                >
                                    <strong>
                                        Possible duplicate
                                    </strong>

                                    <p class="text-muted">
                                        SMJ detected this item earlier as
                                        “{{ $duplicate['title'] ?? 'another item' }}”.
                                    </p>

                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'panel.virtual-garages.items.duplicate.keep',
                                            [
                                                'virtualGarage' => $garage,
                                                'item' => $item,
                                            ]
                                        ) }}"
                                    >
                                        @csrf

                                        <button
                                            type="submit"
                                            class="button button--secondary button--small"
                                        >
                                            Keep anyway
                                        </button>
                                    </form>
                                </div>
                            @endif

                            <div
                                class="row row--wrap"
                                style="gap:var(--space-2);"
                            >
                                <details>
                                    <summary
                                        class="button button--secondary button--small"
                                        style="cursor:pointer;"
                                    >
                                        Edit item
                                    </summary>

                                <form
                                    method="POST"
                                    action="{{ route(
                                        'panel.virtual-garages.items.update',
                                        [
                                            'virtualGarage' => $garage,
                                            'item' => $item,
                                        ]
                                    ) }}"
                                    class="stack"
                                    style="margin-top:var(--space-4);"
                                >
                                    @csrf
                                    @method('PUT')

                                    <div class="field">
                                        <label class="field__label">
                                            Item name
                                        </label>

                                        <input
                                            type="text"
                                            name="title"
                                            class="input"
                                            maxlength="150"
                                            required
                                            value="{{ $item->title }}"
                                        >
                                    </div>

                                    <div class="field">
                                        <label class="field__label">
                                            Category
                                        </label>

                                        <select
                                            name="category_id"
                                            class="input"
                                        >
                                            <option value="">
                                                Choose category
                                            </option>

                                            @foreach($categories as $category)
                                                <option
                                                    value="{{ $category->getKey() }}"
                                                    @selected(
                                                        (int) $item->category_id
                                                        ===
                                                        (int) $category->getKey()
                                                    )
                                                >
                                                    {{ $category->name }}
                                                </option>
                                            @endforeach

                                        </select>
                                    </div>

                                    <div class="field">
                                        <label class="field__label">
                                            Price
                                        </label>

                                        <span class="input-affix">
                                            <span>$</span>

                                            <input
                                                type="number"
                                                name="price"
                                                class="input"
                                                min="0"
                                                step="0.01"
                                                value="{{ $item->price }}"
                                            >
                                        </span>

                                        @if($item->suggested_price !== null)
                                            <p class="field__hint">
                                                AI suggestion:
                                                ${{ number_format(
                                                    (float)
                                                    $item->suggested_price,
                                                    2
                                                ) }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="field">
                                        <label class="field__label">
                                            Condition
                                        </label>

                                        <input
                                            type="text"
                                            name="condition"
                                            class="input"
                                            maxlength="100"
                                            value="{{ $item->condition }}"
                                        >
                                    </div>

                                    <div class="field">
                                        <label class="field__label">
                                            Description
                                        </label>

                                        <textarea
                                            name="description"
                                            class="textarea"
                                            rows="4"
                                            maxlength="4000"
                                        >{{ $item->description }}</textarea>
                                    </div>

                                    <button
                                        type="submit"
                                        class="button button--primary button--small"
                                    >
                                        Save item
                                    </button>

                                </form>
                                </details>

                                <form
                                    method="POST"
                                    action="{{ route(
                                        'panel.virtual-garages.items.skip',
                                        [
                                            'virtualGarage' => $garage,
                                            'item' => $item,
                                        ]
                                    ) }}"
                                    onsubmit="return confirm(@js($hasDuplicate ? 'Skip this possible duplicate?' : 'Remove this item from the Virtual Garage?'));"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="button button--ghost button--small"
                                    >
                                        {{ $hasDuplicate ? 'Skip duplicate' : 'Remove item' }}
                                    </button>
                                </form>
                            </div>

                        </div>

                    </article>

                @endforeach

            </div>

        @else

            <div class="text-muted">
                No AI-detected items yet.
                Upload a photo or analyse a pending photo.
            </div>

        @endif

    </div>
</section>

<section class="card">
    <div class="card__head">
        <h2 class="card__title">Virtual Garage details</h2>
    </div>

    <div class="card__body">

        <form
            id="virtual-garage-form"
            method="POST"
            action="{{ route('panel.virtual-garages.update', $garage) }}"
            class="stack stack--loose"
        >
            @csrf
            @method('PUT')

            <div class="field">
                <label class="field__label" for="title">
                    Garage name
                </label>

                <input
                    id="title"
                    name="title"
                    type="text"
                    class="input"
                    maxlength="150"
                    required
                    value="{{ old('title', $garage->title) }}"
                >
            </div>

            <div class="field">
                <label class="field__label" for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    class="textarea"
                    rows="5"
                    maxlength="4000"
                >{{ old('description', $garage->description) }}</textarea>
            </div>

            <div class="field__row field__row--three">
                @include(
                    'panel::virtual-garages.partials.location-fields',
                    [
                        'garageSuburb' =>
                            $garage->city ?? '',
                        'garageCountry' =>
                            $garage->country ?? 'Australia',
                    ]
                )
            </div>

            <section class="card">
                <div class="card__head">
                    <div>
                        <h3 class="card__title">
                            Buy everything remaining
                        </h3>

                        <p class="text-muted">
                            Optionally set a price for all unsold items together.
                        </p>
                    </div>
                </div>

                <div class="card__body stack">

                    <div class="field">
                        <label
                            class="field__label"
                            for="bulk_price"
                        >
                            Take-the-lot price
                        </label>

                        <span class="input-affix">
                            <input
                                id="bulk_price"
                                name="bulk_price"
                                type="number"
                                min="1"
                                step="0.01"
                                class="input"
                                value="{{ old('bulk_price', $garage->bulk_price) }}"
                                placeholder="Optional"
                            >

                            <span class="input-affix__suffix">
                                AUD
                            </span>
                        </span>
                    </div>

                    <label class="row">
                        <input
                            type="checkbox"
                            name="allow_bulk_offers"
                            value="1"
                            @checked(
                                old(
                                    'allow_bulk_offers',
                                    $garage->allow_bulk_offers
                                )
                            )
                        >

                        <span>
                            Allow buyers to make an offer for everything remaining
                        </span>
                    </label>

                </div>
            </section>

            @error('virtual_garage')
                <p class="field__error">
                    {{ $message }}
                </p>
            @enderror

            <div class="row row--between">

                <a
                    href="{{ route('panel.virtual-garages.index') }}"
                    class="button button--ghost"
                >
                    Back
                </a>

                <div class="row row--wrap">

                    @php
                        $unresolvedDuplicateCount =
                            $garage->items
                                ->filter(
                                    function ($garageItem) {
                                        $duplicate =
                                            data_get(
                                                $garageItem->ai_data,
                                                'duplicate'
                                            );

                                        return
                                            $garageItem->status ===
                                                \Modules\Listing\Models\VirtualGarageItem::STATUS_DRAFT
                                            && $garageItem->listing_id === null
                                            && is_array($duplicate)
                                            && filled(
                                                $duplicate['item_id']
                                                ?? null
                                            );
                                    }
                                )
                                ->count();
                    @endphp

                    <button
                        type="submit"
                        name="action"
                        value="save"
                        class="button button--secondary"
                    >
                        Save Virtual Garage
                    </button>

                    @if(
                        in_array(
                            $garage->status,
                            [
                                \Modules\Listing\Models\VirtualGarage::STATUS_DRAFT,
                                \Modules\Listing\Models\VirtualGarage::STATUS_ACTIVE,
                            ],
                            true
                        )
                    )
                        <button
                            type="submit"
                            name="action"
                            value="publish"
                            class="button button--primary"
                        >
                            {{
                                $garage->status ===
                                \Modules\Listing\Models\VirtualGarage::STATUS_ACTIVE
                                    ? 'Publish new items'
                                    : 'Save & Publish'
                            }}
                        </button>
                    @endif

                </div>

            </div>

        </form>

        @if($unresolvedDuplicateCount > 0)
            <div
                class="row row--wrap"
                style="margin-top:var(--space-3);"
            >
                <form
                    method="POST"
                    action="{{ route(
                        'panel.virtual-garages.duplicates.skip',
                        $garage
                    ) }}"
                    onsubmit="return confirm(
                        'Skip all '
                        + {{ $unresolvedDuplicateCount }}
                        + ' possible duplicates?'
                    );"
                >
                    @csrf

                    <button
                        type="submit"
                        class="button button--secondary"
                    >
                        Skip all duplicates
                        ({{ $unresolvedDuplicateCount }})
                    </button>
                </form>
            </div>
        @endif
    </div>
</section>

@if(
    $garage->status ===
    \Modules\Listing\Models\VirtualGarage::STATUS_ACTIVE
)
    <div class="row row--wrap">

        <form
            method="POST"
            action="{{ route('panel.virtual-garages.complete', $garage) }}"
        >
            @csrf

            <button
                type="submit"
                class="button button--secondary"
            >
                End Virtual Garage
            </button>
        </form>

    </div>
@endif

<style>
    .vg-photo-editor__viewport {
        position: relative;
        width: 100%;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        border-radius: 12px;
        border: 1px solid var(--color-border);
        background: #111827;
        touch-action: none;
        user-select: none;
    }

    .vg-photo-editor__viewport img {
        position: absolute;
        top: 0;
        left: 0;
        max-width: none;
        width: auto;
        height: auto;
        transform-origin: top left;
        cursor: grab;
        user-select: none;
        -webkit-user-drag: none;
    }

    .vg-photo-editor__viewport.is-dragging img {
        cursor: grabbing;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const clamp = (value, min, max) =>
        Math.min(max, Math.max(min, value));

    const normaliseAngle = (angle) => {
        while (angle > 180) angle -= 360;
        while (angle < -180) angle += 360;
        return angle;
    };

    document.querySelectorAll(
        '.js-vg-photo-editor'
    ).forEach(function (editor) {
        const panel =
            editor.querySelector('[data-vg-panel]');

        const toggle =
            editor.querySelector('[data-vg-toggle]');

        const resetButton =
            editor.querySelector('[data-vg-reset]');

        const viewport =
            editor.querySelector('[data-vg-viewport]');

        const image =
            editor.querySelector('[data-vg-image]');

        const zoomInput =
            editor.querySelector('[data-vg-zoom]');

        const rotationInput =
            editor.querySelector('[data-vg-rotation]');

        const rotationValue =
            editor.querySelector(
                '[data-vg-rotation-value]'
            );

        const inputCenterX =
            editor.querySelector(
                '[data-vg-input="center_x"]'
            );

        const inputCenterY =
            editor.querySelector(
                '[data-vg-input="center_y"]'
            );

        const inputZoom =
            editor.querySelector(
                '[data-vg-input="zoom"]'
            );

        const inputRotation =
            editor.querySelector(
                '[data-vg-input="rotation"]'
            );

        const inputCroppedImage =
            editor.querySelector(
                '[data-vg-input="cropped_image"]'
            );

        const initialState = {
            centerX:
                parseFloat(
                    editor.dataset.initialCenterX
                    || '0.5'
                ),

            centerY:
                parseFloat(
                    editor.dataset.initialCenterY
                    || '0.5'
                ),

            zoom:
                parseFloat(
                    editor.dataset.initialZoom
                    || '1'
                ),

            rotation:
                parseFloat(
                    editor.dataset.initialRotation
                    || '0'
                )
        };

        let state = {
            ...initialState
        };

        let naturalWidth = 0;
        let naturalHeight = 0;

        const pointers = new Map();

        let oneFingerStart = null;
        let twoFingerStart = null;

        const radians = () =>
            state.rotation * Math.PI / 180;

        const geometry = (
            width,
            height,
            requestedState = state
        ) => {
            const r =
                requestedState.rotation
                * Math.PI / 180;

            const cos = Math.cos(r);
            const sin = Math.sin(r);

            const requiredScale = Math.max(
                (
                    Math.abs(cos) * width
                    + Math.abs(sin) * height
                ) / naturalWidth,

                (
                    Math.abs(sin) * width
                    + Math.abs(cos) * height
                ) / naturalHeight
            );

            const scale =
                requiredScale
                * requestedState.zoom;

            return {
                cos,
                sin,
                scale
            };
        };

        const clampCentre = (
            width,
            height
        ) => {
            if (!naturalWidth || !naturalHeight) {
                return;
            }

            const g =
                geometry(width, height);

            const halfSourceX =
                (
                    Math.abs(g.cos) * width / 2
                    + Math.abs(g.sin) * height / 2
                ) / g.scale;

            const halfSourceY =
                (
                    Math.abs(g.sin) * width / 2
                    + Math.abs(g.cos) * height / 2
                ) / g.scale;

            const cx =
                state.centerX * naturalWidth;

            const cy =
                state.centerY * naturalHeight;

            const minX =
                Math.min(
                    naturalWidth / 2,
                    halfSourceX
                );

            const maxX =
                Math.max(
                    naturalWidth / 2,
                    naturalWidth - halfSourceX
                );

            const minY =
                Math.min(
                    naturalHeight / 2,
                    halfSourceY
                );

            const maxY =
                Math.max(
                    naturalHeight / 2,
                    naturalHeight - halfSourceY
                );

            state.centerX =
                clamp(cx, minX, maxX)
                / naturalWidth;

            state.centerY =
                clamp(cy, minY, maxY)
                / naturalHeight;
        };

        const syncInputs = () => {
            inputCenterX.value =
                state.centerX.toFixed(5);

            inputCenterY.value =
                state.centerY.toFixed(5);

            inputZoom.value =
                state.zoom.toFixed(3);

            inputRotation.value =
                state.rotation.toFixed(2);

            zoomInput.value =
                state.zoom.toFixed(2);

            rotationInput.value =
                state.rotation.toFixed(1);

            rotationValue.textContent =
                Math.round(state.rotation)
                + '°';
        };

        const render = () => {
            if (
                !naturalWidth
                || !naturalHeight
            ) {
                return;
            }

            const rect =
                viewport.getBoundingClientRect();

            clampCentre(
                rect.width,
                rect.height
            );

            const g =
                geometry(
                    rect.width,
                    rect.height
                );

            const centerSourceX =
                state.centerX
                * naturalWidth;

            const centerSourceY =
                state.centerY
                * naturalHeight;

            const a =
                g.scale * g.cos;

            const b =
                g.scale * g.sin;

            const c =
                -g.scale * g.sin;

            const d =
                g.scale * g.cos;

            const e =
                rect.width / 2
                - (
                    a * centerSourceX
                    + c * centerSourceY
                );

            const f =
                rect.height / 2
                - (
                    b * centerSourceX
                    + d * centerSourceY
                );

            image.style.width =
                naturalWidth + 'px';

            image.style.height =
                naturalHeight + 'px';

            image.style.transform =
                'matrix('
                + a + ','
                + b + ','
                + c + ','
                + d + ','
                + e + ','
                + f
                + ')';

            syncInputs();
        };

        const applyScreenPan = (
            baseState,
            dx,
            dy
        ) => {
            const rect =
                viewport.getBoundingClientRect();

            const oldState = state;

            state = {
                ...baseState
            };

            const g =
                geometry(
                    rect.width,
                    rect.height,
                    state
                );

            const sourceDx =
                (
                    g.cos * dx
                    + g.sin * dy
                ) / g.scale;

            const sourceDy =
                (
                    -g.sin * dx
                    + g.cos * dy
                ) / g.scale;

            state.centerX =
                baseState.centerX
                - sourceDx / naturalWidth;

            state.centerY =
                baseState.centerY
                - sourceDy / naturalHeight;

            state.centerX =
                clamp(state.centerX, 0, 1);

            state.centerY =
                clamp(state.centerY, 0, 1);

            return oldState;
        };

        const getTwoPointers = () =>
            Array.from(
                pointers.values()
            ).slice(0, 2);

        const twoPointerMetrics = () => {
            const [p1, p2] =
                getTwoPointers();

            if (!p1 || !p2) {
                return null;
            }

            const dx =
                p2.x - p1.x;

            const dy =
                p2.y - p1.y;

            return {
                distance:
                    Math.hypot(dx, dy),

                angle:
                    Math.atan2(dy, dx)
                    * 180 / Math.PI,

                midpointX:
                    (p1.x + p2.x) / 2,

                midpointY:
                    (p1.y + p2.y) / 2
            };
        };

        const beginOneFinger = () => {
            if (pointers.size !== 1) {
                oneFingerStart = null;
                return;
            }

            const p =
                Array.from(
                    pointers.values()
                )[0];

            oneFingerStart = {
                x: p.x,
                y: p.y,
                state: {
                    ...state
                }
            };

            twoFingerStart = null;
        };

        const beginTwoFinger = () => {
            const metrics =
                twoPointerMetrics();

            if (!metrics) {
                return;
            }

            twoFingerStart = {
                ...metrics,
                state: {
                    ...state
                }
            };

            oneFingerStart = null;
        };

        viewport.addEventListener(
            'pointerdown',
            function (event) {
                event.preventDefault();

                viewport.setPointerCapture(
                    event.pointerId
                );

                pointers.set(
                    event.pointerId,
                    {
                        x: event.clientX,
                        y: event.clientY
                    }
                );

                if (pointers.size === 1) {
                    beginOneFinger();
                } else if (
                    pointers.size === 2
                ) {
                    beginTwoFinger();
                }

                viewport.classList.add(
                    'is-dragging'
                );
            }
        );

        viewport.addEventListener(
            'pointermove',
            function (event) {
                if (
                    !pointers.has(
                        event.pointerId
                    )
                ) {
                    return;
                }

                event.preventDefault();

                pointers.set(
                    event.pointerId,
                    {
                        x: event.clientX,
                        y: event.clientY
                    }
                );

                if (
                    pointers.size === 1
                    && oneFingerStart
                ) {
                    const p =
                        Array.from(
                            pointers.values()
                        )[0];

                    applyScreenPan(
                        oneFingerStart.state,
                        p.x - oneFingerStart.x,
                        p.y - oneFingerStart.y
                    );

                    render();

                    return;
                }

                if (
                    pointers.size >= 2
                    && twoFingerStart
                ) {
                    const current =
                        twoPointerMetrics();

                    if (!current) {
                        return;
                    }

                    const distanceRatio =
                        current.distance
                        / Math.max(
                            1,
                            twoFingerStart.distance
                        );

                    const angleChange =
                        current.angle
                        - twoFingerStart.angle;

                    state = {
                        ...twoFingerStart.state,

                        zoom: clamp(
                            twoFingerStart
                                .state
                                .zoom
                                * distanceRatio,
                            1,
                            4
                        ),

                        rotation:
                            normaliseAngle(
                                twoFingerStart
                                    .state
                                    .rotation
                                + angleChange
                            )
                    };

                    const midpointDx =
                        current.midpointX
                        - twoFingerStart
                            .midpointX;

                    const midpointDy =
                        current.midpointY
                        - twoFingerStart
                            .midpointY;

                    const rotatedState = {
                        ...state
                    };

                    applyScreenPan(
                        rotatedState,
                        midpointDx,
                        midpointDy
                    );

                    render();
                }
            }
        );

        const pointerEnd = (
            event
        ) => {
            pointers.delete(
                event.pointerId
            );

            if (pointers.size === 1) {
                beginOneFinger();
            } else if (
                pointers.size === 0
            ) {
                oneFingerStart = null;
                twoFingerStart = null;

                viewport.classList.remove(
                    'is-dragging'
                );
            }
        };

        viewport.addEventListener(
            'pointerup',
            pointerEnd
        );

        viewport.addEventListener(
            'pointercancel',
            pointerEnd
        );

        zoomInput.addEventListener(
            'input',
            function () {
                state.zoom =
                    clamp(
                        parseFloat(
                            this.value || '1'
                        ),
                        1,
                        4
                    );

                render();
            }
        );

        rotationInput.addEventListener(
            'input',
            function () {
                state.rotation =
                    normaliseAngle(
                        parseFloat(
                            this.value || '0'
                        )
                    );

                render();
            }
        );

        resetButton.addEventListener(
            'click',
            function () {
                state = {
                    ...initialState
                };

                render();
            }
        );

        toggle.addEventListener(
            'click',
            function () {
                panel.hidden =
                    !panel.hidden;

                if (!panel.hidden) {
                    requestAnimationFrame(
                        render
                    );
                }
            }
        );

        const renderCropToDataUrl = () => {
            const canvas =
                document.createElement(
                    'canvas'
                );

            canvas.width = 1200;
            canvas.height = 900;

            const ctx =
                canvas.getContext(
                    '2d'
                );

            const g =
                geometry(
                    canvas.width,
                    canvas.height
                );

            const centerSourceX =
                state.centerX
                * naturalWidth;

            const centerSourceY =
                state.centerY
                * naturalHeight;

            const a =
                g.scale * g.cos;

            const b =
                g.scale * g.sin;

            const c =
                -g.scale * g.sin;

            const d =
                g.scale * g.cos;

            const e =
                canvas.width / 2
                - (
                    a * centerSourceX
                    + c * centerSourceY
                );

            const f =
                canvas.height / 2
                - (
                    b * centerSourceX
                    + d * centerSourceY
                );

            ctx.fillStyle = '#ffffff';

            ctx.fillRect(
                0,
                0,
                canvas.width,
                canvas.height
            );

            ctx.setTransform(
                a,
                b,
                c,
                d,
                e,
                f
            );

            ctx.drawImage(
                image,
                0,
                0,
                naturalWidth,
                naturalHeight
            );

            let result =
                canvas.toDataURL(
                    'image/webp',
                    0.90
                );

            if (
                !result.startsWith(
                    'data:image/webp'
                )
            ) {
                result =
                    canvas.toDataURL(
                        'image/jpeg',
                        0.90
                    );
            }

            return result;
        };

        editor.addEventListener(
            'submit',
            function (event) {
                const submitter =
                    event.submitter;

                if (
                    !submitter
                    || submitter.value
                        !== 'save_crop'
                ) {
                    return;
                }

                /*
                 * Refresh the dimensions directly from
                 * the image before deciding it is not
                 * ready. This also covers images that
                 * finished loading before our handler
                 * was attached.
                 */
                if (
                    image.naturalWidth > 0
                    && image.naturalHeight > 0
                ) {
                    naturalWidth =
                        image.naturalWidth;

                    naturalHeight =
                        image.naturalHeight;
                }

                if (
                    !naturalWidth
                    || !naturalHeight
                ) {
                    event.preventDefault();

                    console.error(
                        'Virtual Garage source image is not ready.',
                        {
                            src: image.currentSrc || image.src,
                            complete: image.complete,
                            naturalWidth: image.naturalWidth,
                            naturalHeight: image.naturalHeight
                        }
                    );

                    window.alert(
                        'The photo has not finished loading. '
                        + 'Please wait a moment and try Save photo again.'
                    );

                    return;
                }

                try {
                    render();

                    const rendered =
                        renderCropToDataUrl();

                    if (
                        typeof rendered !== 'string'
                        || !rendered.startsWith(
                            'data:image/'
                        )
                    ) {
                        throw new Error(
                            'Rendered crop did not produce an image.'
                        );
                    }

                    inputCroppedImage.value =
                        rendered;
                } catch (error) {
                    event.preventDefault();

                    console.error(
                        'Virtual Garage crop render failed.',
                        error
                    );

                    window.alert(
                        'The adjusted photo could not be prepared. '
                        + 'Please try again.'
                    );
                }
            }
        );

        image.addEventListener(
            'load',
            function () {
                naturalWidth =
                    image.naturalWidth;

                naturalHeight =
                    image.naturalHeight;

                render();
            }
        );

        if (
            image.complete
            && image.naturalWidth > 0
        ) {
            naturalWidth =
                image.naturalWidth;

            naturalHeight =
                image.naturalHeight;
        }

        window.addEventListener(
            'resize',
            function () {
                if (!panel.hidden) {
                    render();
                }
            }
        );
    });
});
</script>

@endsection

