@extends('site::layouts.app')

@section('title', 'Wanted: '.$wanted->title)

@section(
    'description',
    \Illuminate\Support\Str::limit(
        strip_tags((string) $wanted->description),
        155
    )
)

@php
    $buyer = $wanted->getRelation('user');

    $place = collect([
        $wanted->city,
        $wanted->country,
    ])->filter()->implode(', ');
@endphp

@section('content')
<div class="shell shell--wide page">
    <div class="stack stack--loose">

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">
                Home
            </a>

            <span class="breadcrumb__separator">/</span>

            <span>Wanted</span>

            <span class="breadcrumb__separator">/</span>

            <span>{{ $wanted->title }}</span>
        </nav>

        @if($wanted->status === \Modules\Listing\Models\WantedPost::STATUS_FULFILLED)
            <div class="alert">
                <x-ui.icon name="check"/>

                <span>
                    <strong>Found!</strong>
                    This person is no longer actively looking for this item.
                </span>
            </div>
        @endif

        <section class="card">
            <div class="card__body">
                <div class="stack stack--loose">

                    <div class="stack stack--tight">
                        <span class="badge badge--solid">
                            WANTED
                        </span>

                        <h1 class="title-page">
                            {{ $wanted->title }}
                        </h1>

                        <p class="text-price text-price--large">
                            {{ $wanted->budgetLabel() }}
                        </p>
                    </div>

                    <div class="row row--wrap text-meta">

                        @if($wanted->category)
                            <span>
                                {{ $wanted->category->name }}
                            </span>
                        @endif

                        @if($place !== '')
                            <span class="row" style="gap:var(--space-1)">
                                <x-ui.icon
                                    name="map-pin"
                                    style="width:14px;height:14px"
                                />
                                {{ $place }}
                            </span>
                        @endif

                        @if($wanted->published_at)
                            <span class="row" style="gap:var(--space-1)">
                                <x-ui.icon
                                    name="clock"
                                    style="width:14px;height:14px"
                                />
                                {{ $wanted->published_at->diffForHumans() }}
                            </span>
                        @endif

                    </div>

                    @if(filled($wanted->description))
                        <div class="stack stack--tight">
                            <h2 class="card__title">
                                What I'm looking for
                            </h2>

                            <div class="prose">
                                {!! nl2br(e($wanted->description)) !!}
                            </div>
                        </div>
                    @endif

                    @if($buyer)
                        <div class="stack stack--tight">
                            <h2 class="card__title">
                                Wanted by
                            </h2>

                            <a
                                href="{{ route('sellers.show', $buyer->getKey()) }}"
                                class="text-link"
                            >
                                {{ $buyer->name }}
                            </a>
                        </div>
                    @endif

                    @auth
                        @if(
                            (int) auth()->id() === (int) $wanted->user_id
                        )
                            <a
                                href="{{ route('panel.wanted.edit', $wanted) }}"
                                class="button button--secondary"
                            >
                                Manage Wanted Post
                            </a>
                        @endif
                    @endauth

                </div>
            </div>
        </section>

        @if(
            $wanted->status === \Modules\Listing\Models\WantedPost::STATUS_ACTIVE
            && isset($matches)
            && $matches->isNotEmpty()
        )
            <section class="section">
                <div class="section__head">
                    <div>
                        <h2 class="title-section">
                            Possible matches
                        </h2>

                        <p class="text-muted">
                            Listings that may match what this person is looking for.
                        </p>
                    </div>
                </div>

                <div class="grid grid--listings">
                    @foreach($matches as $listing)
                        <x-ui.listing-card :listing="$listing"/>
                    @endforeach
                </div>
            </section>
        @endif

        @if($wanted->status === \Modules\Listing\Models\WantedPost::STATUS_ACTIVE)
            <section class="card">
                <div class="card__body">
                    <div class="stack stack--tight">
                        <h2 class="card__title">
                            Have something like this?
                        </h2>

                        <p class="text-muted">
                            Soon Sell My Junk will automatically match your listings with people looking for them.
                        </p>

                        <a
                            href="{{ route('panel.listings.create') }}"
                            class="button button--primary"
                        >
                            Sell something like this
                        </a>
                    </div>
                </div>
            </section>
        @endif

    </div>
</div>
@endsection
