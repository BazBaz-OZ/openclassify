@extends('site::layouts.app')

@section('title', 'Wanted')

@section('description', 'See what people nearby are looking to buy.')

@section('content')
<div class="shell shell--wide page">
    <div class="stack stack--loose">

        <header class="panel-head">
            <div class="panel-head__text">
                <h1 class="title-page">
                    Wanted
                </h1>

                <p class="text-muted">
                    See what people are looking for. You might already have it sitting around.
                </p>
            </div>

            @auth
                <a
                    href="{{ route('panel.wanted.create') }}"
                    class="button button--primary"
                >
                    Post Wanted
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="button button--primary"
                >
                    Post Wanted
                </a>
            @endauth
        </header>

        @if($wantedPosts->isNotEmpty())

            <div class="listing-grid">

                @foreach($wantedPosts as $wanted)

                    <article class="card">
                        <div class="card__body">
                            <div class="stack stack--tight">

                                <div class="row row--between row--wrap">
                                    <span class="badge badge--solid">
                                        WANTED
                                    </span>

                                    <span class="text-price">
                                        {{ $wanted->budgetLabel() }}
                                    </span>
                                </div>

                                <h2 class="card__title">
                                    <a
                                        href="{{ route('wanted.show', $wanted) }}"
                                        class="text-link"
                                    >
                                        {{ $wanted->title }}
                                    </a>
                                </h2>

                                @if($wanted->category)
                                    <p class="text-muted">
                                        {{ $wanted->category->name }}
                                    </p>
                                @endif

                                @if(filled($wanted->description))
                                    <p>
                                        {{ \Illuminate\Support\Str::limit(
                                            $wanted->description,
                                            140
                                        ) }}
                                    </p>
                                @endif

                                <div class="row row--wrap text-meta">

                                    @if($wanted->city)
                                        <span>
                                            {{ $wanted->city }}
                                        </span>
                                    @endif

                                    @if($wanted->published_at)
                                        <span>
                                            {{ $wanted->published_at->diffForHumans() }}
                                        </span>
                                    @endif

                                </div>

                                <a
                                    href="{{ route('wanted.show', $wanted) }}"
                                    class="button button--secondary button--small"
                                >
                                    View Wanted
                                </a>

                            </div>
                        </div>
                    </article>

                @endforeach

            </div>

            {{ $wantedPosts->links('components.pagination') }}

        @else

            <x-ui.empty-state
                icon="search"
                title="No active Wanted posts"
                text="Be the first person to tell local sellers what you're looking for."
            >
                @auth
                    <a
                        href="{{ route('panel.wanted.create') }}"
                        class="button button--primary"
                    >
                        Post Wanted
                    </a>
                @endauth
            </x-ui.empty-state>

        @endif

    </div>
</div>
@endsection
