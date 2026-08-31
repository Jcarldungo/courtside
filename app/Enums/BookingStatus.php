<?php

namespace App\Enums;

enum BookingStatus: string
{
    /** Slot is held, payment proof not yet accepted. Expires on a timer. */
    case Pending = 'pending';

    /** Payment verified by staff. The slot is sold. */
    case Confirmed = 'confirmed';

    /** Called off by customer or staff. Slot returns to the grid. */
    case Cancelled = 'cancelled';

    /** Hold ran out before payment proof arrived. Slot returns to the grid. */
    case Expired = 'expired';

    /**
     * Statuses that occupy a court slot.
     *
     * This list is the single definition of "live" and must stay in step with
     * the generated column in the bookings migration -- that expression is what
     * the database actually enforces.
     *
     * @return array<int, string>
     */
    public static function live(): array
    {
        return [self::Pending->value, self::Confirmed->value];
    }

    public function isLive(): bool
    {
        return in_array($this->value, self::live(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }
}
