@php
    $settings = $generalSettings ?? [];
    $siteName = $settings['site_name'] ?? config('app.name', 'Sell My Junk');
    $siteDescription = $settings['site_description'] ?? __('site::messages.tagline');
    $siteLogoUrl = $settings['site_logo_url'] ?? null;
    $isAuthenticated = auth()->check();
    $account = is_array($headerAccountMeta ?? null) ? $headerAccountMeta : [];
    $messageCount = max(0, (int) ($account['messages'] ?? 0));
    $notificationCount = max(0, (int) ($account['notifications'] ?? 0));
    $favoriteCount = max(0, (int) ($account['favorites'] ?? 0));
    $badge = static fn (int $count): string => $count > 99 ? '99+' : (string) $count;
    $navCategories = collect($headerNavCategories ?? [])->values();
    $countries = collect($headerLocationCountries ?? [])->values();
    $citiesUrl = \Illuminate\Support\Facades\Route::has('locations.cities')
        ? route('locations.cities', ['country' => '__COUNTRY__'], false)
        : '';
    $locales = config('app.available_locales', ['en']);
    $localeNames = [
        'en' => 'English', 'tr' => 'Türkçe', 'ar' => 'العربية', 'zh' => '中文',
        'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch', 'pt' => 'Português',
        'ru' => 'Русский', 'ja' => '日本語',
    ];
    $legalPages = \Modules\Page\Models\Page::navigation(\Modules\Page\Models\Page::PLACEMENT_LEGAL);
    $helpPages = \Modules\Page\Models\Page::navigation(\Modules\Page\Models\Page::PLACEMENT_HELP);
    $footerPages = \Modules\Page\Models\Page::navigation(\Modules\Page\Models\Page::PLACEMENT_FOOTER);
    $chromeless = trim((string) $__env->yieldContent('chromeless')) === '1';
    $sellRoute = $isAuthenticated ? route('panel.listings.create') : route('login');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>@hasSection('title')@yield('title') — {{ $siteName }}@else{{ $siteName }}@endif</title>
    <meta name="description" content="@hasSection('description')@yield('description')@else{{ $siteDescription }}@endif">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="@hasSection('title')@yield('title')@else{{ $siteName }}@endif">
    <meta property="og:description" content="@hasSection('description')@yield('description')@else{{ $siteDescription }}@endif">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/ts/app.ts'])
    @livewireStyles

    <!-- SMJ SEARCH SUGGESTIONS -->
    <style>
        [data-search-form] {
            position: relative;
        }

        .search-suggestions {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 1000;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .12);
            border-radius: 10px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .15);
            overflow: hidden;
            max-height: 420px;
            overflow-y: auto;
            text-align: left;
        }

        .search-suggestions__heading {
            padding: 9px 14px 5px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #666;
        }

        .search-suggestions__item {
            display: block;
            padding: 10px 14px;
            text-decoration: none;
            color: inherit;
            border-top: 1px solid rgba(0, 0, 0, .06);
        }

        .search-suggestions__item:hover,
        .search-suggestions__item.is-active {
            background: rgba(0, 0, 0, .05);
        }

        .search-suggestions__label {
            display: block;
            font-weight: 600;
        }

        .search-suggestions__meta {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            color: #777;
        }

        .search-suggestions__empty {
            padding: 12px 14px;
            color: #777;
        }
    </style>

</head>
<body data-inbox-channel="{{ $isAuthenticated ? 'users.'.auth()->id().'.inbox' : '' }}">

@unless($chromeless)
<header class="site-header" data-sticky-header>
    <div class="shell shell--wide">
        <div class="site-header__bar">
            <div class="row">
                <button
                    type="button"
                    class="icon-button site-header__menu-trigger"
                    data-nav-drawer-open
                    aria-controls="site-navigation"
                    aria-expanded="false"
                    aria-label="{{ __('site::messages.menu') }}"
                ><x-ui.icon name="menu"/></button>

                <a href="{{ route('home') }}" class="brand">
                    <span class="brand__mark">
                        @if($siteLogoUrl)
                            <img src="{{ $siteLogoUrl }}" alt="">
                        @else
                            {{ mb_substr($siteName, 0, 1) }}
                        @endif
                    </span>
                    <span class="brand__name">{{ $siteName }}</span>
                </a>
            </div>

            <form
                action="{{ route('listings.index') }}"
                method="GET"
                class="site-search"
                role="search"
                data-search-form
                data-search-suggestions-url="{{ route('listings.search-suggestions') }}"
            >
                <x-ui.icon name="search" class="site-search__icon"/>
                <label class="visually-hidden" for="site-search-input">{{ __('site::messages.search') }}</label>
                <input
                    id="site-search-input"
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="{{ __('site::messages.search_placeholder') }}"
                    class="site-search__input"
                    data-search-input
                    autocomplete="off"
                >
                <button type="button" class="site-search__clear" data-search-clear hidden aria-label="{{ __('site::messages.clear') }}">
                    <x-ui.icon name="close"/>
                </button>
                <button type="submit" class="site-search__submit">{{ __('site::messages.search') }}</button>

                <div
                    class="search-suggestions"
                    data-search-suggestions
                    hidden
                ></div>
            </form>

            <div class="site-header__actions" data-disclosure-group>
                <details class="menu site-header__desktop-only" data-disclosure>
                    <summary class="location-trigger">
                        <x-ui.icon name="map-pin"/>
                        <span data-location-label>{{ __('site::messages.all_locations') }}</span>
                        <x-ui.icon name="chevron-down"/>
                    </summary>
                    <div
                        class="menu__panel menu__panel--wide"
                        data-location-picker
                        data-cities-url="{{ $citiesUrl }}"
                        data-location-fallback="{{ __('site::messages.all_locations') }}"
                    >
                        <div class="field">
                            <label class="field__label" for="header-country">{{ __('site::messages.country') }}</label>
                            <select id="header-country" class="select" data-location-country>
                                <option value="">{{ __('site::messages.all_countries') }}</option>
                                @foreach($countries as $country)
                                    <option
                                        value="{{ $country['id'] }}"
                                        data-code="{{ strtoupper($country['code'] ?? '') }}"
                                        data-name="{{ $country['name'] }}"
                                    >{{ $country['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="header-city">{{ __('site::messages.city') }}</label>
                            <select id="header-city" class="select" data-location-city disabled>
                                <option value="">{{ __('site::messages.all_cities') }}</option>
                            </select>
                        </div>
                        <p class="field__hint" data-location-status>{{ __('site::messages.location_hint') }}</p>
                        <button type="button" class="button button--primary button--block" data-location-apply>
                            {{ __('site::messages.apply') }}
                        </button>
                    </div>
                </details>

                @auth
                    <a href="{{ route('favorites.index') }}" class="icon-button site-header__desktop-only" aria-label="{{ __('site::messages.favorites') }}">
                        <x-ui.icon name="heart"/>
                        @if($favoriteCount > 0)<span class="icon-button__badge">{{ $badge($favoriteCount) }}</span>@endif
                    </a>
                    <a href="{{ route('panel.notifications.index') }}" class="icon-button site-header__desktop-only" aria-label="{{ __('notification::messages.notifications') }}">
                        <x-ui.icon name="bell"/>
                        @if($notificationCount > 0)<span class="icon-button__badge">{{ $badge($notificationCount) }}</span>@endif
                    </a>
                    <a href="{{ route('panel.inbox.index') }}" class="icon-button" aria-label="{{ __('site::messages.inbox') }}">
                        <x-ui.icon name="mail"/>
                        <span class="icon-button__badge {{ $messageCount > 0 ? '' : 'is-hidden' }}" data-inbox-badge="{{ $messageCount }}">{{ $badge($messageCount) }}</span>
                    </a>

                    <details class="menu site-header__desktop-only" data-disclosure>
                        <summary class="icon-button" aria-label="{{ __('site::messages.account') }}"><x-ui.icon name="user"/></summary>
                        <div class="menu__panel">
                            <p class="menu__label">{{ auth()->user()->getDisplayName() }}</p>
                            <a href="{{ route('panel.index') }}" class="menu__item">{{ __('site::messages.dashboard') }}</a>
                            <a href="{{ route('panel.listings.index') }}" class="menu__item">{{ __('site::messages.my_listings') }}</a>
                            <a href="{{ route('panel.offers.index') }}" class="menu__item">{{ __('offer::messages.offers') }}</a>
                            <a href="{{ route('panel.promotions.index') }}" class="menu__item">{{ __('promotion::messages.promotions') }}</a>
                            <a href="{{ route('panel.profile.edit') }}" class="menu__item">{{ __('site::messages.my_profile') }}</a>
                            <div class="menu__separator"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="menu__item">{{ __('site::messages.logout') }}</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('login') }}" class="button button--ghost button--small site-header__desktop-only">
                        {{ __('site::messages.login') }}
                    </a>
                @endauth

                <a href="{{ $sellRoute }}" class="button button--primary button--small">
                    <x-ui.icon name="plus"/>
                    <span>{{ __('site::messages.sell') }}</span>
                </a>
            </div>
        </div>
    </div>

    <nav class="category-bar" aria-label="{{ __('site::messages.categories') }}">
        <div class="shell shell--wide">
            <div class="category-bar__track">
                <a href="{{ route('categories.index') }}" class="category-bar__link">
                    <x-ui.icon name="grid"/>
                    <span>{{ __('site::messages.all_categories') }}</span>
                </a>
                @foreach($navCategories as $navCategory)
                    <a
                        href="{{ route('listings.index', ['category' => $navCategory['id']]) }}"
                        class="category-bar__link {{ (int) request('category') === (int) $navCategory['id'] ? 'is-active' : '' }}"
                    >{{ $navCategory['name'] }}</a>
                @endforeach
            </div>
        </div>
    </nav>
</header>

<div class="drawer" id="site-navigation" data-nav-drawer aria-hidden="true">
    <button type="button" class="drawer__scrim" data-nav-drawer-close aria-label="{{ __('site::messages.close') }}"></button>
    <div class="drawer__panel" role="dialog" aria-modal="true" aria-label="{{ __('site::messages.menu') }}">
        <div class="drawer__head">
            <span class="drawer__title">{{ __('site::messages.menu') }}</span>
            <button type="button" class="icon-button" data-nav-drawer-close aria-label="{{ __('site::messages.close') }}">
                <x-ui.icon name="close"/>
            </button>
        </div>
        <div class="drawer__body">
            <div class="stack stack--tight">
                <a href="{{ $sellRoute }}" class="button button--primary button--block">{{ __('site::messages.sell') }}</a>
                <a href="{{ route('listings.index') }}" class="button button--secondary button--block">{{ __('site::messages.browse') }}</a>
            </div>

            <div class="stack stack--tight">
                <p class="text-eyebrow">{{ __('site::messages.browse') }}</p>
                <div class="nav-list">
                    <a href="{{ route('home') }}" class="nav-list__item"><span>{{ __('site::messages.home') }}</span><x-ui.icon name="chevron-right"/></a>
                    <a href="{{ route('listings.index') }}" class="nav-list__item"><span>{{ __('site::messages.all_listings') }}</span><x-ui.icon name="chevron-right"/></a>
                    <a href="{{ route('categories.index') }}" class="nav-list__item"><span>{{ __('site::messages.categories') }}</span><x-ui.icon name="chevron-right"/></a>
                    <a href="{{ route('promotions.plans') }}" class="nav-list__item"><span>{{ __('promotion::messages.plans') }}</span><x-ui.icon name="chevron-right"/></a>
                </div>
            </div>

            <div class="stack stack--tight">
                <p class="text-eyebrow">{{ __('site::messages.account') }}</p>
                <div class="nav-list">
                    @auth
                        <a href="{{ route('panel.index') }}" class="nav-list__item"><span>{{ __('site::messages.dashboard') }}</span><x-ui.icon name="chevron-right"/></a>
                        <a href="{{ route('panel.listings.index') }}" class="nav-list__item"><span>{{ __('site::messages.my_listings') }}</span><x-ui.icon name="chevron-right"/></a>
                        <a href="{{ route('panel.offers.index') }}" class="nav-list__item"><span>{{ __('offer::messages.offers') }}</span><x-ui.icon name="chevron-right"/></a>
                        <a href="{{ route('favorites.index') }}" class="nav-list__item"><span>{{ __('site::messages.favorites') }}</span><x-ui.icon name="chevron-right"/></a>
                        <a href="{{ route('panel.notifications.index') }}" class="nav-list__item"><span>{{ __('notification::messages.notifications') }}</span><x-ui.icon name="chevron-right"/></a>
                        <a href="{{ route('panel.profile.edit') }}" class="nav-list__item"><span>{{ __('site::messages.my_profile') }}</span><x-ui.icon name="chevron-right"/></a>
                    @else
                        <a href="{{ route('login') }}" class="nav-list__item"><span>{{ __('site::messages.login') }}</span><x-ui.icon name="chevron-right"/></a>
                        <a href="{{ route('register') }}" class="nav-list__item"><span>{{ __('site::messages.register') }}</span><x-ui.icon name="chevron-right"/></a>
                    @endauth
                </div>
            </div>

            <div class="stack stack--tight">
                <p class="text-eyebrow">{{ __('site::messages.language') }}</p>
                <div class="chip-row">
                    @foreach($locales as $locale)
                        <a href="{{ route('lang.switch', $locale) }}" class="pill {{ app()->getLocale() === $locale ? 'is-active' : '' }}">
                            {{ $localeNames[$locale] ?? strtoupper($locale) }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @auth
            <div class="drawer__foot">
                <form method="POST" action="{{ route('logout') }}" class="row" style="width:100%">
                    @csrf
                    <button type="submit" class="button button--secondary button--block">{{ __('site::messages.logout') }}</button>
                </form>
            </div>
        @endauth
    </div>
</div>
@endunless

<main>
    @if(session('success') || session('error') || $errors->any())
        <div class="shell shell--wide" style="padding-top: var(--space-4)">
            <x-ui.flash/>
        </div>
    @endif

    @yield('content')
</main>

@unless($chromeless)
<footer class="site-footer">
    <div class="shell shell--wide">
        <div class="site-footer__grid">
            <div class="site-footer__column">
                <a href="{{ route('home') }}" class="brand">
                    <span class="brand__mark">
                        @if($siteLogoUrl)<img src="{{ $siteLogoUrl }}" alt="">@else{{ mb_substr($siteName, 0, 1) }}@endif
                    </span>
                    <span class="brand__name">{{ $siteName }}</span>
                </a>
                <p class="text-muted" style="max-width: 44ch">{{ $siteDescription }}</p>
            </div>

            <div class="site-footer__column">
                <p class="site-footer__heading">{{ __('site::messages.browse') }}</p>
                <a href="{{ route('listings.index') }}" class="site-footer__link">{{ __('site::messages.all_listings') }}</a>
                <a href="{{ route('categories.index') }}" class="site-footer__link">{{ __('site::messages.categories') }}</a>
                <a href="{{ route('promotions.plans') }}" class="site-footer__link">{{ __('promotion::messages.plans') }}</a>
                @foreach($helpPages as $helpPage)
                    <a href="{{ route('pages.show', $helpPage->getAttribute('slug')) }}" class="site-footer__link">{{ $helpPage->getAttribute('title') }}</a>
                @endforeach
            </div>

            <div class="site-footer__column">
                <p class="site-footer__heading">{{ __('site::messages.company') }}</p>
                @foreach($footerPages as $footerPage)
                    <a href="{{ route('pages.show', $footerPage->getAttribute('slug')) }}" class="site-footer__link">{{ $footerPage->getAttribute('title') }}</a>
                @endforeach
                @foreach($legalPages as $legalPage)
                    <a href="{{ route('pages.show', $legalPage->getAttribute('slug')) }}" class="site-footer__link">{{ $legalPage->getAttribute('title') }}</a>
                @endforeach
            </div>

            <div class="site-footer__column">
                <p class="site-footer__heading">{{ __('site::messages.account') }}</p>
                @auth
                    <a href="{{ route('panel.index') }}" class="site-footer__link">{{ __('site::messages.dashboard') }}</a>
                    <a href="{{ route('panel.listings.create') }}" class="site-footer__link">{{ __('site::messages.sell') }}</a>
                @else
                    <a href="{{ route('login') }}" class="site-footer__link">{{ __('site::messages.login') }}</a>
                    <a href="{{ route('register') }}" class="site-footer__link">{{ __('site::messages.register') }}</a>
                @endauth
            </div>
        </div>

        <div class="site-footer__bottom">
            <p>© {{ date('Y') }} {{ $siteName }}</p>
            <div class="locale-list">
                @foreach($locales as $locale)
                    <a href="{{ route('lang.switch', $locale) }}" class="locale-list__item {{ app()->getLocale() === $locale ? 'is-active' : '' }}">{{ $locale }}</a>
                @endforeach
            </div>
        </div>
    </div>
</footer>
@endunless

@livewireScripts
<x-impersonate::banner/>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // SMJ SEARCH SUGGESTIONS

    document.querySelectorAll('[data-search-form]').forEach((form) => {
        const input = form.querySelector('[data-search-input]');
        const box = form.querySelector('[data-search-suggestions]');
        const url = form.dataset.searchSuggestionsUrl;

        if (!input || !box || !url) {
            return;
        }

        let timer = null;
        let controller = null;
        let activeIndex = -1;

        const closeSuggestions = () => {
            box.hidden = true;
            activeIndex = -1;
        };

        const suggestionItems = () =>
            Array.from(box.querySelectorAll('[data-search-suggestion-item]'));

        const setActiveItem = (index) => {
            const items = suggestionItems();

            items.forEach((item) => item.classList.remove('is-active'));

            if (items.length === 0) {
                activeIndex = -1;
                return;
            }

            if (index < 0) {
                index = items.length - 1;
            }

            if (index >= items.length) {
                index = 0;
            }

            activeIndex = index;
            items[activeIndex].classList.add('is-active');
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        };

        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        const renderGroup = (heading, items) => {
            if (!Array.isArray(items) || items.length === 0) {
                return '';
            }

            return `
                <div class="search-suggestions__heading">${escapeHtml(heading)}</div>
                ${items.map((item) => `
                    <a
                        href="${escapeHtml(item.url)}"
                        class="search-suggestions__item"
                        data-search-suggestion-item
                    >
                        <span class="search-suggestions__label">${escapeHtml(item.label)}</span>
                        ${item.meta
                            ? `<span class="search-suggestions__meta">${escapeHtml(item.meta)}</span>`
                            : ''
                        }
                    </a>
                `).join('')}
            `;
        };

        const loadSuggestions = async () => {
            const value = input.value.trim();

            if (value.length < 2) {
                closeSuggestions();
                box.innerHTML = '';
                return;
            }

            if (controller) {
                controller.abort();
            }

            controller = new AbortController();

            try {
                const response = await fetch(
                    `${url}?q=${encodeURIComponent(value)}`,
                    {
                        headers: {
                            'Accept': 'application/json'
                        },
                        signal: controller.signal
                    }
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                const html =
                    renderGroup('Listings', data.listings) +
                    renderGroup('Categories', data.categories);

                box.innerHTML = html || `
                    <div class="search-suggestions__empty">
                        No suggestions
                    </div>
                `;

                box.hidden = false;
                activeIndex = -1;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Search suggestions failed:', error);
                    closeSuggestions();
                }
            }
        };

        input.addEventListener('input', () => {
            clearTimeout(timer);

            timer = setTimeout(loadSuggestions, 180);
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 2) {
                clearTimeout(timer);
                timer = setTimeout(loadSuggestions, 100);
            }
        });

        input.addEventListener('keydown', (event) => {
            const items = suggestionItems();

            if (box.hidden || items.length === 0) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveItem(activeIndex + 1);
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveItem(activeIndex - 1);
            }

            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                window.location.href = items[activeIndex].href;
            }

            if (event.key === 'Escape') {
                closeSuggestions();
            }
        });

        document.addEventListener('click', (event) => {
            if (!form.contains(event.target)) {
                closeSuggestions();
            }
        });
    });
});
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {
    // SMJ LISTING SHARE

    document.querySelectorAll('[data-share]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.share;
            const title = button.dataset.shareTitle || document.title;
            const originalLabel = button.dataset.shareLabel || 'Share';
            const doneLabel = button.dataset.shareDone || 'Copied';
            const label = button.querySelector('span');

            try {
                if (navigator.share) {
                    await navigator.share({
                        title,
                        url
                    });

                    return;
                }

                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(url);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = url;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';

                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    textarea.remove();
                }

                if (label) {
                    label.textContent = doneLabel;

                    setTimeout(() => {
                        label.textContent = originalLabel;
                    }, 1800);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Unable to share listing:', error);
                }
            }
        });
    });
});
</script>

</body>
</html>
