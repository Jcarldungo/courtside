<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Services\BookingService;
use App\Support\Venue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    /**
     * Take the slot.
     *
     * There is no availability check here. SlotUnavailableException is raised by
     * the database losing patience with a duplicate, and is turned into a 409 or
     * a redirect-with-conflict by the handler in bootstrap/app.php.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $court = Court::findOrFail($request->integer('court_id'));

        $booking = $this->bookings->hold(
            court: $court,
            startsAt: $request->slotStartsAt(),
            guest: $request->guest(),
        );

        return redirect()
            ->route('bookings.show', $booking->reference)
            ->with('status', 'slot-held');
    }

    /** The customer's own view of their booking: pay here, or see where it stands. */
    public function show(Booking $booking): Response
    {
        $booking->load('court');

        return Inertia::render('Public/Booking', [
            'booking' => [
                'reference' => $booking->reference,
                'court_name' => $booking->court->name,
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
                'date_label' => $booking->starts_at->isoFormat('dddd, D MMMM YYYY'),
                'time_label' => $booking->timeLabel(),
                'amount_label' => $booking->amountLabel(),
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'is_peak' => $booking->is_peak,
                'has_proof' => $booking->hasProof(),
                'payment_reference' => $booking->payment_reference,
                'hold_seconds_remaining' => $booking->holdSecondsRemaining(),
                'proof_url' => $booking->payment_proof_path
                    ? asset('storage/'.$booking->payment_proof_path)
                    : null,
                'cancellation_reason' => $booking->cancellation_reason,
            ],
            'payment' => config('venue.payment'),
            'hold_minutes' => config('venue.hold_minutes'),
            'contact' => config('venue.contact'),
            'venue_hours' => Venue::toArray()['hours'],
        ]);
    }
}
