<?php

namespace App\Enums;

/**
 * Bookings and maintenance blocks share one table on purpose.
 *
 * A court closed for resurfacing and a court sold to a customer are the same
 * fact from the schema's point of view: that court-hour is spoken for. Keeping
 * them in one table means one unique index guarantees both directions -- a
 * customer cannot book a blocked slot, and staff cannot block a slot a customer
 * already holds. Two tables would need application code to police the gap.
 */
enum BookingKind: string
{
    case Booking = 'booking';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking',
            self::Maintenance => 'Maintenance',
        };
    }
}
