@props(['listing', 'favorited' => false, 'featuredIds' => []])

@php
    $listingId = (int) $listing->getKey();
    $image = $listing->primaryImageUrl('card');
    $category = $listing->category;
    $categoryIcon = trim((string) ($category?->getAttribute('icon') ?: $category?->parent?->getAttribute('icon')));
    $categoryName = trim((string) ($category?->getAttribute('name') ?: 'Listing'));
    $isFeatured = (bool) $listing->getAttribute('is_featured') || in_array($listingId, $featuredIds, true);
    $city = trim((string) $listing->getAttribute('city'));
    $country = trim((string) $listing->getAttribute('country'));
    $place = $city !== '' ? $city : $country;
    $createdAt = $listing->getAttribute('created_at');
@endphp

<article class="listing-card">
    <div class="listing-card__media">
        <a href="{{ route('listings.show', $listing) }}" aria-label="{{ $listing->getAttribute('title') }}">
            @if($image)
                <span class="listing-card__image-loading" aria-hidden="true">
                    <x-ui.icon name="image"/>
                </span>
                <img
                    src="{{ $image }}"
                    alt="{{ $listing->getAttribute('title') }}"
                    class="listing-card__image"
                    data-listing-image
                    loading="lazy"
                    decoding="async"
                >
            @elseif($categoryIcon !== '')
                <span class="listing-card__category-placeholder">
                    <img
                        src="{{ asset(ltrim($categoryIcon, '/')) }}"
                        alt=""
                        class="listing-card__category-image"
                        loading="lazy"
                    >
                    <span class="listing-card__category-name">{{ $categoryName }}</span>
                </span>
            @else
                <span class="listing-card__placeholder"><x-ui.icon name="image"/></span>
            @endif
        </a>

        @if($listing->statusValue() === 'sold')
            <div class="listing-card__sold-overlay" aria-label="Sold">
                <span>SOLD</span>
            </div>
        @endif

        <div class="listing-card__flags">
            @if($isFeatured)
                <span class="badge badge--solid">{{ __('promotion::messages.featured_badge') }}</span>
            @endif
            @if($listing->statusValue() === 'sold')
                <span class="badge badge--critical">{{ $listing->statusLabel() }}</span>
            @endif
        </div>

        @auth
            <span
                class="listing-card__favorite {{ $favorited ? 'is-active' : '' }}"
                data-favorite-toggle="{{ route('favorites.listings.toggle', $listing) }}"
                role="button"
                tabindex="0"
                aria-pressed="{{ $favorited ? 'true' : 'false' }}"
                aria-label="{{ __('site::messages.favorites') }}"
            ><x-ui.icon name="heart"/></span>
        @else
            <a
                href="{{ route('login') }}"
                class="listing-card__favorite"
                aria-label="{{ __('site::messages.favorites') }}"
            ><x-ui.icon name="heart"/></a>
        @endauth
    </div>

    <div class="listing-card__body">
        <p class="listing-card__price">{{ $listing->panelPriceLabel() }}</p>
        <h3 class="listing-card__title text-clamp-2">
            <a href="{{ route('listings.show', $listing) }}">{{ $listing->getAttribute('title') }}</a>
        </h3>
        <div class="listing-card__meta">
            @if($place !== '')
                <span>{{ $place }}</span>
                <span aria-hidden="true">·</span>
            @endif
            @if($createdAt)
                <time datetime="{{ $createdAt->toIso8601String() }}">{{ $createdAt->diffForHumans(short: true) }}</time>
            @endif
        </div>
    </div>
</article>
