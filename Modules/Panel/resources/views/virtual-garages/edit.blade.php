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
                                $photo->status ===
                                \Modules\Listing\Models\VirtualGaragePhoto::STATUS_PENDING
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
                                        Analyse with AI
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
                                data-confirm="Remove this garage photo?"
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

                        @if($item->photo)
                            <div
                                style="
                                    aspect-ratio:16/9;
                                    overflow:hidden;
                                    background:var(--color-surface-muted);
                                "
                            >
                                <img
                                    src="{{ $item->photo->url() }}"
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

                        <div class="card__body stack stack--tight">

                            <div class="row row--between row--wrap">
                                <span class="badge">
                                    AI DRAFT
                                </span>

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
                                    data-confirm="Remove this item from the Virtual Garage?"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="button button--ghost button--small"
                                    >
                                        Remove item
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

                    <button
                        type="submit"
                        name="action"
                        value="save"
                        class="button button--secondary"
                    >
                        Save Virtual Garage
                    </button>

                    @if(
                        $garage->status ===
                        \Modules\Listing\Models\VirtualGarage::STATUS_DRAFT
                    )
                        <button
                            type="submit"
                            name="action"
                            value="publish"
                            class="button button--primary"
                        >
                            Save & Publish
                        </button>
                    @endif

                </div>

            </div>

        </form>
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
@endsection
