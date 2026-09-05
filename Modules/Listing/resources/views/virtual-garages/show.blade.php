@extends('site::layouts.app')

@section('title', $garage->title)

@section(
    'description',
    \Illuminate\Support\Str::limit(
        strip_tags((string) $garage->description),
        155
    )
)

@php
    $seller = $garage->getRelation('user');

    $place = collect([
        $garage->city,
        $garage->country,
    ])->filter()->implode(', ');

    $totalCount = $listings->count();
@endphp

@section('content')
<div class="shell shell--wide page">
    <div class="stack stack--loose">

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span class="breadcrumb__separator">/</span>
            <a href="{{ route('listings.index') }}">Browse</a>
            <span class="breadcrumb__separator">/</span>
            <span>{{ $garage->title }}</span>
        </nav>

        @if(
            $garage->status ===
            \Modules\Listing\Models\VirtualGarage::STATUS_COMPLETED
        )
            <div class="alert">
                <x-ui.icon name="check"/>
                <span>
                    <strong>This Virtual Garage Sale has finished.</strong>
                    You can still browse the items and see what was sold.
                </span>
            </div>
        @endif

        <section class="card">
            <div class="card__body">
                <div class="stack stack--tight">

                    <div class="row row--between row--wrap">
                        <div class="stack stack--tight">
                            <span class="badge badge--solid">
                                VIRTUAL GARAGE SALE
                            </span>

                            <h1 class="title-page">
                                {{ $garage->title }}
                            </h1>
                        </div>

                        @auth
                            @if(
                                (int) auth()->id() ===
                                (int) $garage->user_id
                            )
                                <a
                                    href="{{ route(
                                        'panel.virtual-garages.edit',
                                        $garage
                                    ) }}"
                                    class="button button--secondary"
                                >
                                    Edit Garage
                                </a>
                            @endif
                        @endauth
                    </div>

                    <div class="row row--wrap text-meta">

                        @if($place !== '')
                            <span class="row" style="gap:var(--space-1)">
                                <x-ui.icon
                                    name="map-pin"
                                    style="width:14px;height:14px"
                                />
                                {{ $place }}
                            </span>
                        @endif

                        <span>
                            {{ $totalCount }}
                            {{ \Illuminate\Support\Str::plural(
                                'item',
                                $totalCount
                            ) }}
                        </span>

                        <span>·</span>

                        <span>
                            {{ $availableCount }} available
                        </span>

                        <span>·</span>

                        <span>
                            {{ $soldCount }} sold
                        </span>

                    </div>

                    @if(filled($garage->description))
                        <div class="prose">
                            {!! nl2br(e($garage->description)) !!}
                        </div>
                    @endif

                    @if($seller)
                        <div class="row row--wrap text-meta">
                            <span>Garage sale by</span>

                            <a
                                href="{{ route(
                                    'sellers.show',
                                    $seller->getKey()
                                ) }}"
                                class="text-link"
                            >
                                {{ $seller->name }}
                            </a>
                        </div>
                    @endif

                </div>
            </div>
        </section>

        @if($availableCount > 0)
            <section class="card">
                <div class="card__body">
                    <div class="stack stack--tight">

                        <h2 class="card__title">
                            Everything still available
                        </h2>

                        <div class="row row--wrap text-meta">
                            <span>
                                Listed value:
                                <strong>
                                    ${{ number_format(
                                        $remainingListedTotal,
                                        2
                                    ) }}
                                </strong>
                                AUD
                            </span>

                            @if($garage->bulk_price !== null)
                                <span>·</span>

                                <span>
                                    Garage price:
                                    <strong>
                                        ${{ number_format(
                                            (float) $garage->bulk_price,
                                            2
                                        ) }}
                                    </strong>
                                    AUD
                                </span>
                            @endif
                        </div>

                        @if($garage->bulk_price !== null)
                            <p class="text-muted">
                                Take everything still available for the
                                garage price above.
                            </p>
                        @elseif($garage->allow_bulk_offers)
                            <p class="text-muted">
                                The seller is open to offers for everything
                                still available.
                            </p>
                        @endif

                    </div>
                </div>
            </section>
        @endif

        <section class="section">
            <div class="section__head">
                <div>
                    <h2 class="title-section">
                        Items in this Garage Sale
                    </h2>

                    @if($availableCount > 0)
                        <p class="text-muted">
                            Buy individual items or make a deal for several.
                        </p>
                    @endif
                </div>
            </div>

            @if($listings->isNotEmpty())
                <div class="grid grid--listings">
                    @foreach($listings as $listing)

                        <div class="stack stack--tight">

                            @if(
                                $garage->status ===
                                \Modules\Listing\Models\VirtualGarage::STATUS_ACTIVE
                                && $listing->statusValue() === 'active'
                                && (int) $listing->quantity_available > 0
                            )
                                @auth
                                    @if(
                                        (int) auth()->id() !==
                                        (int) $garage->user_id
                                    )
                                        <label
                                            class="row"
                                            style="gap:var(--space-2)"
                                        >
                                            <input
                                                type="checkbox"
                                                name="listing_ids[]"
                                                value="{{ $listing->getKey() }}"
                                                form="garage-bundle-offer-form"
                                                data-garage-bundle-item
                                                data-garage-bundle-price="{{ (float) $listing->price }}"
                                            >
                                            <span class="text-body">
                                                Add to bundle
                                            </span>
                                        </label>
                                    @endif
                                @endauth
                            @endif

                            @if($listing->statusValue() === 'sold')
                                <span class="badge">
                                    SOLD
                                </span>
                            @endif

                            <x-ui.listing-card
                                :listing="$listing"
                                :favorited="in_array(
                                    (int) $listing->getKey(),
                                    $favoriteListingIds,
                                    true
                                )"
                            />

                        </div>
                    @endforeach
                </div>
            @else
                <x-ui.empty-state
                    icon="tag"
                    title="Nothing here yet"
                    text="This Virtual Garage Sale doesn't have any items at the moment."
                />
            @endif
        </section>

        @if(
            $garage->status ===
            \Modules\Listing\Models\VirtualGarage::STATUS_ACTIVE
            && $availableCount > 1
        )
            @auth
                @if(
                    (int) auth()->id() !==
                    (int) $garage->user_id
                )
                    <section class="card">
                        <div class="card__head">
                            <div>
                                <h2 class="card__title">
                                    Make a Garage Bundle Offer
                                </h2>

                                <p class="text-muted">
                                    Select at least two available items above,
                                    or select everything remaining.
                                </p>
                            </div>
                        </div>

                        <form
                            id="garage-bundle-offer-form"
                            method="POST"
                            action="{{ route(
                                'virtual-garage.bundle-offers.store',
                                $garage
                            ) }}"
                            class="card__body stack stack--loose"
                        >
                            @csrf

                            <div class="row row--wrap">
                                <span
                                    class="badge"
                                    data-garage-bundle-count
                                >
                                    0 items selected
                                </span>

                                <span class="text-muted">
                                    Listed total:
                                    <strong data-garage-bundle-total>
                                        0.00
                                    </strong>
                                    AUD
                                </span>

                                <button
                                    type="button"
                                    class="button button--secondary button--small"
                                    data-garage-select-all
                                >
                                    Select everything remaining
                                </button>
                            </div>

                            @error('listing_ids')
                                <p class="field__error">
                                    {{ $message }}
                                </p>
                            @enderror

                            <div class="field">
                                <label
                                    for="garage-bundle-amount"
                                    class="field__label"
                                >
                                    Your offer
                                </label>

                                <span class="input-affix">
                                    <input
                                        id="garage-bundle-amount"
                                        name="amount"
                                        type="number"
                                        min="1"
                                        step="0.01"
                                        class="input"
                                        value="{{ old(
                                            'amount',
                                            $garage->bulk_price
                                        ) }}"
                                        required
                                    >

                                    <span class="input-affix__suffix">
                                        AUD
                                    </span>
                                </span>
                            </div>

                            <div class="field">
                                <label
                                    for="garage-bundle-message"
                                    class="field__label"
                                >
                                    Message
                                    <span class="text-muted">
                                        (optional)
                                    </span>
                                </label>

                                <textarea
                                    id="garage-bundle-message"
                                    name="message"
                                    class="textarea"
                                    maxlength="500"
                                    rows="4"
                                >{{ old('message') }}</textarea>
                            </div>

                            <div>
                                <button
                                    type="submit"
                                    class="button button--primary"
                                >
                                    Send Bundle Offer
                                </button>
                            </div>
                        </form>
                    </section>
                @endif
            @endauth
        @endif

    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const items = Array.from(
        document.querySelectorAll('[data-garage-bundle-item]')
    );

    if (!items.length) {
        return;
    }

    const countEl = document.querySelector(
        '[data-garage-bundle-count]'
    );

    const totalEl = document.querySelector(
        '[data-garage-bundle-total]'
    );

    const selectAllButton = document.querySelector(
        '[data-garage-select-all]'
    );

    const updateSummary = () => {
        const selected = items.filter(item => item.checked);

        const total = selected.reduce(
            (sum, item) =>
                sum + Number(
                    item.dataset.garageBundlePrice || 0
                ),
            0
        );

        if (countEl) {
            countEl.textContent =
                `${selected.length} item${
                    selected.length === 1 ? '' : 's'
                } selected`;
        }

        if (totalEl) {
            totalEl.textContent = total.toFixed(2);
        }
    };

    items.forEach(item => {
        item.addEventListener('change', updateSummary);
    });

    if (selectAllButton) {
        selectAllButton.addEventListener('click', () => {
            const shouldSelect =
                items.some(item => !item.checked);

            items.forEach(item => {
                item.checked = shouldSelect;
            });

            updateSummary();
        });
    }

    updateSummary();
});
</script>
@endpush

@endsection
