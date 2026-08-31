<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

/**
 * The safety net under the queued job.
 *
 * On a ₱300/month shared host the queue worker is the first thing to die, and a
 * dead worker means every abandoned hold silently keeps its court-hour off sale
 * -- the venue loses money and nobody sees an error. This runs every minute and
 * needs no memory of what was dispatched, so it also cleans up after any hold
 * whose job was lost with a restarted worker.
 *
 * Where a worker cannot run at all, this command alone is enough: point cron at
 * `php artisan schedule:run` and holds still expire.
 */
class ReleaseStaleHolds extends Command
{
    protected $signature = 'courtside:release-holds';

    protected $description = 'Release pending bookings whose payment hold has run out';

    public function handle(BookingService $service): int
    {
        // Holds live for 15 minutes, so this set is small by construction --
        // no need to chunk, and chunking while mutating the filtered column
        // would skip rows anyway.
        $stale = Booking::query()->stale()->customerBookings()->get();

        $released = $stale->filter(fn (Booking $booking) => $service->expire($booking))->count();

        $this->info(sprintf(
            'Released %d expired hold%s.',
            $released,
            $released === 1 ? '' : 's'
        ));

        return self::SUCCESS;
    }
}
