<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Conversation\App\Events\ConversationReadUpdated;
use Modules\Conversation\App\Events\InboxMessageCreated;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationMessage;
use Modules\Conversation\App\Support\QuickMessageCatalog;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\WantedPost;
use Modules\Listing\Support\WantedMatcher;
use Modules\User\App\Models\User;

class ConversationController extends Controller
{
    public function inbox(Request $request): View
    {
        $user = $request->user();
        $userId = $user ? (int) $user->getKey() : null;
        $requiresLogin = ! $user;
        $messageFilter = $this->resolveMessageFilter($request);

        $conversations = collect();
        $selectedConversation = null;

        if ($userId) {
            [
                'conversations' => $conversations,
                'selectedConversation' => $selectedConversation,
                'markedRead' => $markedRead,
            ] = $this->resolveInboxState(
                $userId,
                $messageFilter,
                $request->integer('conversation'),
                true,
            );

            if ($selectedConversation && $markedRead) {
                try {
                    $pendingBroadcast = broadcast(new ConversationReadUpdated(
                        $userId,
                        $selectedConversation->readPayloadFor($userId),
                    ));

                    unset($pendingBroadcast);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        return view('conversation::inbox', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messageFilter' => $messageFilter,
            'quickMessages' => QuickMessageCatalog::all(),
            'requiresLogin' => $requiresLogin,
        ]);
    }

    public function start(Request $request, Listing $listing): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($listing->statusValue() === 'sold') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This listing has been sold.'], 422);
            }

            return back()->with('error', 'This listing has been sold and is no longer accepting new messages.');
        }

        if (! $listing->user_id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'A conversation cannot be started for this listing.'], 422);
            }

            return back()->with('error', 'A conversation cannot be started for this listing.');
        }

        if ((int) $listing->user_id === (int) $user->getKey()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You cannot message your own listing.'], 422);
            }

            return back()->with('error', 'You cannot message your own listing.');
        }

        $seller = User::query()->find((int) $listing->user_id);

        if (! $seller || $user->messagingBlockedWith($seller)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Messaging with this user is unavailable.'], 403);
            }

            return back()->with('error', 'Messaging with this user is unavailable.');
        }

        $messageBody = trim((string) $request->string('message'));

        if ($request->expectsJson() && $messageBody === '') {
            return response()->json(['message' => 'Message cannot be empty.'], 422);
        }

        $conversation = Conversation::openForListingBuyer($listing, (int) $user->getKey());
        $user->rememberListing($listing);

        $message = null;
        if ($messageBody !== '') {
            $message = $conversation->createMessageFor((int) $user->getKey(), $messageBody);
            $this->broadcastMessageCreated($conversation, $message, (int) $user->getKey());
        }

        if ($request->expectsJson()) {
            return $this->conversationJsonResponse($conversation, $message, (int) $user->getKey());
        }

        return redirect()
            ->route('panel.inbox.index', array_merge($this->inboxFilters($request), ['conversation' => $conversation->getKey()]))
            ->with('success', $messageBody !== '' ? 'Message sent.' : 'Conversation started.');
    }

    public function startFromWanted(
        Request $request,
        Listing $listing,
        WantedPost $wanted
    ): RedirectResponse {
        $user = $request->user();
        $userId = (int) $user->getKey();

        if ((int) $listing->user_id !== $userId) {
            abort(403);
        }

        if ($listing->statusValue() !== 'active') {
            return back()->with(
                'error',
                'Only active listings can contact Wanted buyers.'
            );
        }

        if ($wanted->status !== WantedPost::STATUS_ACTIVE) {
            return back()->with(
                'error',
                'This Wanted post is no longer active.'
            );
        }

        if ((int) $wanted->user_id === $userId) {
            return back()->with(
                'error',
                'You cannot contact yourself.'
            );
        }

        $matches = WantedMatcher::wantedForListing(
            $listing,
            50
        );

        if (! $matches->contains(
            fn (WantedPost $match): bool =>
                (int) $match->getKey() === (int) $wanted->getKey()
        )) {
            return back()->with(
                'error',
                'This Wanted post no longer matches your listing.'
            );
        }

        $buyer = User::query()->find((int) $wanted->user_id);

        if (! $buyer || $user->messagingBlockedWith($buyer)) {
            return back()->with(
                'error',
                'Messaging with this user is unavailable.'
            );
        }

        $conversation = Conversation::openForListingBuyer(
            $listing,
            (int) $wanted->user_id
        );

        return redirect()
            ->route('panel.inbox.index', [
                'conversation' => $conversation->getKey(),
            ])
            ->with(
                'success',
                'Conversation opened with the Wanted buyer.'
            );
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        if ((int) $conversation->buyer_id !== $userId && (int) $conversation->seller_id !== $userId) {
            abort(403);
        }

        $partner = $conversation->partnerFor($userId);

        if (! $partner || $user->messagingBlockedWith($partner)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Messaging with this user is unavailable.'], 403);
            }

            return back()->with('error', 'Messaging with this user is unavailable.');
        }

        $payload = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $messageBody = trim($payload['message']);

        if ($messageBody === '') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Message cannot be empty.'], 422);
            }

            return back()->with('error', 'Message cannot be empty.');
        }

        $message = $conversation->createMessageFor($userId, $messageBody);
        $this->broadcastMessageCreated($conversation, $message, $userId);

        if ($request->expectsJson()) {
            return $this->conversationJsonResponse($conversation, $message, $userId);
        }

        return redirect()
            ->route('panel.inbox.index', array_merge($this->inboxFilters($request), ['conversation' => $conversation->getKey()]))
            ->with('success', 'Message sent.');
    }

    public function block(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        abort_unless($conversation->hasParticipant($userId), 403);

        $partner = $conversation->partnerFor($userId);
        abort_unless($partner, 404);

        $user->blockUser($partner);

        return redirect()
            ->route('panel.inbox.index', ['conversation' => $conversation->getKey()])
            ->with('success', 'User blocked.');
    }

    public function unblock(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        abort_unless($conversation->hasParticipant($userId), 403);

        $partner = $conversation->partnerFor($userId);
        abort_unless($partner, 404);

        $user->unblockUser($partner);

        return redirect()
            ->route('panel.inbox.index', ['conversation' => $conversation->getKey()])
            ->with('success', 'User unblocked.');
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        abort_unless($conversation->hasParticipant($userId), 403);

        $updated = $conversation->markAsReadFor($userId);
        $payload = $conversation->readPayloadFor($userId);

        if ($updated > 0) {
            try {
                $pendingBroadcast = broadcast(new ConversationReadUpdated($userId, $payload));
                $pendingBroadcast->toOthers();
                unset($pendingBroadcast);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json($payload);
    }

    private function conversationJsonResponse(Conversation $conversation, ?ConversationMessage $message, int $userId): JsonResponse
    {
        return response()->json([
            'conversation_id' => (int) $conversation->getKey(),
            'send_url' => route('conversations.messages.send', $conversation),
            'read_url' => route('conversations.read', $conversation),
            'conversation' => [
                'id' => (int) $conversation->getKey(),
                'unread_count' => $conversation->unreadCountForParticipant($userId),
            ],
            'counts' => [
                'unread_messages_total' => Conversation::unreadCountForUser($userId),
            ],
            'message' => $message ? $this->messagePayload($message, $userId) : null,
        ]);
    }

    private function messagePayload(ConversationMessage $message, int $userId): array
    {
        return $message->toRealtimePayloadFor($userId);
    }

    private function inboxFilters(Request $request): array
    {
        $messageFilter = $this->resolveMessageFilter($request);

        return $messageFilter === 'all' ? [] : ['message_filter' => $messageFilter];
    }

    private function resolveMessageFilter(Request $request): string
    {
        $messageFilter = (string) $request->string('message_filter', 'all');

        return in_array($messageFilter, ['all', 'unread', 'important'], true) ? $messageFilter : 'all';
    }

    private function resolveInboxState(
        int $userId,
        string $messageFilter,
        ?int $conversationId,
        bool $markSelectedRead,
    ): array {
        $conversations = Conversation::inboxForUser($userId, $messageFilter);
        $selectedConversation = Conversation::resolveSelected($conversations, $conversationId);
        $markedRead = false;

        if ($selectedConversation) {
            $selectedConversation->loadThread();

            if ($markSelectedRead) {
                $markedRead = $selectedConversation->markAsReadFor($userId) > 0;
                $selectedConversation->unread_count = 0;

                $conversations = $conversations->map(function (Conversation $conversation) use ($selectedConversation): Conversation {
                    if ((int) $conversation->getKey() === (int) $selectedConversation->getKey()) {
                        $conversation->unread_count = 0;
                    }

                    return $conversation;
                });
            }
        }

        return [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'markedRead' => $markedRead,
        ];
    }

    private function renderInboxList($conversations, string $messageFilter, ?Conversation $selectedConversation): string
    {
        return view('conversation::partials.inbox-list-pane', [
            'conversations' => $conversations,
            'messageFilter' => $messageFilter,
            'selectedConversation' => $selectedConversation,
        ])->render();
    }

    private function renderInboxThread(?Conversation $selectedConversation, string $messageFilter): string
    {
        return view('conversation::partials.inbox-thread-pane', [
            'selectedConversation' => $selectedConversation,
            'messageFilter' => $messageFilter,
            'quickMessages' => QuickMessageCatalog::all(),
        ])->render();
    }

    private function broadcastMessageCreated(
        Conversation $conversation,
        ConversationMessage $message,
        int $senderId,
    ): void {
        foreach ($conversation->participantIds() as $participantId) {
            $event = new InboxMessageCreated(
                $participantId,
                [
                    'conversationId' => (int) $conversation->getKey(),
                    'body' => (string) $message->body,
                    'senderId' => (int) $message->sender_id,
                    'createdAt' => $message->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ],
            );

            try {
                $pendingBroadcast = broadcast($event);

                if ($participantId === $senderId) {
                    $pendingBroadcast->toOthers();
                }

                // Force PendingBroadcast to dispatch inside the try/catch.
                unset($pendingBroadcast);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
