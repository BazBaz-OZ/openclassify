@extends('site::layouts.app')

@section('title', 'Membership & Pricing')

@section('description')
Choose the Sell My Junk plan that suits how much you sell.
@endsection

@section('content')
@php
    $plans = config('membership.plans', []);
    $user = auth()->user();

    $entitlement = $user
        ? app(\Modules\Listing\Support\AiEntitlement::class)
        : null;

    $currentPlan = $entitlement
        ? $entitlement->plan($user)
        : 'free';
@endphp

<style>
    .membership-page {
        padding: 48px 0 72px;
    }

    .membership-hero {
        max-width: 760px;
        margin: 0 auto 36px;
        text-align: center;
    }

    .membership-hero__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        padding: 7px 12px;
        border-radius: 999px;
        background: #eef9f0;
        color: #187a28;
        font-size: 13px;
        font-weight: 700;
    }

    .membership-hero h1 {
        margin: 0 0 12px;
        font-size: clamp(32px, 5vw, 48px);
        line-height: 1.1;
    }

    .membership-hero p {
        margin: 0;
        color: #666;
        font-size: 17px;
        line-height: 1.6;
    }

    .membership-grid {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: 20px;
        align-items: stretch;
    }

    .membership-card {
        position: relative;
        display: flex;
        flex-direction: column;
        min-height: 100%;
        padding: 28px;
        border: 1px solid #ddd;
        border-radius: 18px;
        background: #fff;
    }

    .membership-card--featured {
        border: 2px solid #39b54a;
        box-shadow:
            0 14px 40px rgba(57, 181, 74, .14);
    }

    .membership-card__badge {
        position: absolute;
        top: -13px;
        left: 24px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #39b54a;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
    }

    .membership-card__name {
        margin: 0 0 8px;
        font-size: 24px;
        font-weight: 750;
    }

    .membership-card__price {
        display: flex;
        align-items: baseline;
        gap: 5px;
        margin-bottom: 18px;
    }

    .membership-card__price strong {
        font-size: 36px;
        line-height: 1;
    }

    .membership-card__price span {
        color: #777;
    }

    .membership-card__features {
        display: grid;
        gap: 12px;
        margin: 0 0 26px;
        padding: 0;
        list-style: none;
    }

    .membership-card__features li {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        line-height: 1.45;
    }

    .membership-card__features svg {
        flex: 0 0 auto;
        width: 18px;
        height: 18px;
        margin-top: 2px;
        color: #39b54a;
    }

    .membership-card__action {
        margin-top: auto;
    }

    .membership-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 46px;
        padding: 11px 16px;
        border: 0;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        cursor: pointer;
    }

    .membership-button--primary {
        background: #39b54a;
        color: #fff;
    }

    .membership-button--secondary {
        background: #111;
        color: #fff;
    }

    .membership-button--current {
        background: #eef9f0;
        color: #187a28;
        cursor: default;
    }

    .membership-note {
        margin-top: 28px;
        text-align: center;
        color: #777;
        font-size: 14px;
    }

    @media (max-width: 900px) {
        .membership-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="membership-page">
    <div class="shell">

        @if(request('checkout') === 'success')
            <div
                class="alert alert--positive"
                style="margin-bottom:24px;"
            >
                <x-ui.icon name="check"/>
                <span>
                    Payment completed. Your membership
                    may take a few seconds to update.
                </span>
            </div>
        @elseif(request('checkout') === 'cancelled')
            <div
                class="alert alert--caution"
                style="margin-bottom:24px;"
            >
                <span>Checkout cancelled. No charge was made.</span>
            </div>
        @endif
        <div class="membership-hero">
            <div class="membership-hero__eyebrow">
                <x-ui.icon name="sparkle"/>
                Sell smarter with AI
            </div>

            <h1>Membership & Pricing</h1>

            <p>
                Start free, then upgrade when you need more AI scans
                and more Virtual Garage capacity.
            </p>
        </div>

        <div class="membership-grid">
            @foreach($plans as $key => $plan)
                @php
                    $isCurrent = $currentPlan === $key;
                    $isFeatured = $key === 'member';
                    $price = (float) $plan['price_monthly'];
                @endphp

                <article
                    class="
                        membership-card
                        {{ $isFeatured
                            ? 'membership-card--featured'
                            : '' }}
                    "
                >
                    @if($isFeatured)
                        <span class="membership-card__badge">
                            Most popular
                        </span>
                    @endif

                    <h2 class="membership-card__name">
                        {{ $plan['name'] }}
                    </h2>

                    <div class="membership-card__price">
                        <strong>
                            ${{ number_format($price, 2) }}
                        </strong>

                        @if($price > 0)
                            <span>/ month</span>
                        @endif
                    </div>

                    <ul class="membership-card__features">
                        <li>
                            <x-ui.icon name="check"/>

                            <span>
                                <strong>
                                    {{ $plan['ai_scans'] }}
                                </strong>
                                AI scans
                                {{ $plan['ai_period'] === 'monthly'
                                    ? 'per month'
                                    : 'lifetime' }}
                            </span>
                        </li>

                        <li>
                            <x-ui.icon name="check"/>

                            <span>
                                <strong>
                                    {{ $plan['active_virtual_garages'] }}
                                </strong>
                                active Virtual
                                {{ \Illuminate\Support\Str::plural(
                                    'Garage',
                                    $plan['active_virtual_garages']
                                ) }}
                            </span>
                        </li>

                        <li>
                            <x-ui.icon name="check"/>

                            <span>
                                Up to
                                <strong>
                                    {{ $plan['virtual_garage_photos'] }}
                                </strong>
                                photos per Virtual Garage
                            </span>
                        </li>

                        <li>
                            <x-ui.icon name="check"/>

                            <span>
                                Unlimited standard manual listings
                            </span>
                        </li>
                    </ul>

                    <div class="membership-card__action">
                        @if($isCurrent)
                            <span
                                class="
                                    membership-button
                                    membership-button--current
                                "
                            >
                                Current plan
                            </span>
                        @elseif($key === 'free')
                            <span
                                class="
                                    membership-button
                                    membership-button--current
                                "
                            >
                                Free
                            </span>
                        @else
                            @auth
                                <form
                                    method="POST"
                                    action="{{ route(
                                        'membership.checkout',
                                        $key
                                    ) }}"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="
                                            membership-button
                                            {{ $isFeatured
                                                ? 'membership-button--primary'
                                                : 'membership-button--secondary'
                                            }}
                                        "
                                    >
                                        Upgrade to
                                        {{ $plan['name'] }}
                                    </button>
                                </form>
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="
                                        membership-button
                                        {{ $isFeatured
                                            ? 'membership-button--primary'
                                            : 'membership-button--secondary'
                                        }}
                                    "
                                >
                                    Sign in to upgrade
                                </a>
                            @endauth
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <p class="membership-note">
            AI limits apply per account. Failed AI scans do not
            use your allowance.
        </p>
    </div>
</div>
@endsection
