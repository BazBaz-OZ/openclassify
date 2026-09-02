@extends('panel::layouts.panel', ['panelSection' => 'wanted'])

@section('title', 'My Wanted')

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">My Wanted</h1>
        <p class="text-muted">
            Tell local sellers what you're looking for instead of searching every day.
        </p>
    </div>

    <a href="{{ route('panel.wanted.create') }}" class="button button--primary">
        <x-ui.icon name="plus"/>
        <span>Post Wanted</span>
    </a>
</header>

@if($wantedPosts->isNotEmpty())
    <section class="card">
        <div class="data-list">

            @foreach($wantedPosts as $wanted)
                <article class="data-row">

                    <div class="data-row__media">
                        <span class="listing-card__placeholder">
                            <x-ui.icon name="search"/>
                        </span>
                    </div>

                    <div class="data-row__main">
                        <p class="data-row__title">
                            {{ $wanted->title }}
                        </p>

                        <div class="data-row__meta">
                            <span class="badge">
                                {{ ucfirst($wanted->status) }}
                            </span>

                            <span class="text-price">
                                {{ $wanted->budgetLabel() }}
                            </span>

                            @if($wanted->category)
                                <span>
                                    {{ $wanted->category->name }}
                                </span>
                            @endif

                            @if($wanted->city)
                                <span class="data-row__metric">
                                    <x-ui.icon name="map-pin"/>
                                    {{ $wanted->city }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="data-row__actions">

                        @if(in_array($wanted->status, [
                            \Modules\Listing\Models\WantedPost::STATUS_ACTIVE,
                            \Modules\Listing\Models\WantedPost::STATUS_FULFILLED,
                        ], true))
                            <a
                                href="{{ route('wanted.show', $wanted) }}"
                                class="button button--ghost button--small"
                            >
                                View
                            </a>
                        @endif

                        <a
                            href="{{ route('panel.wanted.edit', $wanted) }}"
                            class="button button--secondary button--small"
                        >
                            Edit
                        </a>
                    </div>
                </article>
            @endforeach

        </div>
    </section>

    {{ $wantedPosts->links('components.pagination') }}

@else
    <x-ui.empty-state
        icon="search"
        title="Nothing wanted yet"
        text="Looking for something specific? Post it here and let local sellers come to you."
    >
        <a
            href="{{ route('panel.wanted.create') }}"
            class="button button--primary"
        >
            Post your first Wanted
        </a>
    </x-ui.empty-state>
@endif
@endsection
