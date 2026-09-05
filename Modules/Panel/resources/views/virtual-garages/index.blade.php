@extends('panel::layouts.panel', ['panelSection' => 'virtual-garages'])

@section('title', 'My Virtual Garages')

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">My Virtual Garages</h1>
        <p class="text-muted">
            Turn a garage, shed, room or pile of unwanted items into one easy-to-shop virtual sale.
        </p>
    </div>

    <a
        href="{{ route('panel.virtual-garages.create') }}"
        class="button button--primary"
    >
        <x-ui.icon name="plus"/>
        <span>Start Virtual Garage</span>
    </a>
</header>

@if($garages->isNotEmpty())
    <section class="card">
        <div class="data-list">
            @foreach($garages as $garage)
                <article class="data-row">

                    <div class="data-row__media">
                        <span class="listing-card__placeholder">
                            <x-ui.icon name="tag"/>
                        </span>
                    </div>

                    <div class="data-row__main">
                        <p class="data-row__title">
                            {{ $garage->title }}
                        </p>

                        <div class="data-row__meta">
                            <span class="badge">
                                {{ ucfirst($garage->status) }}
                            </span>

                            <span>
                                {{ $garage->listings_count }}
                                items
                            </span>

                            <span>
                                {{ $garage->available_listings_count }}
                                available
                            </span>

                            <span>
                                {{ $garage->sold_listings_count }}
                                sold
                            </span>
                        </div>
                    </div>

                    <div class="data-row__actions">
                        @if(
                            in_array(
                                $garage->status,
                                [
                                    \Modules\Listing\Models\VirtualGarage::STATUS_ACTIVE,
                                    \Modules\Listing\Models\VirtualGarage::STATUS_COMPLETED,
                                ],
                                true
                            )
                        )
                            <a
                                href="{{ route('virtual-garages.show', $garage) }}"
                                class="button button--secondary button--small"
                            >
                                View
                            </a>
                        @endif

                        <a
                            href="{{ route('panel.virtual-garages.edit', $garage) }}"
                            class="button button--secondary button--small"
                        >
                            Edit
                        </a>
                    </div>

                </article>
            @endforeach
        </div>
    </section>

    {{ $garages->links('components.pagination') }}
@else
    <x-ui.empty-state
        icon="tag"
        title="No Virtual Garages yet"
        text="Start by creating a garage and adding some of your existing listings."
    >
        <a
            href="{{ route('panel.virtual-garages.create') }}"
            class="button button--primary"
        >
            Start your first Virtual Garage
        </a>
    </x-ui.empty-state>
@endif
@endsection
