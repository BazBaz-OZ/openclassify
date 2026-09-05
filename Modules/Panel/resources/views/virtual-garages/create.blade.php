@extends('panel::layouts.panel', ['panelSection' => 'virtual-garages'])

@section('title', 'Start Virtual Garage')

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">Start a Virtual Garage</h1>

        <p class="text-muted">
            Create your virtual sale first, then choose the items you want to include.
        </p>
    </div>
</header>

<section class="card">
    <div class="card__body">

        <form
            method="POST"
            action="{{ route('panel.virtual-garages.store') }}"
            class="stack stack--loose"
        >
            @csrf

            <div class="field">
                <label class="field__label" for="title">
                    Garage sale name
                </label>

                <input
                    id="title"
                    name="title"
                    type="text"
                    maxlength="150"
                    required
                    class="input"
                    value="{{ old('title') }}"
                    placeholder="e.g. Baz's Garage Clean-Out"
                >

                @error('title')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    maxlength="4000"
                    class="textarea"
                    placeholder="Tell buyers what you're clearing out..."
                >{{ old('description') }}</textarea>
            </div>

            <div class="field__row field__row--three">
                @include(
                    'panel::virtual-garages.partials.location-fields',
                    [
                        'garageSuburb' =>
                            $defaultCity ?? '',
                        'garageCountry' =>
                            $defaultCountry ?? 'Australia',
                    ]
                )
            </div>

            <div class="row row--between">
                <a
                    href="{{ route('panel.virtual-garages.index') }}"
                    class="button button--ghost"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="button button--primary"
                >
                    Create Virtual Garage
                </button>
            </div>

        </form>
    </div>
</section>
@endsection
