<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatched with a delay the instant a hold is placed, to fire exactly when the
 * payment window closes.
 *
 * Every check below is a refusal to trust its own trigger. A queue can deliver
 * early, deliver twice, or deliver an hour late after a worker restart, so the
 * job re-reads the booking and decides for itself. The failure this guards
 * against -- expiring a slot somebody has already paid for -- is far worse than
 * a hold that lingers a minute too long.
 */
class ReleaseExpiredHold implements ShouldQueue
{
    use Queueable;

    /** If the booking is gone, there is nothing to release. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Booking $booking) {}

    public function handle(BookingService $service): void
    {
        $booking = $this->booking->fresh();

        if (! $booking || $booking->status !== BookingStatus::Pending) {
            return;
        }

        // Paid. The slot now waits on a human, not a clock.
        if ($booking->hasProof()) {
            return;
        }

        // Fired early: leave the customer their remaining minutes.
        if ($booking->hold_expires_at?->isFuture()) {
            return;
        }

        $service->expire($booking);
    }
}
