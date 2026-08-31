<?php

namespace App\Enums;

/**
 * Staff accounts only. Customers never register -- they book as guests with a
 * name and a mobile number, because a court's customers will not create an
 * account to reserve an hour, and asking them to is how a booking page loses
 * to a Facebook comment.
 */
enum UserRole: string
{
    /** Full access, including rates, courts and staff accounts. */
    case Owner = 'owner';

    /** Day-to-day counter duty: confirm payments, block courts, see the schedule. */
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Staff => 'Staff',
        };
    }
}
