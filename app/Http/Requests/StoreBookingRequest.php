<?php

namespace App\Http\Requests;

use App\Support\SlotSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    /** Guests book without an account, on purpose. */
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
            'court_id' => [
                'required',
                'integer',
                // A closed court is not merely hidden -- it cannot be booked
                // through a stale tab or a hand-crafted request either.
                Rule::exists('courts', 'id')->where('is_active', true),
            ],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'customer_name' => ['required', 'string', 'min:2', 'max:80'],
            // Philippine mobile numbers: 11 digits starting 09. The venue calls
            // this number when the weather closes the courts, so it has to be real.
            'customer_phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'court_id.required' => 'Choose a court.',
            'court_id.exists' => 'That court is not accepting bookings right now.',
            'starts_at.required' => 'Choose a time slot.',
            'customer_name.required' => 'We need a name for the booking.',
            'customer_phone.regex' => 'Enter an 11-digit mobile number starting with 09.',
        ];
    }

    /**
     * Slot rules the schema cannot express: on the grid, not in the past, not
     * past the booking horizon.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('starts_at')) {
                    return;
                }

                $schedule = app(SlotSchedule::class);
                $startsAt = $this->slotStartsAt();

                if (! $schedule->isValidSlot($startsAt)) {
                    $validator->errors()->add('starts_at', 'That is not one of our booking slots.');

                    return;
                }

                if ($startsAt->isPast()) {
                    $validator->errors()->add('starts_at', 'That slot has already started. Pick a later one.');

                    return;
                }

                if (! $schedule->isWithinBookingWindow($startsAt)) {
                    $validator->errors()->add('starts_at', sprintf(
                        'Bookings open %d days in advance.',
                        $schedule->advanceDays()
                    ));
                }
            },
        ];
    }

    public function slotStartsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->string('starts_at')->toString());
    }

    /**
     * @return array{name: string, phone: string}
     */
    public function guest(): array
    {
        return [
            'name' => $this->string('customer_name')->trim()->toString(),
            'phone' => $this->string('customer_phone')->trim()->toString(),
        ];
    }
}
