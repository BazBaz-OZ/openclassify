@extends('site::layouts.app')

@section('title', $clearOut->title)

@section(
    'description',
    \Illuminate\Support\Str::limit(
        strip_tags((string) $clearOut->description),
        155
    )
)

@php
    $seller = $clearOut->getRelation('user');

    $place = collect([
        $clearOut->city,
        $clearOut->country,
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
            <span>{{ $clearOut->title }}</span>
        </nav>

        @if($clearOut->status === \Modules\Listing\Models\ClearOut::STATUS_COMPLETED)
            <div class="alert">
                <x-ui.icon name="check"/>
                <span>
                    <strong>This Clear Out has finished.</strong>
                    You can still browse the items and see what was included.
                </span>
            </div>
        @endif

        <section class="card">
            <div class="card__body">
                <div class="stack stack--tight">

                    <div class="row row--between row--wrap">
                        <div class="stack stack--tight">
                            <span class="badge badge--solid">
                                CLEAR OUT
                            </span>

                            <h1 class="title-page">
                                {{ $clearOut->title }}
                            </h1>
                        </div>

                        @auth
                            @if((int) auth()->id() === (int) $clearOut->user_id)
                                <a
                                    href="{{ route('panel.clear-outs.edit', $clearOut) }}"
                                    class="button button--secondary"
                                >
                                    Edit Clear Out
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
                            {{ \Illuminate\Support\Str::plural('item', $totalCount) }}
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

                    @if(filled($clearOut->description))
                        <div class="prose">
                            {!! nl2br(e($clearOut->description)) !!}
                        </div>
                    @endif

                    @if($seller)
                        <div class="row row--wrap text-meta">
                            <span>Clear Out by</span>

                            <a
                                href="{{ route('sellers.show', $seller->getKey()) }}"
                                class="text-link"
                            >
                                {{ $seller->name }}
                            </a>
                        </div>
                    @endif

                </div>
            </div>
        </section>

        <section class="section">
            <div class="section__head">
                <div>
                    <h2 class="title-section">
                        Items in this Clear Out
                    </h2>

                    @if($availableCount > 0)
                        <p class="text-muted">
                            Buy individual items now. Bundle offers are coming next.
                        </p>
                    @endif
                </div>
            </div>

            @if($listings->isNotEmpty())
                <div class="grid grid--listings">
                    @foreach($listings as $listing)
                        <div class="stack stack--tight">

                            @if(
                                $clearOut->status === \Modules\Listing\Models\ClearOut::STATUS_ACTIVE
                                && $listing->statusValue() === 'active'
                                && (int) $listing->quantity_available > 0
                            )
                                @auth
                                    @if((int) auth()->id() !== (int) $clearOut->user_id)
                                        <label class="row" style="gap:var(--space-2)">
                                            <input
                                                type="checkbox"
                                                name="listing_ids[]"
                                                value="{{ $listing->getKey() }}"
                                                form="bundle-offer-form"
                                                data-bundle-item
                                                data-bundle-price="{{ (float) $listing->price }}"
                                            >
                                            <span class="text-body">
                                                Add to bundle
                                            </span>
                                        </label>
                                    @endif
                                @endauth
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
                    title="Nothing available yet"
                    text="This Clear Out doesn't have any items available at the moment."
                />
            @endif
        </section>

        @if(
            $clearOut->status === \Modules\Listing\Models\ClearOut::STATUS_ACTIVE
            && $availableCount > 1
        )
            @auth
                @if((int) auth()->id() !== (int) $clearOut->user_id)

                    <section class="card">
                        <div class="card__head">
                            <div>
                                <h2 class="card__title">
                                    Make a Bundle Offer
                                </h2>

                                <p class="text-muted">
                                    Select at least two available items above and make one offer for all of them.
                                </p>
                            </div>
                        </div>

                        <form
                            id="bundle-offer-form"
                            method="POST"
                            action="{{ route('bundle-offers.store', $clearOut) }}"
                            class="card__body stack stack--loose"
                        >
                            @csrf

                            <div class="row row--wrap">
                                <span class="badge" data-bundle-count>
                                    0 items selected
                                </span>

                                <span class="text-muted">
                                    Listed total:
                                    <strong data-bundle-total>
                                        0.00
                                    </strong>
                                    AUD
                                </span>
                            </div>

                            @error('listing_ids')
                                <p class="field__error">
                                    {{ $message }}
                                </p>
                            @enderror

                            <div class="field">
                                <label
                                    for="bundle-amount"
                                    class="field__label"
                                >
                                    Your offer for the selected items
                                </label>

                                <span class="input-affix">
                                    <input
                                        id="bundle-amount"
                                        name="amount"
                                        type="number"
                                        min="1"
                                        step="0.01"
                                        class="input"
                                        value="{{ old('amount') }}"
                                        required
                                    >

                                    <span class="input-affix__suffix">
                                        AUD
                                    </span>
                                </span>

                                @error('amount')
                                    <p class="field__error">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="field">
                                <label
                                    for="bundle-message"
                                    class="field__label"
                                >
                                    Message
                                </label>

                                <textarea
                                    id="bundle-message"
                                    name="message"
                                    class="textarea"
                                    rows="3"
                                    maxlength="500"
                                    placeholder="For example: I can collect all three on Saturday."
                                >{{ old('message') }}</textarea>
                            </div>

                            <button
                                type="submit"
                                class="button button--accent"
                            >
                                Send Bundle Offer
                            </button>
                        </form>
                    </section>

                    <script>
                        (() => {
                            const boxes = Array.from(
                                document.querySelectorAll('[data-bundle-item]')
                            );

                            const count = document.querySelector(
                                '[data-bundle-count]'
                            );

                            const total = document.querySelector(
                                '[data-bundle-total]'
                            );

                            const update = () => {
                                const selected = boxes.filter(
                                    box => box.checked
                                );

                                const sum = selected.reduce(
                                    (value, box) =>
                                        value
                                        + Number(box.dataset.bundlePrice || 0),
                                    0
                                );

                                if (count) {
                                    count.textContent =
                                        selected.length
                                        + (selected.length === 1
                                            ? ' item selected'
                                            : ' items selected');
                                }

                                if (total) {
                                    total.textContent = sum.toFixed(2);
                                }
                            };

                            boxes.forEach(
                                box => box.addEventListener(
                                    'change',
                                    update
                                )
                            );

                            update();
                        })();
                    </script>

                @endif
            @else
                <section class="card">
                    <div class="card__body">
                        <p class="text-muted">
                            Sign in to select multiple items and make a bundle offer.
                        </p>

                        <a
                            href="{{ route('login') }}"
                            class="button button--primary"
                        >
                            Sign in
                        </a>
                    </div>
                </section>
            @endauth
        @endif

    </div>
</div>
@endsection
