import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { PageProps } from '@/types';

/**
 * Wraps login, password reset and email verification -- the handful of pages
 * a customer never sees. Same tokens as the rest of the app rather than
 * Breeze's default indigo-on-gray, so a staff member's first screen doesn't
 * look like a different product from the one they're about to manage.
 */
export default function GuestLayout({ children }: PropsWithChildren) {
    const { venue } = usePage<PageProps>().props;

    return (
        <div className="flex min-h-dvh flex-col items-center justify-center bg-surface-sunken px-4 py-10">
            <Link href="/" className="mb-6 flex items-center gap-2 font-display text-lg font-semibold text-ink">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand text-base font-bold text-white">
                    {venue.short_name.slice(0, 1)}
                </span>
                {venue.short_name}
            </Link>

            <div className="w-full overflow-hidden rounded-2xl border border-ink/10 bg-white px-6 py-6 shadow-sm sm:max-w-md sm:px-8">
                {children}
            </div>
        </div>
    );
}
