import { PropsWithChildren } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { PhoneIcon, PinIcon } from '@/Components/Icons';
import DemoBanner from '@/Components/DemoBanner';

/**
 * Shared chrome for every public page: header, footer, and a sticky mobile
 * booking bar. Nothing here reads a venue name, phone number or colour
 * directly -- it all comes from the shared `venue` prop, which is what makes
 * re-skinning a config edit rather than a find-and-replace through JSX.
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    const { venue, demo } = usePage<PageProps>().props;

    return (
        <div className="flex min-h-dvh flex-col bg-surface-sunken">
            {demo.enabled && <DemoBanner showEnterAdmin />}

            <header className="sticky top-0 z-30 border-b border-ink/10 bg-white/95 backdrop-blur">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
                    <Link href={route('home')} className="flex items-center gap-2 font-display text-lg font-semibold text-ink">
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-sm font-bold text-white">
                            {venue.short_name.slice(0, 1)}
                        </span>
                        {venue.short_name}
                    </Link>

                    <nav className="flex items-center gap-2 sm:gap-3">
                        <a
                            href={`tel:${venue.contact.phone_link}`}
                            className="hidden items-center gap-1.5 text-sm text-ink/70 hover:text-brand-strong sm:flex"
                        >
                            <PhoneIcon width={16} height={16} />
                            {venue.contact.phone}
                        </a>
                        <Link
                            href={route('book')}
                            className="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                        >
                            Book a {venue.unit}
                        </Link>
                    </nav>
                </div>
            </header>

            <main className="flex-1 pb-24 sm:pb-0">{children}</main>

            <footer className="border-t border-ink/10 bg-white">
                <div className="mx-auto grid max-w-5xl gap-8 px-4 py-10 sm:grid-cols-3">
                    <div>
                        <p className="font-display text-lg font-semibold text-ink">{venue.name}</p>
                        <p className="mt-1 text-sm text-ink/60">{venue.tagline}</p>
                    </div>
                    <div className="text-sm text-ink/70">
                        <p className="flex items-start gap-2">
                            <PinIcon width={16} height={16} className="mt-0.5 shrink-0 text-brand-strong" />
                            <span>
                                {venue.location.line1}
                                <br />
                                {venue.location.city}, {venue.location.province}
                            </span>
                        </p>
                        <a href={venue.location.map_url} target="_blank" rel="noreferrer" className="mt-2 inline-block font-medium text-brand-strong hover:underline">
                            Open in Google Maps →
                        </a>
                    </div>
                    <div className="text-sm text-ink/70">
                        <p>{venue.hours.label}</p>
                        <a href={`tel:${venue.contact.phone_link}`} className="mt-2 flex items-center gap-1.5 font-medium text-brand-strong hover:underline">
                            <PhoneIcon width={16} height={16} />
                            {venue.contact.phone}
                        </a>
                        <a
                            href={venue.contact.open_play_url}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-2 block text-ink/50 hover:text-brand-strong hover:underline"
                        >
                            Looking for open play instead? →
                        </a>
                    </div>
                </div>
                <div className="border-t border-ink/5 px-4 py-4 text-center text-xs text-ink/40">
                    Site by{' '}
                    <a href="https://janncarl.vercel.app" target="_blank" rel="noreferrer" className="underline hover:text-ink/60">
                        Jann Carl
                    </a>
                </div>
            </footer>

            {/* Thumb-reach CTA: on a phone opened from a Messenger link, the
                primary action must never require a scroll to find. */}
            <div className="fixed inset-x-0 bottom-0 z-30 border-t border-ink/10 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_16px_rgba(0,0,0,0.06)] sm:hidden">
                <Link
                    href={route('book')}
                    className="block w-full rounded-xl bg-brand py-3.5 text-center text-base font-semibold text-white active:bg-brand-strong"
                >
                    Book a {venue.unit}
                </Link>
            </div>
        </div>
    );
}
