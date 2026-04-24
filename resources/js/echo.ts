import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

const isBrowser = typeof window !== 'undefined';

function csrfToken(): string {
    if (!isBrowser) {
        return '';
    }
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function createEcho(): Echo<'reverb'> | null {
    if (!isBrowser) {
        return null;
    }

    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;
    if (typeof reverbKey !== 'string' || reverbKey.length === 0) {
        return null;
    }

    const host = (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? '127.0.0.1';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
    const scheme = (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'http';

    window.Pusher = Pusher;

    return new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
        },
    });
}

export const echo = createEcho();

export type PresenceMember = { id: number; name: string };

export type SukienRoundPayload = {
    eventRoomId: number;
    eventRoundId: number;
    roundNumber: number;
    presetOption: { id: number; label: string; bg_color: string; text_color: string };
    autoEndAt?: string | null;
};

export type SukienOptionStat = {
    optionId: number;
    betsCount: number;
    totalAmountVnd: number;
};

export type SukienStatsPayload = {
    eventRoomId: number;
    eventRoundId: number;
    betsCount: number;
    totalAmountVnd: number;
    perOption?: SukienOptionStat[];
};

export function subscribeSukienPublicChannel(
    roomId: number,
    handlers: {
        onRoundStarted?: (p: SukienRoundPayload) => void;
        onRoundEnded?: (p: SukienRoundPayload) => void;
        onStats?: (p: SukienStatsPayload) => void;
    },
): () => void {
    if (echo === null) {
        return () => {};
    }
    const ch = echo.channel(`sukien-room.${roomId}`);
    ch.listen('.sukien.round.started', (e: SukienRoundPayload) => {
        handlers.onRoundStarted?.(e);
    });
    ch.listen('.sukien.round.ended', (e: SukienRoundPayload) => {
        handlers.onRoundEnded?.(e);
    });
    ch.listen('.sukien.room.stats', (e: SukienStatsPayload) => {
        handlers.onStats?.(e);
    });
    return () => {
        echo?.leave(`sukien-room.${roomId}`);
    };
}

/**
 * Đếm số người trong phòng (presence) — cần user đã đăng nhập.
 */
export function joinSukienPresence(
    roomId: number,
    handlers: {
        onHere?: (members: PresenceMember[]) => void;
        onJoining?: (member: PresenceMember) => void;
        onLeaving?: (member: PresenceMember) => void;
    },
): () => void {
    if (echo === null) {
        return () => {};
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (echo.join(`sukien-presence.${roomId}`) as any)
        .here((users: PresenceMember[]) => handlers.onHere?.(users))
        .joining((user: PresenceMember) => handlers.onJoining?.(user))
        .leaving((user: PresenceMember) => handlers.onLeaving?.(user));
    return () => {
        echo?.leave(`sukien-presence.${roomId}`);
    };
}
