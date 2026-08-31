<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentProofRequest extends FormRequest
{
    /** The booking reference in the URL is the customer's credential. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A GCash receipt is a phone screenshot. Accept what phones produce,
            // and keep the ceiling low enough for mobile data in Pampanga.
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],
            'payment_reference' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proof.required' => 'Attach your GCash receipt screenshot.',
            'proof.image' => 'That file is not an image. Send the screenshot from your phone.',
            'proof.max' => 'That image is larger than 5MB. Try a screenshot instead of a photo.',
        ];
    }

    /**
     * A hold that already expired cannot be rescued by a late upload -- the slot
     * belongs to somebody else now, and saying so plainly beats taking a
     * screenshot the venue will never honour.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $booking = $this->route('booking');

                if (! $booking instanceof Booking) {
                    return;
                }

                if ($booking->status !== BookingStatus::Pending) {
                    $validator->errors()->add('proof', match ($booking->status) {
                        BookingStatus::Expired => 'This hold ran out before payment arrived, so the slot went back on sale. Please book again.',
                        BookingStatus::Cancelled => 'This booking was cancelled.',
                        default => 'This booking is already confirmed. Nothing more to send.',
                    });
                }
            },
        ];
    }
}
