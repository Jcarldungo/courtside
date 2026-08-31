import { FormEventHandler, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { SelectedCell } from './AdminSlotGrid';
import { XIcon } from '@/Components/Icons';
import { PageProps } from '@/types';
import { useModalA11y } from '@/Hooks/useModalA11y';

interface AdminActionPanelProps {
    selection: SelectedCell | null;
    onClose: () => void;
}

/**
 * One panel, branched on what was clicked, rather than a different modal per
 * action -- an open slot, an unpaid hold, a receipt waiting on a human, a
 * confirmed booking, and a maintenance block all resolve to "here's the
 * situation, here's what you can do about it" in the same place.
 */
export default function AdminActionPanel({ selection, onClose }: AdminActionPanelProps) {
    const dialogRef = useModalA11y<HTMLDivElement>(selection !== null, onClose);

    if (!selection) {
        return null;
    }

    const { cell, courtName } = selection;
    const title = `${cell.label} · ${courtName}`;

    return (
        <div ref={dialogRef} className="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-labelledby="admin-panel-title">
            <button type="button" aria-label="Close" onClick={onClose} className="absolute inset-0 bg-ink/50" tabIndex={-1} />

            <div className="relative w-full max-w-md rounded-t-2xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-2xl sm:rounded-2xl">
                <div className="mb-4 flex items-start justify-between gap-3">
                    <h2 id="admin-panel-title" className="font-display text-lg font-semibold text-ink">
                        {title}
                    </h2>
                    <button type="button" onClick={onClose} aria-label="Close" className="rounded-full p-1.5 text-ink/70 hover:bg-surface-sunken hover:text-ink">
                        <XIcon width={20} height={20} />
                    </button>
                </div>

                {cell.state === 'open' && <BlockForm courtId={selection.courtId} startsAt={cell.starts_at} onDone={onClose} />}
                {cell.state === 'blocked' && cell.booking && <MaintenanceDetail booking={cell.booking} onDone={onClose} />}
                {cell.state === 'pending' && cell.booking && <PendingDetail booking={cell.booking} priceLabel={cell.price_label} onDone={onClose} />}
                {cell.state === 'confirmed' && cell.booking && <ConfirmedDetail booking={cell.booking} priceLabel={cell.price_label} onDone={onClose} />}
            </div>
        </div>
    );
}

function BlockForm({ courtId, startsAt, onDone }: { courtId: number; startsAt: string; onDone: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        court_id: String(courtId),
        starts_at: startsAt,
        reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.maintenance.store'), { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <p className="text-sm text-ink/70">This slot is open. Block it to take it off sale.</p>
            <div>
                <label htmlFor="reason" className="mb-1 block text-sm font-medium text-ink/80">
                    Reason <span className="font-normal text-ink/70">(optional, staff-only)</span>
                </label>
                <input
                    id="reason"
                    type="text"
                    placeholder="Resurfacing, net repair, private event…"
                    value={data.reason}
                    onChange={(e) => setData('reason', e.target.value)}
                    className="w-full rounded-lg border-ink/15 focus:border-brand focus:ring-brand"
                />
            </div>
            {/* 'booking' isn't a field on this form -- it's the generic key the
                global BookingException/SlotUnavailableException handlers use
                for server-side failures that aren't about one specific input. */}
            {(errors as Record<string, string>).booking && (
                <p className="text-sm text-red-600">{(errors as Record<string, string>).booking}</p>
            )}
            {errors.starts_at && <p className="text-sm text-red-600">{errors.starts_at}</p>}
            <button
                type="submit"
                disabled={processing}
                className="w-full rounded-xl bg-slate-700 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
            >
                {processing ? 'Blocking…' : 'Block this slot'}
            </button>
        </form>
    );
}

function MaintenanceDetail({ booking, onDone }: { booking: NonNullable<SelectedCell['cell']['booking']>; onDone: () => void }) {
    const [processing, setProcessing] = useState(false);

    function remove() {
        setProcessing(true);
        router.post(route('admin.bookings.cancel', booking.id), {}, { preserveScroll: true, onFinish: () => setProcessing(false), onSuccess: onDone });
    }

    return (
        <div className="space-y-4">
            <p className="text-sm text-ink/70">{booking.notes || 'Blocked for maintenance.'}</p>
            <button
                type="button"
                onClick={remove}
                disabled={processing}
                className="w-full rounded-xl border border-ink/15 py-3 text-sm font-semibold text-ink/70 hover:bg-surface-sunken disabled:opacity-60"
            >
                {processing ? 'Removing…' : 'Remove block — reopen this slot'}
            </button>
        </div>
    );
}

function PendingDetail({
    booking,
    priceLabel,
    onDone,
}: {
    booking: NonNullable<SelectedCell['cell']['booking']>;
    priceLabel: string;
    onDone: () => void;
}) {
    const { venue } = usePage<PageProps>().props;
    const [processing, setProcessing] = useState<'confirm' | 'reject' | 'cancel' | null>(null);

    function act(action: 'confirm' | 'reject' | 'cancel') {
        setProcessing(action);
        const routeName = action === 'confirm' ? 'admin.bookings.confirm' : action === 'reject' ? 'admin.bookings.reject' : 'admin.bookings.cancel';
        router.post(route(routeName, booking.id), {}, { preserveScroll: true, onFinish: () => setProcessing(null), onSuccess: onDone });
    }

    return (
        <div className="space-y-4">
            <dl className="space-y-1.5 text-sm">
                <Row label="Customer" value={`${booking.customer_name} · ${booking.customer_phone}`} />
                <Row label="Amount" value={`${priceLabel} (${venue.payment.method})`} mono />
                <Row label="Reference" value={booking.reference} mono />
            </dl>

            {booking.has_proof ? (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p className="text-sm font-medium text-amber-900">Receipt uploaded — check it against GCash before confirming.</p>
                    {booking.proof_url && (
                        <a href={booking.proof_url} target="_blank" rel="noreferrer" className="mt-2 block">
                            <img
                                src={booking.proof_url}
                                alt={`GCash receipt uploaded for booking ${booking.reference}`}
                                className="max-h-48 w-full rounded-lg border border-amber-200 object-contain bg-white"
                            />
                        </a>
                    )}
                    {booking.payment_reference && (
                        <p className="mt-2 text-xs text-amber-800">
                            GCash ref: <span className="font-score">{booking.payment_reference}</span>
                        </p>
                    )}
                    <div className="mt-3 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => act('confirm')}
                            disabled={processing !== null}
                            className="rounded-lg bg-brand py-2.5 text-sm font-semibold text-white hover:bg-brand-strong disabled:opacity-60"
                        >
                            {processing === 'confirm' ? 'Confirming…' : 'Confirm'}
                        </button>
                        <button
                            type="button"
                            onClick={() => act('reject')}
                            disabled={processing !== null}
                            className="rounded-lg border border-red-200 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
                        >
                            {processing === 'reject' ? 'Rejecting…' : 'Reject'}
                        </button>
                    </div>
                </div>
            ) : (
                <p className="rounded-xl border border-ink/10 bg-surface-sunken p-3 text-sm text-ink/70">
                    Waiting for the customer to upload a GCash receipt. Nothing to verify yet.
                </p>
            )}

            <button
                type="button"
                onClick={() => act('cancel')}
                disabled={processing !== null}
                className="w-full rounded-xl border border-ink/15 py-2.5 text-sm font-medium text-ink/70 hover:bg-surface-sunken disabled:opacity-60"
            >
                {processing === 'cancel' ? 'Cancelling…' : 'Cancel this hold'}
            </button>
        </div>
    );
}

function ConfirmedDetail({
    booking,
    priceLabel,
    onDone,
}: {
    booking: NonNullable<SelectedCell['cell']['booking']>;
    priceLabel: string;
    onDone: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    function cancel() {
        setProcessing(true);
        router.post(route('admin.bookings.cancel', booking.id), {}, { preserveScroll: true, onFinish: () => setProcessing(false), onSuccess: onDone });
    }

    return (
        <div className="space-y-4">
            <dl className="space-y-1.5 text-sm">
                <Row label="Customer" value={`${booking.customer_name} · ${booking.customer_phone}`} />
                <Row label="Amount" value={priceLabel} mono />
                <Row label="Reference" value={booking.reference} mono />
            </dl>
            <p className="rounded-xl border border-brand/20 bg-brand-tint p-3 text-sm text-brand-strong">Payment confirmed. This court-hour is sold.</p>
            <button
                type="button"
                onClick={cancel}
                disabled={processing}
                className="w-full rounded-xl border border-red-200 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60"
            >
                {processing ? 'Cancelling…' : 'Cancel booking (refund / no-show)'}
            </button>
        </div>
    );
}

function Row({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-ink/70">{label}</dt>
            <dd className={`text-right font-medium text-ink ${mono ? 'font-score' : ''}`}>{value}</dd>
        </div>
    );
}
