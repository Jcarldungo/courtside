<?php

namespace Database\Factories;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * Filipino names, local mobile prefixes: seeded data should read like a real
     * venue's book, because screenshots of lorem ipsum sell nothing.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = Carbon::tomorrow()->setTime(10, 0);

        return [
            'reference' => Booking::newReference(),
            'court_id' => Court::factory(),
            'kind' => BookingKind::Booking,
            'status' => BookingStatus::Pending,
            'starts_at' => $starts,
            'ends_at' => fn (array $attributes) => Carbon::parse($attributes['starts_at'])->addHour(),
            'customer_name' => fake()->randomElement([
                'Miguel Santos', 'Ana Reyes', 'Joshua dela Cruz', 'Katrina Lim',
                'Paolo Gutierrez', 'Bea Mercado', 'Rico Panganiban', 'Divine Tolentino',
                'Karl Bautista', 'Jasmine Ocampo', 'Dennis Yabut', 'Trisha Manalo',
            ]),
            'customer_phone' => '09'.fake()->numerify('#########'),
            'amount_centavos' => 40000,
            'is_peak' => false,
            'hold_expires_at' => now()->addMinutes(15),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Confirmed,
            'hold_expires_at' => null,
            'payment_reference' => strtoupper(fake()->bothify('??####????')),
            'payment_proof_path' => 'proofs/demo-gcash-receipt.png',
            'proof_uploaded_at' => now()->subMinutes(fake()->numberBetween(20, 600)),
            'confirmed_at' => now()->subMinutes(fake()->numberBetween(5, 15)),
        ]);
    }

    /** Proof uploaded, waiting on staff to check it against the GCash app. */
    public function awaitingVerification(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Pending,
            'payment_reference' => strtoupper(fake()->bothify('??####????')),
            'payment_proof_path' => 'proofs/demo-gcash-receipt.png',
            'proof_uploaded_at' => now()->subMinutes(fake()->numberBetween(1, 10)),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Expired,
            'hold_expires_at' => now()->subMinutes(fake()->numberBetween(1, 300)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now()->subHours(fake()->numberBetween(1, 48)),
            'cancellation_reason' => fake()->randomElement([
                'Customer requested reschedule',
                'Payment not received',
                'Wrong slot booked',
            ]),
        ]);
    }

    public function maintenance(string $reason = 'Court resurfacing'): static
    {
        return $this->state(fn () => [
            'kind' => BookingKind::Maintenance,
            'status' => BookingStatus::Confirmed,
            'customer_name' => null,
            'customer_phone' => null,
            'amount_centavos' => 0,
            'hold_expires_at' => null,
            'notes' => $reason,
        ]);
    }

    public function at(Carbon $startsAt): static
    {
        return $this->state(fn () => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
        ]);
    }
}
