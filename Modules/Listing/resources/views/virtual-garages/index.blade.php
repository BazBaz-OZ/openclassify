@extends('site::layouts.app')

@section('title', 'Virtual Garage Sales')

@section(
    'description',
    'Browse active Virtual Garage Sales and find multiple items from the same seller in one place.'
)

@section('content')
<div class="shell shell--wide page">
    <div class="stack stack--loose">

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span class="breadcrumb__separator">/</span>
            <span>Virtual Garage Sales</span>
        </nav>

        <header class="section__head">
            <div>
                <h1 class="title-page">
                    Virtual Garage Sales
                </h1>

                <p class="text-muted">
                    Browse active garage sales from sellers clearing out
                    multiple items at once.
                </p>
            </div>

            @auth
                <a
                    href="{{ route('panel.virtual-garages.create') }}"
                    class="button button--primary"
                >
                    Start Virtual Garage
                </a>
            @endauth
        </header>

        @if($garages->isNotEmpty())
            <div class="grid grid--listings">

                @foreach($garages as $garage)
                    @php
                        $seller = $garage->getRelation('user');

                        $place = collect([
                            $garage->city,
                            $garage->country,
                        ])->filter()->implode(', ');
                    @endphp

                    <article class="card">
                        <div class="card__body">
                            <div class="stack stack--tight">

                                <div class="row row--between row--wrap">
                                    <span class="badge badge--solid">
                                        VIRTUAL GARAGE
                                    </span>

                                    <span class="badge">
                                        ACTIVE
                                    </span>
                                </div>

                                <h2 class="card__title">
                                    <a
                                        href="{{ route(
                                            'virtual-garages.show',
                                            $garage
                                        ) }}"
                                        class="text-link"
                                    >
                                        {{ $garage->title }}
                                    </a>
                                </h2>

                                @if(filled($garage->description))
                                    <p class="text-muted">
                                        {{ \Illuminate\Support\Str::limit(
                                            strip_tags(
                                                (string) $garage->description
                                            ),
                                            140
                                        ) }}
                                    </p>
                                @endif

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
                                        {{ $garage->available_listings_count }}
                                        available
                                    </span>

                                    <span>·</span>

                                    <span>
                                        {{ $garage->sold_listings_count }}
                                        sold
                                    </span>
                                </div>

                                @if($seller)
                                    <div class="text-meta">
                                        By
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

                                @if($garage->bulk_price !== null)
                                    <div class="row row--wrap text-meta">
                                        <span>
                                            Garage price:
                                            <strong>
                                                ${{ number_format(
                                                    (float) $garage->bulk_price,
                                                    2
                                                ) }}
                                                AUD
                                            </strong>
                                        </span>
                                    </div>
                                @elseif($garage->allow_bulk_offers)
                                    <div class="text-meta">
                                        Bulk offers welcome
                                    </div>
                                @endif

                                <div>
                                    <a
                                        href="{{ route(
                                            'virtual-garages.show',
                                            $garage
                                        ) }}"
                                        class="button button--secondary"
                                    >
                                        View Garage
                                    </a>
                                </div>

                            </div>
                        </div>
                    </article>
                @endforeach

            </div>

            {{ $garages->links('components.pagination') }}
        @else
            <x-ui.empty-state
                icon="tag"
                title="No active Virtual Garages"
                text="There are no active Virtual Garage Sales at the moment."
            >
                @auth
                    <a
                        href="{{ route(
                            'panel.virtual-garages.create'
                        ) }}"
                        class="button button--primary"
                    >
                        Start the first Virtual Garage
                    </a>
                @endauth
            </x-ui.empty-state>
        @endif

    </div>
</div>
@endsection
