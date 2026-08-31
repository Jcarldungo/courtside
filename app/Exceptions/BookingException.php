<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking request that is wrong on its face -- off the slot grid, in the
 * past, on a closed court, or an illegal state transition.
 *
 * Distinct from SlotUnavailableException, which means the request was perfectly
 * valid and merely lost a race. That difference is why one returns 422 and the
 * other 409, and why only one of them offers you another slot.
 */
class BookingException extends RuntimeException
{
    public static function notOnGrid(string $time): self
    {
        return new self("{$time} is not one of this venue's booking slots.");
    }

    public static function inThePast(): self
    {
        return new self('That slot has already started.');
    }

    public static function tooFarAhead(int $days): self
    {
        return new self("Bookings open {$days} days in advance.");
    }

    public static function courtUnavailable(string $court): self
    {
        return new self("{$court} is not accepting bookings right now.");
    }

    public static function notPending(): self
    {
        return new self('This booking is no longer awaiting payment.');
    }

    public static function notLive(): self
    {
        return new self('This booking has already been cancelled or expired.');
    }

    public static function noProofToVerify(): self
    {
        return new self('No payment proof has been uploaded for this booking yet.');
    }
}
