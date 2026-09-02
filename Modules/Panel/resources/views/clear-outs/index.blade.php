@extends('panel::layouts.panel', ['panelSection' => 'listings'])

@section('title', 'My Clear Outs')

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">My Clear Outs</h1>
        <p class="text-muted">
            Group your unwanted items together and let buyers browse the whole clear-out.
        </p>
    </div>

    <a href="{{ route('panel.clear-outs.create') }}" class="button button--primary">
        <x-ui.icon name="plus"/>
        <span>Start a Clear Out</span>
    </a>
</header>

@if($clearOuts->isNotEmpty())
    <section class="card">
        <div class="data-list">
            @foreach($clearOuts as $clearOut)
                <article class="data-row">
                    <div class="data-row__media">
                        <span class="listing-card__placeholder">
                            <x-ui.icon name="tag"/>
                        </span>
                    </div>

                    <div class="data-row__main">
                        <p class="data-row__title">
                            {{ $clearOut->title }}
                        </p>

                        <div class="data-row__meta">
                            <span class="badge">
                                {{ ucfirst($clearOut->status) }}
                            </span>

                            <span class="data-row__metric">
                                {{ $clearOut->listings_count }} items
                            </span>

                            <span class="data-row__metric">
                                {{ $clearOut->available_listings_count }} available
                            </span>

                            <span class="data-row__metric">
                                {{ $clearOut->sold_listings_count }} sold
                            </span>

                            @if($clearOut->city)
                                <span class="data-row__metric">
                                    <x-ui.icon name="map-pin"/>
                                    {{ $clearOut->city }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="data-row__actions">
                        @if(in_array($clearOut->status, [
                            \Modules\Listing\Models\ClearOut::STATUS_ACTIVE,
                            \Modules\Listing\Models\ClearOut::STATUS_COMPLETED,
                        ], true))
                            <a
                                href="{{ route('clear-outs.show', $clearOut) }}"
                                class="button button--ghost button--small"
                            >
                                View
                            </a>
                        @endif

                        <a
                            href="{{ route('panel.clear-outs.edit', $clearOut) }}"
                            class="button button--secondary button--small"
                        >
                            Edit
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{ $clearOuts->links('components.pagination') }}
@else
    <x-ui.empty-state
        icon="tag"
        title="No Clear Outs yet"
        text="Clearing a garage, room, workshop or renovation? Put the items together into one easy-to-browse Clear Out."
    >
        <a href="{{ route('panel.clear-outs.create') }}" class="button button--primary">
            Start your first Clear Out
        </a>
    </x-ui.empty-state>
@endif
@endsection
