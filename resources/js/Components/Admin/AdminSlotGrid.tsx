import { Board, BoardCell } from '@/types/board';

export interface SelectedCell {
    courtId: number;
    courtName: string;
    cell: BoardCell;
}

interface AdminSlotGridProps {
    board: Board;
    onSelectCell: (selection: SelectedCell) => void;
}

/**
 * The staff mirror of the public SlotGrid, same row/column orientation so the
 * mental model carries over, but every non-empty cell is real information
 * (who, how much, has a receipt landed yet) and every future cell is
 * clickable -- an open one to block it, anything else to act on it.
 */
export default function AdminSlotGrid({ board, onSelectCell }: AdminSlotGridProps) {
    return (
        <div className="overflow-x-auto rounded-2xl border border-ink/10 bg-white shadow-sm">
            <table className="w-full min-w-[480px] border-collapse text-sm">
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
                        <tr key={slot.start} className="border-b border-ink/5 last:border-0">
                            <th scope="row" className="sticky left-0 z-10 whitespace-nowrap bg-white px-3 py-2 text-left font-score text-sm font-medium text-ink/80">
                                {slot.label}
                            </th>
                            {board.courts.map((court) => {
                                const cell = court.cells[rowIndex];
                                const interactive = cell.state !== 'past' && cell.state !== 'closed';

                                return (
                                    <td key={court.id} className="px-1.5 py-1.5 text-center align-middle">
                                        {interactive ? (
                                            <button
                                                type="button"
                                                onClick={() => onSelectCell({ courtId: court.id, courtName: court.name, cell })}
                                                className={`w-full min-w-[76px] rounded-lg border px-2 py-2 text-left transition-colors ${cellClasses(cell)}`}
                                            >
                                                <AdminCellContent cell={cell} />
                                            </button>
                                        ) : (
                                            <span className="inline-flex w-full min-w-[76px] items-center justify-center rounded-lg border border-dashed border-ink/10 px-2 py-2.5 text-[11px] text-ink/30">
                                                {cell.state === 'closed' ? 'Closed' : '—'}
                                            </span>
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

function cellClasses(cell: BoardCell): string {
    if (cell.state === 'open') {
        return 'border-ink/10 bg-white hover:border-brand/40 hover:bg-brand-tint';
    }
    if (cell.state === 'blocked') {
        return 'border-slate-300 bg-slate-100 hover:bg-slate-200';
    }
    if (cell.state === 'confirmed') {
        return 'border-brand/30 bg-brand-tint hover:bg-brand/15';
    }
    if (cell.state === 'pending' && cell.booking?.has_proof) {
        return 'border-amber-400 bg-amber-100 hover:bg-amber-200 animate-pulse';
    }
    // pending, no proof yet
    return 'border-amber-200 bg-amber-50 hover:bg-amber-100';
}

function AdminCellContent({ cell }: { cell: BoardCell }) {
    if (cell.state === 'open') {
        return (
            <span className="block text-center text-[11px] font-semibold uppercase tracking-wide text-ink/40">
                + Block
            </span>
        );
    }

    if (cell.state === 'blocked') {
        return <span className="block truncate text-xs font-medium text-slate-600">Maintenance</span>;
    }

    if (!cell.booking) {
        return null;
    }

    const urgent = cell.state === 'pending' && cell.booking.has_proof;

    return (
        <span className="block">
            <span className="block truncate text-xs font-semibold text-ink">{cell.booking.customer_name}</span>
            <span className={`block text-[10px] font-medium uppercase tracking-wide ${urgent ? 'text-amber-700' : cell.state === 'confirmed' ? 'text-brand-strong' : 'text-amber-600'}`}>
                {urgent ? 'Verify payment' : cell.state === 'confirmed' ? 'Confirmed' : 'Awaiting GCash'}
            </span>
        </span>
    );
}
