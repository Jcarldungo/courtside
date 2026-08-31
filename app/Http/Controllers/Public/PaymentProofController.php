<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentProofRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;

class PaymentProofController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function store(StorePaymentProofRequest $request, Booking $booking): RedirectResponse
    {
        // Foldered by date so a venue reviewing last Saturday's receipts is not
        // scrolling through a single directory of ten thousand screenshots.
        $path = $request->file('proof')->store(
            'proofs/'.$booking->starts_at->format('Y/m'),
            'public'
        );

        $this->bookings->attachProof(
            booking: $booking,
            path: $path,
            reference: $request->input('payment_reference'),
        );

        return redirect()
            ->route('bookings.show', $booking->reference)
            ->with('status', 'proof-uploaded');
    }
}
