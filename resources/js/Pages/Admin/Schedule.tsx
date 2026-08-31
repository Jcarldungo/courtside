import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import AdminSlotGrid, { SelectedCell } from '@/Components/Admin/AdminSlotGrid';
import AdminActionPanel from '@/Components/Admin/AdminActionPanel';
import { PageProps } from '@/types';
import { Board, DateStripDay } from '@/types/board';

type ScheduleProps = PageProps<{
    board: Board;
    dates: DateStripDay[];
}>;

export default function Schedule() {
    const { venue, board, dates } = usePage<ScheduleProps>().props;
    const [selection, setSelection] = useState<SelectedCell | null>(null);

    function goToDate(date: string) {
        router.get(route('dashboard', { date }), {}, { preserveScroll: true, preserveState: true });
    }

    return (
        <AdminLayout>
            <Head title={`Today's schedule — ${venue.name} admin`} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="font-display text-2xl font-bold text-ink">Schedule</h1>
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => goToDate(shiftDate(board.date, -1))}
                        className="rounded-lg border border-ink/15 px-3 py-1.5 text-sm text-ink/70 hover:bg-white"
                    >
                        ← Prev day
                    </button>
                    <button
                        type="button"
                        onClick={() => goToDate(shiftDate(board.date, 1))}
                        className="rounded-lg border border-ink/15 px-3 py-1.5 text-sm text-ink/70 hover:bg-white"
                    >
                        Next day →
                    </button>
                </div>
            </div>

            <p className="mt-1 text-sm text-ink/70">{board.date_label}{board.is_today && ' · Today'}</p>

            {board.summary && (
                <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <SummaryTile label="Booked" value={String(board.summary.booked)} />
                    <SummaryTile
                        label="Needs review"
                        value={String(board.summary.awaiting_verification)}
                        highlight={board.summary.awaiting_verification > 0}
                    />
                    <SummaryTile label="Awaiting payment" value={String(board.summary.awaiting_payment)} />
                    <SummaryTile label="Confirmed revenue" value={board.summary.expected_label} mono />
                </div>
            )}

            <div className="mt-4 -mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
                {dates.map((day) => (
                    <Link
                        key={day.date}
                        href={route('dashboard', { date: day.date })}
                        preserveScroll
                        replace
                        className={`flex shrink-0 flex-col items-center rounded-lg border px-3 py-1.5 text-xs ${
                            day.date === board.date ? 'border-brand bg-brand text-white' : 'border-ink/10 bg-white text-ink/70'
                        }`}
                    >
                        <span className="font-semibold uppercase tracking-wide opacity-80">{day.weekday}</span>
                        <span className="font-score font-bold">{day.day}</span>
                    </Link>
                ))}
            </div>

            <div className="mt-4">
                <AdminSlotGrid board={board} onSelectCell={setSelection} />
            </div>

            <div className="mt-4 flex flex-wrap gap-4 text-xs text-ink/70">
                <Legend swatchClass="bg-amber-50 border-amber-200" label="Awaiting payment" />
                <Legend swatchClass="bg-amber-100 border-amber-400" label="Needs review" />
                <Legend swatchClass="bg-brand-tint border-brand/30" label="Confirmed" />
                <Legend swatchClass="bg-slate-100 border-slate-300" label="Maintenance" />
            </div>

            <AdminActionPanel selection={selection} onClose={() => setSelection(null)} />
        </AdminLayout>
    );
}

function SummaryTile({ label, value, highlight = false, mono = false }: { label: string; value: string; highlight?: boolean; mono?: boolean }) {
    return (
        <div className={`rounded-xl border p-3 ${highlight ? 'border-amber-300 bg-amber-50' : 'border-ink/10 bg-white'}`}>
            <p className="text-[11px] font-medium uppercase tracking-wide text-ink/70">{label}</p>
            <p className={`mt-0.5 text-lg font-semibold ${mono ? 'font-score' : 'font-display'} ${highlight ? 'text-amber-700' : 'text-ink'}`}>{value}</p>
        </div>
    );
}

function Legend({ swatchClass, label }: { swatchClass: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5">
            <i className={`h-3 w-3 rounded border ${swatchClass}`} />
            {label}
        </span>
    );
}

function shiftDate(date: string, days: number): string {
    const d = new Date(`${date}T00:00:00`);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}
