@extends('panel::layouts.panel', ['panelSection' => 'wanted'])

@section('title', $wanted->title)

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">{{ $wanted->title }}</h1>

        <p class="text-muted">
            Update or manage your Wanted post.
        </p>
    </div>

    <span class="badge">
        {{ ucfirst($wanted->status) }}
    </span>
</header>

<section class="card">
    <div class="card__body">
        <form
            method="POST"
            action="{{ route('panel.wanted.update', $wanted) }}"
            class="stack stack--loose"
        >
            @csrf
            @method('PUT')

            @include('panel::wanted.partials.form')

            <div class="row row--between">

                <a
                    href="{{ route('panel.wanted.index') }}"
                    class="button button--ghost"
                >
                    Back
                </a>

                <div class="row row--wrap">

                    <button
                        type="submit"
                        name="action"
                        value="save"
                        class="button button--secondary"
                    >
                        Save
                    </button>

                    @if($wanted->status === \Modules\Listing\Models\WantedPost::STATUS_DRAFT)
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

@if($wanted->status === \Modules\Listing\Models\WantedPost::STATUS_ACTIVE)
    <div class="row row--wrap">

        <a
            href="{{ route('wanted.show', $wanted) }}"
            class="button button--primary"
        >
            View Wanted Post
        </a>

        <form
            method="POST"
            action="{{ route('panel.wanted.fulfill', $wanted) }}"
        >
            @csrf

            <button
                type="submit"
                class="button button--secondary"
            >
                I found it
            </button>
        </form>

        <form
            method="POST"
            action="{{ route('panel.wanted.cancel', $wanted) }}"
            data-confirm="Cancel this Wanted post?"
        >
            @csrf

            <button
                type="submit"
                class="button button--ghost"
            >
                Cancel Wanted
            </button>
        </form>

    </div>
@endif

@if($wanted->status === \Modules\Listing\Models\WantedPost::STATUS_FULFILLED)
    <a
        href="{{ route('wanted.show', $wanted) }}"
        class="button button--secondary"
    >
        View Wanted Post
    </a>
@endif
@endsection
