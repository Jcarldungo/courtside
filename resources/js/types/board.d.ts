/**
 * Mirrors App\Services\ScheduleBoard::forDate(). The public grid never
 * receives the `booking` key on a cell -- that only exists in the admin
 * payload -- so it stays optional here rather than being widened to `any`.
 */
export interface SlotHeading {
    start: string;
    label: string;
    short_label: string;
    is_peak: boolean;
}

export type CellState = 'open' | 'taken' | 'blocked' | 'closed' | 'past' | 'pending' | 'confirmed';

export interface BoardCellBooking {
    id: number;
    reference: string;
    kind: 'booking' | 'maintenance';
    status: 'pending' | 'confirmed' | 'cancelled' | 'expired';
    customer_name: string | null;
    customer_phone: string | null;
    amount_label: string;
    has_proof: boolean;
    proof_url: string | null;
    payment_reference: string | null;
    notes: string | null;
}

export interface BoardCell {
    slot: string;
    starts_at: string;
    label: string;
    is_peak: boolean;
    price_label: string;
    state: CellState;
    booking?: BoardCellBooking;
}

export interface BoardCourt {
    id: number;
    name: string;
    surface: string | null;
    is_active: boolean;
    rate_peak_label: string;
    rate_offpeak_label: string;
    cells: BoardCell[];
}

export interface BoardSummary {
    booked: number;
    awaiting_payment: number;
    awaiting_verification: number;
    blocked: number;
    expected_centavos: number;
    expected_label: string;
}

export interface Board {
    date: string;
    date_label: string;
    is_today: boolean;
    slots: SlotHeading[];
    courts: BoardCourt[];
    summary: BoardSummary | null;
}

export interface DateStripDay {
    date: string;
    weekday: string;
    day: string;
    month: string;
    is_today: boolean;
    is_weekend: boolean;
}
