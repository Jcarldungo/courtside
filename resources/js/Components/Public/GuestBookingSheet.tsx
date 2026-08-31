import { FormEventHandler, useEffect, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import { XIcon } from '@/Components/Icons';

interface SelectedSlot {
    courtId: number;
    label: string;
    priceLabel: string;
    startsAt: string;
}

interface GuestBookingSheetProps {
    slot: SelectedSlot | null;
    onClose: () => void;
}

/**
 * Captures a name and mobile number in place, at the moment a slot is tapped.
 *
 * A court's customers will not create an account to reserve an hour, so this
 * is the entire signup: two fields, one slot already chosen for them. It is a
 * bottom sheet rather than a full-page form because on a phone that pattern
 * keeps the grid they just tapped visible in memory, one tap behind.
 */
export default function GuestBookingSheet({ slot, onClose }: GuestBookingSheetProps) {
    const nameRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        court_id: '',
        starts_at: '',
        customer_name: '',
        customer_phone: '',
    });

    useEffect(() => {
        if (slot) {
            setData({
                court_id: String(slot.courtId),
                starts_at: slot.startsAt,
                customer_name: data.customer_name,
                customer_phone: data.customer_phone,
            });
            clearErrors();
            window.setTimeout(() => nameRef.current?.focus(), 50);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [slot?.startsAt, slot?.courtId]);

    if (!slot) {
        return null;
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('bookings.store'), {
            preserveScroll: true,
            onSuccess: () => reset('customer_name', 'customer_phone'),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-labelledby="guest-sheet-title">
            <button
                type="button"
                aria-label="Close"
                onClick={onClose}
                className="absolute inset-0 bg-ink/50"
            />

            <div className="relative w-full max-w-md rounded-t-2xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-2xl sm:rounded-2xl">
                <div className="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-brand-strong">Confirm your slot</p>
                        <h2 id="guest-sheet-title" className="font-display text-xl font-semibold text-ink">
                            {slot.label}
                        </h2>
                        <p className="font-score text-sm text-ink/60">{slot.priceLabel} for the hour</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Cancel and close"
                        className="rounded-full p-1.5 text-ink/40 hover:bg-surface-sunken hover:text-ink"
                    >
                        <XIcon width={20} height={20} />
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label htmlFor="customer_name" className="mb-1 block text-sm font-medium text-ink/80">
                            Full name
                        </label>
                        <input
                            id="customer_name"
                            ref={nameRef}
                            type="text"
                            autoComplete="name"
                            value={data.customer_name}
                            onChange={(e) => setData('customer_name', e.target.value)}
                            className="w-full rounded-lg border-ink/15 focus:border-brand focus:ring-brand"
                            required
                        />
                        {errors.customer_name && <p className="mt-1 text-sm text-red-600">{errors.customer_name}</p>}
                    </div>

                    <div>
                        <label htmlFor="customer_phone" className="mb-1 block text-sm font-medium text-ink/80">
                            Mobile number
                        </label>
                        <input
                            id="customer_phone"
                            type="tel"
                            inputMode="numeric"
                            autoComplete="tel"
                            placeholder="09XXXXXXXXX"
                            value={data.customer_phone}
                            onChange={(e) => setData('customer_phone', e.target.value)}
                            className="w-full rounded-lg border-ink/15 font-score focus:border-brand focus:ring-brand"
                            required
                        />
                        {errors.customer_phone && <p className="mt-1 text-sm text-red-600">{errors.customer_phone}</p>}
                        <p className="mt-1 text-xs text-ink/50">We'll call this number if the courts ever need to close on short notice.</p>
                    </div>

                    {errors.court_id && <p className="text-sm text-red-600">{errors.court_id}</p>}

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-xl bg-brand py-3.5 text-base font-semibold text-white transition-colors hover:bg-brand-strong disabled:opacity-60"
                    >
                        {processing ? 'Holding your slot…' : `Hold this slot — ${slot.priceLabel}`}
                    </button>
                    <p className="text-center text-xs text-ink/50">No payment yet. You'll have 15 minutes to send GCash.</p>
                </form>
            </div>
        </div>
    );
}
