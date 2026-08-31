<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The three things staff do to a booking that already exists: confirm the
 * receipt, reject it, or cancel a live one. Removing a maintenance block is
 * the same `cancel` action -- a block is just a booking with no customer, and
 * BookingService::cancel() already works on any live row regardless of kind.
 */
class BookingActionController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        $this->bookings->confirm($booking, $request->user());

        return back()->with('status', 'booking-confirmed');
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->bookings->reject($booking, $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'booking-rejected');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->bookings->cancel($booking, $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'booking-cancelled');
    }
}
