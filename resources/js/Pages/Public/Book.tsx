import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import SlotGrid from '@/Components/Public/SlotGrid';
import GuestBookingSheet from '@/Components/Public/GuestBookingSheet';
import { AlertIcon } from '@/Components/Icons';
import { PageProps } from '@/types';
import { Board, DateStripDay } from '@/types/board';

type BookProps = PageProps<{
    board: Board;
    dates: DateStripDay[];
}>;

interface SelectedSlot {
    courtId: number;
    label: string;
    priceLabel: string;
    startsAt: string;
}

export default function Book() {
    const { venue, board, dates, flash } = usePage<BookProps>().props;
    const [selected, setSelected] = useState<SelectedSlot | null>(null);

    // The conflict banner: losing a race for a slot arrives as flashed data
    // (see bootstrap/app.php's SlotUnavailableException handler), not a dead
    // end -- the next open slot on the same court is one tap away.
    const conflict = flash.conflict;

    const dateStrip = useMemo(
        () =>
            dates.map((day) => ({
                ...day,
                href: route('book', { date: day.date }),
            })),
        [dates],
    );

    function goToDate(date: string) {
        router.get(route('book', { date }), {}, { preserveScroll: true, preserveState: true });
    }

    function handleTakeNextSlot() {
        if (!conflict?.next_available_at) return;

        const [datePart] = conflict.next_available_at.split('T');
        if (datePart !== board.date) {
            goToDate(datePart);
            return;
        }

        setSelected({
            courtId: conflict.court.id,
            label: `${conflict.next_available_label} · ${conflict.court.name}`,
            priceLabel: '',
            startsAt: conflict.next_available_at.slice(0, 16).replace('T', ' '),
        });
    }

    return (
        <PublicLayout>
            <Head title={`Book a ${venue.unit} — ${venue.name}`} />

            <div className="mx-auto max-w-5xl px-4 py-6">
                <h1 className="font-display text-2xl font-bold text-ink sm:text-3xl">Book a {venue.unit}</h1>
                <p className="mt-1 text-sm text-ink/60">Pick a time, then confirm with your name and number. Pay by {venue.payment.method} after.</p>

                {conflict && (
                    <div role="alert" className="mt-5 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
                        <AlertIcon width={20} height={20} className="mt-0.5 shrink-0 text-amber-600" />
                        <div className="flex-1">
                            <p className="text-sm font-medium text-amber-900">{conflict.message}</p>
                            {conflict.next_available_label && (
                                <button
                                    type="button"
                                    onClick={handleTakeNextSlot}
                                    className="mt-2 text-sm font-semibold text-amber-900 underline underline-offset-2 hover:text-amber-950"
                                >
                                    Hold {conflict.next_available_label} on {conflict.court.name} instead →
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {/* Date strip */}
                <div className="mt-5 -mx-4 flex snap-x gap-2 overflow-x-auto px-4 pb-2" role="tablist" aria-label="Choose a date">
                    {dateStrip.map((day) => (
                        <Link
                            key={day.date}
                            href={day.href}
                            preserveScroll
                            role="tab"
                            aria-selected={day.date === board.date}
                            replace
                            className={`flex shrink-0 snap-start flex-col items-center rounded-xl border px-3.5 py-2 transition-colors ${
                                day.date === board.date
                                    ? 'border-brand bg-brand text-white'
                                    : 'border-ink/10 bg-white text-ink/70 hover:border-brand/40'
                            }`}
                        >
                            <span className="text-[10px] font-semibold uppercase tracking-wide opacity-80">{day.weekday}</span>
                            <span className="font-score text-base font-bold leading-tight">{day.day}</span>
                            <span className="text-[10px] opacity-70">{day.month}</span>
                        </Link>
                    ))}
                </div>

                <div className="mt-4 flex items-center justify-between text-sm text-ink/60">
                    <span>{board.date_label}{board.is_today && ' · Today'}</span>
                    <div className="flex items-center gap-3 text-xs">
                        <span className="flex items-center gap-1"><i className="h-2.5 w-2.5 rounded-full bg-brand-tint border border-brand/40" /> Open</span>
                        <span className="flex items-center gap-1"><i className="h-2.5 w-2.5 rounded-full bg-surface-sunken border border-ink/15" /> Taken</span>
                    </div>
                </div>

                <div className="mt-3">
                    <SlotGrid
                        board={board}
                        unit={venue.unit}
                        onSelectSlot={(courtId, courtName, startsAt, label, priceLabel) =>
                            setSelected({ courtId, label, priceLabel, startsAt })
                        }
                    />
                </div>
            </div>

            <GuestBookingSheet slot={selected} onClose={() => setSelected(null)} />
        </PublicLayout>
    );
}
