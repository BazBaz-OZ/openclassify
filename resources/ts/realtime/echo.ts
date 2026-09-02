import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { InboxMessagePayload } from '../core/types';

type InboxListener = (payload: InboxMessagePayload) => void;

declare global {
    interface Window {
        Echo?: Echo<'reverb'>;
        Pusher?: typeof Pusher;
    }
}

const listeners = new Set<InboxListener>();
let connected = false;

function isInboxPayload(value: unknown): value is InboxMessagePayload {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        typeof candidate['conversationId'] === 'number' &&
        typeof candidate['body'] === 'string' &&
        typeof candidate['senderId'] === 'number' &&
        typeof candidate['createdAt'] === 'string'
    );
}

function channelName(): string | null {
    const value = document.body.dataset['inboxChannel'];

    return value === undefined || value === '' ? null : value;
}

function echoClient(): Echo<'reverb'> | null {
    if (window.Echo !== undefined) {
        return window.Echo;
    }

    const key = import.meta.env['VITE_REVERB_APP_KEY'];

    if (!key) {
        console.warn('Reverb app key is missing.');
        return null;
    }

    const scheme = import.meta.env['VITE_REVERB_SCHEME'] ?? 'http';
    const host = import.meta.env['VITE_REVERB_HOST'] ?? window.location.hostname;
    const port = Number(import.meta.env['VITE_REVERB_PORT'] ?? 8080);

    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
}

function connect(): void {
    if (connected) {
        return;
    }

    const client = echoClient();
    const channel = channelName();

    if (client === null || channel === null) {
        return;
    }

    connected = true;

    client.private(channel)
        .listen('.inbox.message.created', (payload: unknown) => {
            if (!isInboxPayload(payload)) {
                return;
            }

            for (const listener of listeners) {
                listener(payload);
            }
        });
}

export function subscribeToInbox(listener: InboxListener): () => void {
    listeners.add(listener);
    connect();

    return (): void => {
        listeners.delete(listener);
    };
}
