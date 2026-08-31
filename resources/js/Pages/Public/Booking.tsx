import { FormEventHandler, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import Countdown from '@/Components/Public/Countdown';
import { CheckCircleIcon, ClockIcon, UploadIcon, XIcon } from '@/Components/Icons';
import { PageProps } from '@/types';

interface BookingDetail {
    reference: string;
    court_name: string;
    status: 'pending' | 'confirmed' | 'cancelled' | 'expired';
    status_label: string;
    date_label: string;
    time_label: string;
    amount_label: string;
    customer_name: string;
    customer_phone: string;
    is_peak: boolean;
    has_proof: boolean;
    payment_reference: string | null;
    hold_seconds_remaining: number;
    proof_url: string | null;
    cancellation_reason: string | null;
}

interface PaymentDetail {
    method: string;
    account_name: string;
    account_number: string;
    instructions: string;
}

type BookingPageProps = PageProps<{
    booking: BookingDetail;
    payment: PaymentDetail;
    hold_minutes: number;
    contact: { phone: string; phone_link: string };
}>;

export default function Booking() {
    const { venue, booking, payment, contact } = usePage<BookingPageProps>().props;

    return (
        <PublicLayout>
            <Head title={`Booking ${booking.reference} — ${venue.name}`} />

            <div className="mx-auto max-w-lg px-4 py-8">
                <div className="rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-ink/70">Reference</p>
                    <p className="font-score text-2xl font-bold tracking-wider text-ink">{booking.reference}</p>

                    <dl className="mt-4 space-y-2 border-t border-ink/10 pt-4 text-sm">
                        <Row label="Court" value={booking.court_name} />
                        <Row label="Date" value={booking.date_label} />
                        <Row label="Time" value={`${booking.time_label}${booking.is_peak ? ' (peak)' : ''}`} mono />
                        <Row label="Amount" value={booking.amount_label} mono />
                        <Row label="Booked under" value={`${booking.customer_name} · ${booking.customer_phone}`} />
                    </dl>
                </div>

                <div className="mt-6">
                    {booking.status === 'pending' && !booking.has_proof && (
                        <PendingUnpaid booking={booking} payment={payment} contact={contact} />
                    )}
                    {booking.status === 'pending' && booking.has_proof && <AwaitingVerification />}
                    {booking.status === 'confirmed' && <Confirmed contactPhone={contact.phone_link} />}
                    {booking.status === 'expired' && <Expired unit={venue.unit} />}
                    {booking.status === 'cancelled' && <Cancelled reason={booking.cancellation_reason} unit={venue.unit} />}
                </div>
            </div>
        </PublicLayout>
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

function PendingUnpaid({ booking, payment, contact }: { booking: BookingDetail; payment: PaymentDetail; contact: { phone: string; phone_link: string } }) {
    const [preview, setPreview] = useState<string | null>(null);
    const { data, setData, post, processing, errors, progress } = useForm<{
        proof: File | null;
        payment_reference: string;
    }>({
        proof: null,
        payment_reference: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('bookings.proof.store', booking.reference), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    function handleFile(file: File | null) {
        setData('proof', file);
        setPreview(file ? URL.createObjectURL(file) : null);
    }

    return (
        <div className="space-y-5">
            <div className="flex flex-col items-center gap-2 text-center">
                <Countdown
                    initialSeconds={booking.hold_seconds_remaining}
                    onExpire={() => router.reload({ only: ['booking'] })}
                />
                <p className="text-xs text-ink/70">Your slot is held until the timer runs out.</p>
            </div>

            <div className="rounded-2xl border border-brand/20 bg-brand-tint p-5">
                <p className="text-xs font-semibold uppercase tracking-wide text-brand-strong">Step 1 — Send {payment.method}</p>
                <dl className="mt-2 space-y-1.5 text-sm text-ink/80">
                    <Row label="Send to" value={payment.account_name} />
                    <Row label="Number" value={payment.account_number} mono />
                    <Row label="Amount" value={booking.amount_label} mono />
                </dl>
                <p className="mt-3 text-sm text-ink/70">{payment.instructions}</p>
            </div>

            <form onSubmit={submit} className="rounded-2xl border border-ink/10 bg-white p-5">
                <p className="text-xs font-semibold uppercase tracking-wide text-ink/70">Step 2 — Upload your receipt</p>

                <label
                    htmlFor="proof"
                    className="mt-3 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-ink/20 bg-surface-sunken px-4 py-8 text-center hover:border-brand/40"
                >
                    {preview ? (
                        <img src={preview} alt="Selected GCash receipt preview" className="max-h-56 rounded-lg object-contain" />
                    ) : (
                        <>
                            <UploadIcon width={28} height={28} className="text-ink/70" />
                            <span className="text-sm font-medium text-ink/70">Tap to choose your GCash screenshot</span>
                            <span className="text-xs text-ink/70">JPG or PNG, up to 5MB</span>
                        </>
                    )}
                    <input
                        id="proof"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        className="sr-only"
                        onChange={(e) => handleFile(e.target.files?.[0] ?? null)}
                    />
                </label>
                {errors.proof && <p className="mt-2 text-sm text-red-600">{errors.proof}</p>}

                <div className="mt-4">
                    <label htmlFor="payment_reference" className="mb-1 block text-sm font-medium text-ink/80">
                        GCash reference number <span className="font-normal text-ink/70">(optional)</span>
                    </label>
                    <input
                        id="payment_reference"
                        type="text"
                        value={data.payment_reference}
                        onChange={(e) => setData('payment_reference', e.target.value)}
                        className="w-full rounded-lg border-ink/15 font-score focus:border-brand focus:ring-brand"
                    />
                </div>

                {progress && (
                    <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-sunken">
                        <div className="h-full bg-brand transition-all" style={{ width: `${progress.percentage}%` }} />
                    </div>
                )}

                <button
                    type="submit"
                    disabled={processing || !data.proof}
                    className="mt-4 w-full rounded-xl bg-brand py-3.5 text-base font-semibold text-white transition-colors hover:bg-brand-strong disabled:opacity-50"
                >
                    {processing ? 'Uploading…' : "I've sent the payment"}
                </button>
            </form>

            <p className="text-center text-xs text-ink/70">
                Trouble paying?{' '}
                <a href={`tel:${contact.phone_link}`} className="font-medium text-brand-strong hover:underline">
                    Call {contact.phone}
                </a>
            </p>
        </div>
    );
}

function AwaitingVerification() {
    return (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-brand/20 bg-brand-tint p-8 text-center">
            <ClockIcon width={32} height={32} className="text-brand-strong" />
            <p className="font-display text-lg font-semibold text-ink">Receipt received</p>
            <p className="text-sm text-ink/70">
                Staff are checking your payment against GCash. Your slot is safe — this no longer runs on a timer. We'll confirm shortly.
            </p>
        </div>
    );
}

function Confirmed({ contactPhone }: { contactPhone: string }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-brand/30 bg-brand-tint p-8 text-center">
            <CheckCircleIcon width={36} height={36} className="text-brand-strong" />
            <p className="font-display text-lg font-semibold text-ink">You're confirmed</p>
            <p className="text-sm text-ink/70">See you on the court. Show your reference code at the counter if asked.</p>
            <a href={`tel:${contactPhone}`} className="mt-1 text-sm font-medium text-brand-strong hover:underline">
                Need to change something? Call the venue.
            </a>
        </div>
    );
}

function Expired({ unit }: { unit: string }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-ink/15 bg-white p-8 text-center">
            <XIcon width={30} height={30} className="text-ink/70" />
            <p className="font-display text-lg font-semibold text-ink">This hold ran out</p>
            <p className="text-sm text-ink/70">Payment didn't arrive in time, so the slot went back on sale.</p>
            <Link href={route('book')} className="mt-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-strong">
                Book another {unit}
            </Link>
        </div>
    );
}

function Cancelled({ reason, unit }: { reason: string | null; unit: string }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-ink/15 bg-white p-8 text-center">
            <XIcon width={30} height={30} className="text-ink/70" />
            <p className="font-display text-lg font-semibold text-ink">Booking cancelled</p>
            {reason && <p className="text-sm text-ink/70">{reason}</p>}
            <Link href={route('book')} className="mt-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-strong">
                Book another {unit}
            </Link>
        </div>
    );
}
