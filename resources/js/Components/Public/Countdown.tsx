import { useEffect, useRef, useState } from 'react';

interface CountdownProps {
    /** Seconds remaining as of the moment the server rendered this page. */
    initialSeconds: number;
    onExpire?: () => void;
}

/**
 * The payment-hold shot clock.
 *
 * Ticks from the server's number rather than a client-guessed one, so a phone
 * with a wrong clock still shows the real time left. An ARIA live region
 * announces state changes for anyone using a screen reader, without reading
 * out every single second.
 */
export default function Countdown({ initialSeconds, onExpire }: CountdownProps) {
    const [secondsLeft, setSecondsLeft] = useState(Math.max(0, initialSeconds));
    const hasExpired = useRef(false);

    useEffect(() => {
        if (secondsLeft <= 0) {
            if (!hasExpired.current) {
                hasExpired.current = true;
                onExpire?.();
            }

            return;
        }

        const timer = window.setInterval(() => {
            setSecondsLeft((value) => Math.max(0, value - 1));
        }, 1000);

        return () => window.clearInterval(timer);
    }, [secondsLeft > 0]);

    const minutes = Math.floor(secondsLeft / 60);
    const seconds = secondsLeft % 60;
    const label = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    const isUrgent = secondsLeft <= 60 && secondsLeft > 0;

    return (
        <div
            role="timer"
            aria-live="polite"
            aria-atomic="true"
            className={`font-score inline-flex items-baseline gap-2 rounded-xl border-2 px-4 py-2 text-3xl font-semibold transition-colors ${
                isUrgent
                    ? 'border-red-300 bg-red-50 text-red-700'
                    : 'border-brand/20 bg-brand-tint text-brand-strong'
            }`}
        >
            <span>{label}</span>
            <span className="text-sm font-normal font-sans text-ink/60">left to pay</span>
        </div>
    );
}
