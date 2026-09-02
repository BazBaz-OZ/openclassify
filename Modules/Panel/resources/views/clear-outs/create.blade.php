@extends('panel::layouts.panel', ['panelSection' => 'listings'])

@section('title', 'Start a Clear Out')

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">Start a Clear Out</h1>
        <p class="text-muted">
            Garage clean-out, moving house, renovation leftovers or business surplus — put it all in one place.
        </p>
    </div>
</header>

<section class="card">
    <div class="card__body">
        <form method="POST" action="{{ route('panel.clear-outs.store') }}" class="stack stack--loose">
            @csrf

            <div class="field">
                <label class="field__label" for="title">Clear Out name</label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    class="input"
                    maxlength="150"
                    required
                    value="{{ old('title') }}"
                    placeholder="e.g. Garage Clear-Out"
                >
                @error('title')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    class="textarea"
                    rows="5"
                    maxlength="4000"
                    placeholder="Tell buyers what you're clearing out..."
                >{{ old('description') }}</textarea>
            </div>

            <div class="field__row field__row--two">
                <div class="field">
                    <label class="field__label" for="city">City / Suburb</label>
                    <input
                        id="city"
                        name="city"
                        type="text"
                        class="input"
                        value="{{ old('city') }}"
                        placeholder="Springfield Lakes"
                    >
                </div>

                <div class="field">
                    <label class="field__label" for="country">Country</label>
                    <input
                        id="country"
                        name="country"
                        type="text"
                        class="input"
                        value="{{ old('country', 'Australia') }}"
                    >
                </div>
            </div>

            <div class="row row--between">
                <a href="{{ route('panel.clear-outs.index') }}" class="button button--ghost">
                    Cancel
                </a>

                <button type="submit" class="button button--primary">
                    Create Clear Out
                </button>
            </div>
        </form>
    </div>
</section>
@endsection
