@extends('panel::layouts.panel', ['panelSection' => 'wanted'])

@section('title', 'Post Wanted')

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">Post Wanted</h1>
        <p class="text-muted">
            Tell sellers exactly what you're looking for.
        </p>
    </div>
</header>

<section class="card">
    <div class="card__body">
        <form
            method="POST"
            action="{{ route('panel.wanted.store') }}"
            class="stack stack--loose"
        >
            @csrf

            @include('panel::wanted.partials.form')

            <div class="row row--between">
                <a
                    href="{{ route('panel.wanted.index') }}"
                    class="button button--ghost"
                >
                    Cancel
                </a>

                <div class="row row--wrap">
                    <button
                        type="submit"
                        name="action"
                        value="save"
                        class="button button--secondary"
                    >
                        Save Draft
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="publish"
                        class="button button--primary"
                    >
                        Save & Publish
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection
