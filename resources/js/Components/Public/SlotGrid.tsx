import { Board } from '@/types/board';

interface SlotGridProps {
    board: Board;
    unit: string;
    onSelectSlot: (courtId: number, courtName: string, slotStart: string, label: string, priceLabel: string) => void;
}

/**
 * The signature element: a scoreboard, not a form.
 *
 * Rows are time slots, columns are courts -- the same orientation as a printed
 * schedule taped to a court's fence, so an operator's mental model needs no
 * translation. Times and prices are set in the mono "score" face; only an
 * open cell is a real button, so the tab order skips every dead cell.
 */
export default function SlotGrid({ board, unit, onSelectSlot }: SlotGridProps) {
    if (board.courts.length === 0) {
        return (
            <p className="rounded-xl border border-dashed border-ink/20 bg-white p-6 text-center text-sm text-ink/60">
                No {unit}s are open for booking right now.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-2xl border border-ink/10 bg-white shadow-sm">
            <table className="w-full min-w-[420px] border-collapse text-sm">
                <thead>
                    <tr className="border-b border-ink/10 bg-surface-sunken">
                        <th scope="col" className="sticky left-0 z-10 bg-surface-sunken px-3 py-3 text-left font-display text-xs font-semibold uppercase tracking-wide text-ink/50">
                            Time
                        </th>
                        {board.courts.map((court) => (
                            <th key={court.id} scope="col" className="px-3 py-3 text-center font-display text-xs font-semibold uppercase tracking-wide text-ink/70">
                                {court.name}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {board.slots.map((slot, rowIndex) => (
                        <tr key={slot.start} className={`border-b border-ink/5 last:border-0 ${slot.is_peak ? 'bg-accent/5' : ''}`}>
                            <th
                                scope="row"
                                className="sticky left-0 z-10 whitespace-nowrap bg-inherit px-3 py-2 text-left font-score text-sm font-medium text-ink/80"
                            >
                                {slot.label}
                                {slot.is_peak && (
                                    <span className="ml-1.5 hidden text-[10px] font-sans font-semibold uppercase tracking-wide text-accent-strong sm:inline">
                                        peak
                                    </span>
                                )}
                            </th>
                            {board.courts.map((court) => {
                                const cell = court.cells[rowIndex];
                                const isOpen = cell.state === 'open';

                                return (
                                    <td key={court.id} className="px-1.5 py-1.5 text-center align-middle">
                                        {isOpen ? (
                                            <button
                                                type="button"
                                                onClick={() => onSelectSlot(court.id, court.name, cell.starts_at, `${slot.label} · ${court.name}`, cell.price_label)}
                                                className="group flex w-full min-w-[64px] flex-col items-center gap-0.5 rounded-lg border border-brand/25 bg-brand-tint px-2 py-2 transition-colors hover:bg-brand hover:text-white focus-visible:bg-brand focus-visible:text-white"
                                            >
                                                <span className="font-score text-xs font-semibold text-brand-strong group-hover:text-white group-focus-visible:text-white">
                                                    {cell.price_label}
                                                </span>
                                                <span className="text-[10px] uppercase tracking-wide text-brand/70 group-hover:text-white/80 group-focus-visible:text-white/80">
                                                    Open
                                                </span>
                                            </button>
                                        ) : (
                                            <CellBadge state={cell.state} />
                                        )}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CellBadge({ state }: { state: string }) {
    const copy: Record<string, string> = {
        taken: 'Booked',
        blocked: 'Closed',
        closed: 'Closed',
        past: '—',
        pending: 'Pending',
        confirmed: 'Booked',
    };

    return (
        <span
            aria-label={state === 'past' ? 'Slot has passed' : copy[state] ?? state}
            className="inline-flex w-full min-w-[64px] items-center justify-center rounded-lg border border-dashed border-ink/15 bg-surface-sunken px-2 py-2.5 text-[11px] font-medium text-ink/35 line-through decoration-ink/20"
        >
            {copy[state] ?? state}
        </span>
    );
}
