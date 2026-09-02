@extends('panel::layouts.panel', ['panelSection' => 'listings'])

@section('title', $clearOut->title)

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">{{ $clearOut->title }}</h1>
        <p class="text-muted">
            Add listings to your Clear Out, then publish it when you're ready.
        </p>
    </div>

    <span class="badge">{{ ucfirst($clearOut->status) }}</span>
</header>

@if($errors->has('clear_out'))
    <div class="alert alert--caution">
        <span>{{ $errors->first('clear_out') }}</span>
    </div>
@endif

<section class="card">
    <div class="card__head">
        <h2 class="card__title">Clear Out details</h2>
    </div>

    <div class="card__body">
        <form
            id="clear-out-form"
            method="POST"
            action="{{ route('panel.clear-outs.update', $clearOut) }}"
            class="stack stack--loose"
        >
            @csrf
            @method('PUT')

            <div class="field">
                <label class="field__label" for="title">Name</label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    class="input"
                    maxlength="150"
                    required
                    value="{{ old('title', $clearOut->title) }}"
                >
            </div>

            <div class="field">
                <label class="field__label" for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    class="textarea"
                    rows="4"
                    maxlength="4000"
                >{{ old('description', $clearOut->description) }}</textarea>
            </div>

            <div class="field__row field__row--two">
                <div class="field">
                    <label class="field__label" for="city">City / Suburb</label>
                    <input
                        id="city"
                        name="city"
                        type="text"
                        class="input"
                        value="{{ old('city', $clearOut->city) }}"
                    >
                </div>

                <div class="field">
                    <label class="field__label" for="country">Country</label>
                    <input
                        id="country"
                        name="country"
                        type="text"
                        class="input"
                        value="{{ old('country', $clearOut->country) }}"
                    >
                </div>
            </div>

            <div class="stack">
                <div>
                    <h2 class="title-card">Items in this Clear Out</h2>
                    <p class="text-muted">
                        Select any of your listings you want buyers to see together.
                    </p>
                </div>

                @if($listings->isNotEmpty())
                    <div class="data-list" style="border:1px solid var(--line-hairline);border-radius:var(--radius-lg);overflow:hidden">
                        @foreach($listings as $listing)
                            <label class="data-row" style="cursor:pointer">
                                <div class="data-row__media">
                                    @if($listing->panelPrimaryImageUrl())
                                        <img
                                            src="{{ $listing->panelPrimaryImageUrl() }}"
                                            alt=""
                                            loading="lazy"
                                        >
                                    @else
                                        <span class="listing-card__placeholder">
                                            <x-ui.icon name="image"/>
                                        </span>
                                    @endif
                                </div>

                                <div class="data-row__main">
                                    <p class="data-row__title">
                                        {{ $listing->title }}
                                    </p>

                                    <div class="data-row__meta">
                                        <span>{{ $listing->panelPriceLabel() }}</span>
                                        <span>
                                            Stock:
                                            {{ (int) $listing->quantity_available }}
                                            /
                                            {{ (int) $listing->quantity_total }}
                                        </span>
                                    </div>
                                </div>

                                <div class="data-row__actions">
                                    <input
                                        type="checkbox"
                                        name="listing_ids[]"
                                        value="{{ $listing->getKey() }}"
                                        @checked(
                                            in_array(
                                                $listing->getKey(),
                                                old(
                                                    'listing_ids',
                                                    $clearOut->listings->pluck('id')->all()
                                                )
                                            )
                                        )
                                    >
                                </div>
                            </label>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state
                        icon="tag"
                        title="No available listings"
                        text="Create some listings first, then come back and add them to this Clear Out."
                    >
                        <a href="{{ route('panel.listings.create') }}" class="button button--primary">
                            Create a listing
                        </a>
                    </x-ui.empty-state>
                @endif
            </div>

            <div class="row row--between">
                <a href="{{ route('panel.clear-outs.index') }}" class="button button--ghost">
                    Back
                </a>

                <div class="row row--wrap">
                    <button
                        type="submit"
                        name="action"
                        value="save"
                        class="button button--secondary"
                    >
                        Save Clear Out
                    </button>

                    @if($clearOut->status === \Modules\Listing\Models\ClearOut::STATUS_DRAFT)
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

@if($clearOut->status === \Modules\Listing\Models\ClearOut::STATUS_ACTIVE)
    <div class="row row--wrap">
        <form method="POST" action="{{ route('panel.clear-outs.complete', $clearOut) }}">
            @csrf
            <button type="submit" class="button button--secondary">
                Complete Clear Out
            </button>
        </form>
    </div>
@endif
@endsection
