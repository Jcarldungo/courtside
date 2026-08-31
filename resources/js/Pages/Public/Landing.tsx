import { Head, Link, usePage } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/types';
import { ClockIcon, PhoneIcon, PinIcon } from '@/Components/Icons';

interface CourtSummary {
    id: number;
    name: string;
    surface: string | null;
    description: string | null;
    rate_peak_label: string;
    rate_offpeak_label: string;
}

interface TonightSummary {
    date_label: string;
    peak_label: string;
    peak_has_passed: boolean;
    open_peak_slots: string[];
    open_peak_count: number;
}

type LandingProps = PageProps<{
    courts: CourtSummary[];
    tonight: TonightSummary;
}>;

export default function Landing() {
    const { venue, courts, tonight } = usePage<LandingProps>().props;

    return (
        <PublicLayout>
            <Head title={`${venue.name} — book a ${venue.unit} in ${venue.location.city}`} />

            {/* Hero: the most characteristic thing in this world is a court
                actually being played on, so the photo leads. */}
            <section className="relative">
                <div className="relative h-[62vh] min-h-[420px] w-full overflow-hidden sm:h-[70vh]">
                    <img
                        src={venue.photos.hero}
                        alt=""
                        className="h-full w-full object-cover"
                        fetchPriority="high"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/30 to-ink/10" />
                    <div className="absolute inset-x-0 bottom-0 mx-auto max-w-5xl px-4 pb-8 text-white sm:pb-12">
                        <p className="text-sm font-semibold uppercase tracking-widest text-accent">
                            {venue.location.city}, {venue.location.province}
                        </p>
                        <h1 className="mt-2 max-w-lg font-display text-4xl font-bold leading-tight sm:text-5xl">
                            {venue.name}
                        </h1>
                        <p className="mt-3 max-w-md text-base text-white/85 sm:text-lg">{venue.tagline}</p>

                        <div className="mt-6 flex flex-wrap items-center gap-3">
                            <Link
                                href={route('book')}
                                className="rounded-xl bg-brand px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand/30 transition-colors hover:bg-brand-strong"
                            >
                                Book a {venue.unit}
                            </Link>
                            <a
                                href={`tel:${venue.contact.phone_link}`}
                                className="flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-5 py-3.5 text-sm font-medium text-white backdrop-blur hover:bg-white/20"
                            >
                                <PhoneIcon width={18} height={18} />
                                {venue.contact.phone}
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            {/* Tonight's scoreboard: real, live availability, not marketing copy. */}
            <section className="mx-auto -mt-8 max-w-5xl px-4">
                <div className="rounded-2xl border border-ink/10 bg-white p-5 shadow-lg shadow-ink/5 sm:p-6">
                    <p className="text-xs font-semibold uppercase tracking-wide text-brand-strong">Tonight · {tonight.date_label}</p>
                    {tonight.open_peak_count > 0 ? (
                        <>
                            <p className="mt-1 font-display text-xl font-semibold text-ink">
                                {tonight.open_peak_count} peak {tonight.open_peak_count === 1 ? 'slot' : 'slots'} still open, {tonight.peak_label}
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {tonight.open_peak_slots.slice(0, 6).map((slot) => (
                                    <span key={slot} className="font-score rounded-full bg-brand-tint px-3 py-1 text-xs font-semibold text-brand-strong">
                                        {slot}
                                    </span>
                                ))}
                            </div>
                        </>
                    ) : tonight.peak_has_passed ? (
                        <p className="mt-1 font-display text-xl font-semibold text-ink">
                            Peak hours ({tonight.peak_label}) have wrapped up for today — book ahead for tomorrow night.
                        </p>
                    ) : (
                        <p className="mt-1 font-display text-xl font-semibold text-ink">
                            Peak hours ({tonight.peak_label}) are fully booked tonight — plenty open tomorrow.
                        </p>
                    )}
                    <Link href={route('book')} className="mt-4 inline-block text-sm font-semibold text-brand-strong hover:underline">
                        See the full schedule →
                    </Link>
                </div>
            </section>

            {/* Rates */}
            <section id="rates" className="mx-auto max-w-5xl px-4 py-14">
                <h2 className="font-display text-2xl font-bold text-ink sm:text-3xl">Rates</h2>
                <p className="mt-1 text-sm text-ink/60">Priced per {venue.unit}, per hour. Peak is {venue.hours.peak_label}.</p>

                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {courts.map((court) => (
                        <div key={court.id} className="rounded-2xl border border-ink/10 bg-white p-5">
                            <p className="font-display text-lg font-semibold text-ink">{court.name}</p>
                            {court.surface && <p className="text-xs text-ink/50">{court.surface}</p>}
                            <dl className="mt-4 space-y-2 text-sm">
                                <div className="flex items-center justify-between">
                                    <dt className="text-ink/60">Off-peak</dt>
                                    <dd className="font-score font-semibold text-ink">{court.rate_offpeak_label}/hr</dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt className="text-ink/60">Peak ({venue.hours.peak_label})</dt>
                                    <dd className="font-score font-semibold text-accent-strong">{court.rate_peak_label}/hr</dd>
                                </div>
                            </dl>
                        </div>
                    ))}
                </div>
            </section>

            {/* Amenities */}
            <section className="border-t border-ink/10 bg-white py-14">
                <div className="mx-auto max-w-5xl px-4">
                    <h2 className="font-display text-2xl font-bold text-ink sm:text-3xl">What's here</h2>
                    <div className="mt-6 grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                        {venue.amenities.map((item) => (
                            <div key={item.label}>
                                <p className="font-display text-base font-semibold text-ink">{item.label}</p>
                                <p className="mt-0.5 text-sm text-ink/60">{item.detail}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Gallery */}
            <section className="mx-auto max-w-5xl px-4 py-14">
                <h2 className="font-display text-2xl font-bold text-ink sm:text-3xl">On the courts</h2>
                <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {venue.photos.gallery.map((photo) => (
                        <img
                            key={photo.src}
                            src={photo.src}
                            alt={photo.alt}
                            loading="lazy"
                            className="aspect-[4/5] w-full rounded-xl object-cover"
                        />
                    ))}
                </div>
            </section>

            {/* Hours, location, payment */}
            <section className="border-t border-ink/10 bg-white py-14">
                <div className="mx-auto grid max-w-5xl gap-8 px-4 sm:grid-cols-3">
                    <div>
                        <ClockIcon width={22} height={22} className="text-brand-strong" />
                        <h3 className="mt-2 font-display text-lg font-semibold text-ink">Hours</h3>
                        <p className="mt-1 text-sm text-ink/70">{venue.hours.label}</p>
                        <p className="text-sm text-ink/50">Peak: {venue.hours.peak_label}</p>
                    </div>
                    <div>
                        <PinIcon width={22} height={22} className="text-brand-strong" />
                        <h3 className="mt-2 font-display text-lg font-semibold text-ink">Location</h3>
                        <p className="mt-1 text-sm text-ink/70">
                            {venue.location.line1}, {venue.location.city}
                        </p>
                        <p className="text-sm text-ink/50">{venue.location.landmark}</p>
                        <a href={venue.location.map_url} target="_blank" rel="noreferrer" className="mt-2 inline-block text-sm font-semibold text-brand-strong hover:underline">
                            Get directions →
                        </a>
                    </div>
                    <div>
                        <h3 className="font-display text-lg font-semibold text-ink">Paying with {venue.payment.method}</h3>
                        <p className="mt-1 text-sm text-ink/70">
                            Reserve first, then send {venue.payment.method} to <span className="font-score font-semibold">{venue.payment.account_number}</span> and
                            upload your receipt — no need to pay at the counter.
                        </p>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-5xl px-4 pb-16 pt-4 text-center">
                <Link
                    href={route('book')}
                    className="inline-block rounded-xl bg-brand px-8 py-4 text-base font-semibold text-white shadow-lg shadow-brand/20 hover:bg-brand-strong"
                >
                    Book a {venue.unit} now
                </Link>
            </section>
        </PublicLayout>
    );
}
