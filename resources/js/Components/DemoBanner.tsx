import { useState } from 'react';
import { router } from '@inertiajs/react';

interface DemoBannerProps {
    /** Only the public side offers a way into the admin view -- staff who are
     * already there don't need a link back to where they are. */
    showEnterAdmin?: boolean;
}

/**
 * The one-tap part of one-tap demo mode. A court owner evaluating this system
 * never creates an account and never has to look at data that's gone stale
 * since the last time someone poked at it -- this banner is how they reach
 * both of those from wherever they happen to be.
 */
export default function DemoBanner({ showEnterAdmin = false }: DemoBannerProps) {
    const [resetting, setResetting] = useState(false);

    function reset() {
        setResetting(true);
        router.post(
            route('demo.reset'),
            {},
            { preserveScroll: true, preserveState: true, onFinish: () => setResetting(false) }
        );
    }

    return (
        <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-accent px-4 py-1.5 text-center text-xs font-semibold text-ink">
            <span>DEMO — this is a live sample venue. Nothing booked here is real.</span>
            <span className="flex gap-3">
                {showEnterAdmin && (
                    <a href={route('demo.enter')} className="underline underline-offset-2 hover:no-underline">
                        View as owner
                    </a>
                )}
                <button type="button" onClick={reset} disabled={resetting} className="underline underline-offset-2 hover:no-underline disabled:opacity-60">
                    {resetting ? 'Resetting…' : 'Reset demo data'}
                </button>
            </span>
        </div>
    );
}
