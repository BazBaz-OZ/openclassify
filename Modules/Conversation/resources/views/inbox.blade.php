@extends('panel::layouts.panel', ['panelSection' => 'inbox'])

@section('title', __('conversation::messages.inbox'))

@php
    $viewerId = (int) auth()->id();
@endphp

@section('panel_content')
<header class="panel-head">
    <div class="panel-head__text">
        <h1 class="title-page">{{ __('conversation::messages.inbox') }}</h1>
        <p class="text-muted">{{ trans_choice('site::messages.results_count', $conversations->count(), ['count' => $conversations->count()]) }}</p>
    </div>
</header>

@if($conversations->isEmpty())
    <x-ui.empty-state icon="mail" :title="__('conversation::messages.no_conversations')" :text="__('conversation::messages.no_conversations_hint')">
        <a href="{{ route('listings.index') }}" class="button button--secondary">{{ __('site::messages.browse') }}</a>
    </x-ui.empty-state>
@else
    <div class="thread-layout {{ $selectedConversation ? 'is-thread-open' : '' }}" data-inbox-pane>
        <div class="thread-layout__list">
            @foreach($conversations as $conversation)
                @php
                    $partner = $conversation->partnerFor($viewerId);
                    $unread = $conversation->unreadCountForParticipant($viewerId);
                    $isActive = $selectedConversation !== null && (int) $selectedConversation->getKey() === (int) $conversation->getKey();
                @endphp
                <a
                    href="{{ route('panel.inbox.index', ['conversation' => $conversation->getKey()]) }}"
                    class="thread-preview {{ $isActive ? 'is-active' : '' }}"
                    data-thread-open
                >
                    <span class="avatar avatar--small">{{ \App\Support\UserDirectory::initials((string) $partner?->getAttribute('name')) }}</span>
                    <span class="thread-preview__main">
                        <span class="thread-preview__name text-clamp-1">{{ $partner?->getAttribute('name') ?? '—' }}</span>
                        <span class="thread-preview__text text-clamp-1">{{ $conversation->getRelation('lastMessage')?->getAttribute('body') ?? '' }}</span>
                    </span>
                    @if($unread > 0)
                        <span class="thread-preview__badge">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="thread-layout__detail">
            @if($selectedConversation)
                @php
                    $partner = $selectedConversation->partnerFor($viewerId);
                    $listing = $selectedConversation->getRelation('listing');
                    $viewer = auth()->user();
                    $viewerBlockedPartner = $partner && $viewer->hasBlocked($partner);
                    $partnerBlockedViewer = $partner && $viewer->isBlockedBy($partner);
                    $messagingBlocked = $viewerBlockedPartner || $partnerBlockedViewer;
                @endphp

                <div
                    class="thread-view"
                    data-inbox-thread="{{ $selectedConversation->getKey() }}"
                    data-thread-endpoint="{{ route('conversations.messages.send', $selectedConversation) }}"
                >
                    <div class="thread__head">
                        <button type="button" class="icon-button thread-back" data-thread-back aria-label="{{ __('conversation::messages.back') }}">
                            <x-ui.icon name="arrow-left"/>
                        </button>
                        <span class="avatar avatar--small">{{ \App\Support\UserDirectory::initials((string) $partner?->getAttribute('name')) }}</span>
                        <div class="stack" style="gap:0;min-width:0">
                            <span class="thread-preview__name">{{ $partner?->getAttribute('name') ?? '—' }}</span>
                            @if($listing)
                                <a href="{{ route('listings.show', $listing) }}" class="text-meta text-clamp-1">{{ $listing->getAttribute('title') }}</a>
                            @endif
                        </div>

                        <div class="row row--wrap" style="margin-inline-start:auto">
                            <button
                                type="button"
                                class="button button--ghost button--small"
                                data-reveal-target="conversation-report-{{ $selectedConversation->getKey() }}"
                            >
                                <x-ui.icon name="flag"/>
                                <span>Report user</span>
                            </button>

                            @if($viewerBlockedPartner)
                                <form method="POST" action="{{ route('conversations.unblock', $selectedConversation) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button button--secondary button--small">
                                        Unblock user
                                    </button>
                                </form>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('conversations.block', $selectedConversation) }}"
                                    onsubmit="return confirm('Block this user? They will no longer be able to message you.');"
                                >
                                    @csrf
                                    <button type="submit" class="button button--secondary button--small">
                                        Block user
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if($partner)
                        <form
                            method="POST"
                            action="{{ route('reports.store') }}"
                            id="conversation-report-{{ $selectedConversation->getKey() }}"
                            class="stack stack--tight"
                            style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--line-hairline);"
                            hidden
                        >
                            @csrf

                            <input type="hidden" name="subject_type" value="user">
                            <input type="hidden" name="subject_id" value="{{ $partner->getKey() }}">

                            <div class="field__row field__row--two">
                                <div class="field">
                                    <label class="field__label" for="conversation-report-reason">
                                        {{ __('report::messages.reason') }}
                                    </label>

                                    <select
                                        id="conversation-report-reason"
                                        name="reason"
                                        class="select"
                                        required
                                    >
                                        @foreach(\Modules\Report\Models\Report::reasons() as $reason)
                                            <option value="{{ $reason }}">
                                                {{ __('report::messages.reason_'.$reason) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="field">
                                    <label class="field__label" for="conversation-report-details">
                                        {{ __('report::messages.details') }}
                                    </label>

                                    <textarea
                                        id="conversation-report-details"
                                        name="details"
                                        class="textarea"
                                        rows="2"
                                        maxlength="1000"
                                        placeholder="{{ __('report::messages.placeholder') }}"
                                    ></textarea>
                                </div>
                            </div>

                            <button type="submit" class="button button--critical button--small">
                                <x-ui.icon name="flag"/>
                                <span>{{ __('report::messages.submit') }}</span>
                            </button>
                        </form>
                    @endif

                    <ul class="thread__messages" data-thread-messages>
                        @foreach($selectedConversation->getRelation('messages') as $message)
                            @php $outgoing = (int) $message->getAttribute('sender_id') === $viewerId; @endphp
                            <li class="thread__bubble {{ $outgoing ? 'thread__bubble--out' : 'thread__bubble--in' }}">
                                <p class="thread__text">{{ $message->getAttribute('body') }}</p>
                                <time class="thread__time" datetime="{{ $message->getAttribute('created_at')?->toIso8601String() }}">
                                    {{ $message->getAttribute('created_at')?->format('H:i') }}
                                </time>
                            </li>
                        @endforeach
                    </ul>

                    @if(! empty($quickMessages))
                        <div class="thread__quick">
                            @foreach($quickMessages as $quick)
                                <button type="button" class="pill" data-thread-quick="{{ $quick }}">{{ $quick }}</button>
                            @endforeach
                        </div>
                    @endif

                    <form class="thread__composer" data-thread-form>
                        @csrf
                        <label class="visually-hidden" for="thread-input">{{ __('conversation::messages.message_placeholder') }}</label>
                        <textarea id="thread-input" class="textarea" rows="1" placeholder="{{ __('conversation::messages.message_placeholder') }}" data-thread-input maxlength="2000"></textarea>
                        <button type="submit" class="button button--primary button--icon" data-thread-submit aria-label="{{ __('conversation::messages.send') }}">
                            <x-ui.icon name="arrow-right"/>
                        </button>
                    </form>
                </div>
            @else
                <div class="card__body" style="margin:auto;text-align:center">
                    <p class="text-muted">{{ __('conversation::messages.select_conversation') }}</p>
                </div>
            @endif
        </div>
    </div>
@endif
@endsection
