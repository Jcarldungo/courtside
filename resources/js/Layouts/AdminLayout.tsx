import { PropsWithChildren } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import DemoBanner from '@/Components/DemoBanner';

/**
 * Deliberately plain: this is a work surface for a counter shift, not a
 * marketing page. No hero, no gallery -- just the venue name for orientation,
 * who's logged in, and a way out.
 */
export default function AdminLayout({ children }: PropsWithChildren) {
    const { venue, auth, demo } = usePage<PageProps>().props;

    return (
        <div className="min-h-dvh bg-surface-sunken">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
            >
                Skip to content
            </a>

            {demo.enabled && <DemoBanner />}
            <header className="border-b border-ink/10 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                    <div className="flex items-center gap-2">
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-sm font-bold text-white">
                            {venue.short_name.slice(0, 1)}
                        </span>
                        <span className="font-display text-base font-semibold text-ink">{venue.short_name} admin</span>
                    </div>

                    <div className="flex items-center gap-4 text-sm">
                        <Link href={route('home')} className="text-ink/70 hover:text-ink">
                            View public site
                        </Link>
                        <span className="hidden text-ink/70 sm:inline">
                            {auth.user?.name} · {auth.user?.role_label}
                        </span>
                        <button
                            type="button"
                            onClick={() => router.post(route('logout'))}
                            className="rounded-lg border border-ink/15 px-3 py-1.5 font-medium text-ink/70 hover:bg-surface-sunken"
                        >
                            Log out
                        </button>
                    </div>
                </div>
            </header>

            <main id="main-content" className="mx-auto max-w-6xl px-4 py-6">{children}</main>
        </div>
    );
}
