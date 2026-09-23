@extends('site::layouts.app')

@section('title', __('site::messages.categories'))
@section('description', __('site::messages.hero_lead'))

@section('content')
<div class="shell shell--wide page">
    <div class="stack stack--section">
        <div class="category-page__intro">
            <div class="stack stack--tight">
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}">{{ __('site::messages.home') }}</a>
                    <span class="breadcrumb__separator">/</span>
                    <span>{{ __('site::messages.categories') }}</span>
                </nav>
                <h1 class="title-page">{{ __('site::messages.all_categories') }}</h1>
            </div>

            <aside
                class="ad-slot"
                aria-label="Advertisement"
            >
                <div class="ad-slot__inner">
                <div class="ad-slot__frame ad-slot__frame--pcez">
                    <img
                        src="{{ asset('images/ads/pcez-logo.png') }}"
                        alt="PCEZ Computers"
                        class="ad-slot__pcez-logo"
                    >

                    <span class="ad-slot__pcez-copy">
                        IT Support • Repairs • Business Technology
                    </span>

                    <span class="ad-slot__pcez-actions">
                        <a
                            href="https://pcez.com.au"
                            target="_blank"
                            rel="noopener sponsored"
                            class="ad-slot__pcez-cta"
                        >
                            Visit PCEZ
                        </a>

                        <a
                            href="mailto:support@sellmyjunk.com.au?subject=Advertising%20with%20SellMyJunk"
                            class="ad-slot__advertise-link"
                        >
                            Advertise with us
                        </a>
                    </span>
                </div>
                </div>
            </aside>
        </div>

        @forelse($categories as $category)
            <section class="section">
                <div class="section__head">
                    <h2 class="title-section">{{ $category->getAttribute('name') }}</h2>
                    <a href="{{ route('listings.index', ['category' => $category->getKey()]) }}" class="text-link">
                        {{ __('site::messages.view_all') }}
                    </a>
                </div>

                @if($category->getRelation('children')->isNotEmpty())
                    <div class="grid grid--categories">
                        @foreach($category->getRelation('children') as $child)
                            <a href="{{ route('listings.index', ['category' => $child->getKey()]) }}" class="category-card">
                                <span class="category-card__icon">
                                    @if($child->iconUrl())
                                        <img src="{{ $child->iconUrl() }}" alt="" loading="lazy">
                                    @else
                                        <x-ui.icon name="grid"/>
                                    @endif
                                </span>
                                <span class="category-card__name">{{ $child->getAttribute('name') }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @empty
            <x-ui.empty-state icon="grid" :title="__('site::messages.no_listings')" :text="__('site::messages.no_listings_hint')"/>
        @endforelse
    </div>
</div>
@endsection
