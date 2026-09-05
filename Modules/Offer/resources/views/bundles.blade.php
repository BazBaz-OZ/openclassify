@extends('panel::layouts.panel', ['panelSection' => 'offers'])

@section('title', 'Bundle Offers')

@php
    $tones = [
        'positive' => 'badge--positive',
        'critical' => 'badge--critical',
        'caution' => 'badge--caution',
        'default' => '',
    ];
@endphp

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">Bundle Offers</h1>
        <p class="text-muted">
            Offers covering multiple items from the same Clear Out or Virtual Garage.
        </p>
    </div>
</header>

<nav class="chip-row">
    <a
        href="{{ route('panel.offers.index') }}"
        class="pill"
    >
        Individual Offers
    </a>

    <a
        href="{{ route('panel.bundle-offers.index', ['direction' => 'received']) }}"
        class="pill {{ $direction === 'received' ? 'is-active' : '' }}"
    >
        Bundle Offers Received
    </a>

    <a
        href="{{ route('panel.bundle-offers.index', ['direction' => 'sent']) }}"
        class="pill {{ $direction === 'sent' ? 'is-active' : '' }}"
    >
        Bundle Offers Sent
    </a>
</nav>

@if($bundleOffers->isNotEmpty())
    <section class="card">
        <div class="data-list">

            @foreach($bundleOffers as $bundle)
                @php
                    $person = $direction === 'sent'
                        ? $bundle->seller
                        : $bundle->buyer;

                    $listedTotal = $bundle->listedTotal();
                @endphp

                <article class="data-row">

                    <div class="data-row__media">
                        <span class="listing-card__placeholder">
                            <x-ui.icon name="tag"/>
                        </span>
                    </div>

                    <div class="data-row__main">
                        <p class="data-row__title">
                            @if($bundle->virtualGarage)
                                <a href="{{ route('virtual-garages.show', $bundle->virtualGarage) }}">
                                    Virtual Garage: {{ $bundle->virtualGarage->title }}
                                </a>
                            @elseif($bundle->clearOut)
                                <a href="{{ route('clear-outs.show', $bundle->clearOut) }}">
                                    Clear Out: {{ $bundle->clearOut->title }}
                                </a>
                            @else
                                Bundle Offer #{{ $bundle->getKey() }}
                            @endif
                        </p>

                        <div class="data-row__meta">
                            <span class="text-price">
                                Offer: {{ $bundle->amountLabel() }}
                            </span>

                            <span>
                                Listed total:
                                {{ number_format($listedTotal, 2) }}
                                {{ $bundle->currency }}
                            </span>

                            <span class="badge {{ $tones[$bundle->statusTone()] }}">
                                {{ ucfirst($bundle->status) }}
                            </span>

                            @if($bundle->isFulfilled())
                                <span class="badge badge--positive">
                                    Collected
                                </span>
                            @endif

                            @if($person)
                                <span class="data-row__metric">
                                    <x-ui.icon name="user"/>
                                    {{ $person->name }}
                                </span>
                            @endif

                            <span>
                                {{ $bundle->items->count() }} items
                            </span>
                        </div>

                        <div class="stack stack--tight">
                            @foreach($bundle->items as $item)
                                @if($item->listing)
                                    <div class="row row--wrap text-muted">
                                        <a
                                            href="{{ route('listings.show', $item->listing) }}"
                                            class="text-link"
                                        >
                                            {{ $item->listing->title }}
                                        </a>

                                        <span>
                                            {{ number_format((float) $item->listed_price, 2) }}
                                            {{ $bundle->currency }}
                                        </span>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if(filled($bundle->message))
                            <p class="text-muted">
                                {{ $bundle->message }}
                            </p>
                        @endif
                    </div>

                    <div class="data-row__actions">

                        @if(
                            $direction === 'received'
                            && $bundle->isPending()
                        )
                            <form
                                method="POST"
                                action="{{ route('bundle-offers.accept', $bundle) }}"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="button button--primary button--small"
                                >
                                    Accept
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('bundle-offers.decline', $bundle) }}"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="button button--ghost button--small"
                                >
                                    Decline
                                </button>
                            </form>

                        @elseif(
                            $direction === 'received'
                            && $bundle->isAccepted()
                            && ! $bundle->isFulfilled()
                        )
                            <form
                                method="POST"
                                action="{{ route('bundle-offers.fulfill', $bundle) }}"
                                data-confirm="Mark this bundle as collected/sold? Stock will be reduced for every item in the bundle."
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="button button--primary button--small"
                                >
                                    Mark collected / sold
                                </button>
                            </form>

                        @elseif(
                            $direction === 'received'
                            && $bundle->isFulfilled()
                        )
                            <span class="badge badge--positive">
                                Collected
                            </span>

                        @elseif(
                            $direction === 'sent'
                            && $bundle->isPending()
                        )
                            <form
                                method="POST"
                                action="{{ route('bundle-offers.withdraw', $bundle) }}"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="button button--ghost button--small"
                                >
                                    Withdraw
                                </button>
                            </form>
                        @endif

                    </div>
                </article>
            @endforeach

        </div>
    </section>

    {{ $bundleOffers->links('components.pagination') }}

@else
    <x-ui.empty-state
        icon="sort"
        title="No bundle offers"
        text="Bundle offers will appear here when buyers make an offer on multiple items from the same Clear Out or Virtual Garage."
    />
@endif
@endsection
