<?php

namespace App\Services;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Exceptions\SlotUnavailableException;
use App\Jobs\ReleaseExpiredHold;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Support\SlotSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

/**
 * Every write to the bookings table goes through here.
 *
 * Note what this class does NOT do: it never asks "is this slot free?" before
 * inserting. Such a check would be pure theatre -- between the SELECT and the
 * INSERT, another request can take the slot, which is the exact bug this
 * project is about. The INSERT *is* the check, and the unique index is the
 * authority. This class's real job is turning the resulting integrity violation
 * into an answer a customer can act on.
 */
class BookingService
{
    /** MySQL/MariaDB duplicate-entry error number. */
    private const DUPLICATE_ENTRY = 1062;

    private const SLOT_INDEX = 'bookings_court_active_slot_unique';

    /** Days to search ahead when suggesting an alternative slot. */
    private const SUGGESTION_HORIZON_DAYS = 7;

    public function __construct(protected SlotSchedule $schedule) {}

    /**
     * Place a payment hold on a court-slot for a guest.
     *
     * @param  array{name: string, phone: string}  $guest
     *
     * @throws SlotUnavailableException when the slot was taken first
     * @throws BookingException when the request is invalid on its face
     */
    public function hold(Court $court, CarbonInterface $startsAt, array $guest, ?User $staff = null): Booking
    {
        $startsAt = CarbonImmutable::instance($startsAt);

        $this->assertCourtOpen($court);
        $this->assertOnGrid($startsAt);
        $this->assertBookableWhen($startsAt, allowInProgress: $staff !== null);

        if (! $staff) {
            $this->assertWithinBookingWindow($startsAt);
        }

        $booking = $this->insert($court, $startsAt, [
            'kind' => BookingKind::Booking,
            'status' => BookingStatus::Pending,
            'customer_name' => $guest['name'],
            'customer_phone' => $guest['phone'],
            'amount_centavos' => $court->rateFor($startsAt),
            'is_peak' => $this->schedule->isPeak($startsAt),
            'hold_expires_at' => now()->addMinutes($this->schedule->holdMinutes()),
            'created_by' => $staff?->id,
        ]);

        // Release the slot the moment the payment window closes. The scheduled
        // sweeper covers the case where this job is never delivered.
        ReleaseExpiredHold::dispatch($booking)->delay($booking->hold_expires_at);

        return $booking;
    }

    /**
     * Close a court-slot for maintenance.
     *
     * Goes through the same index as a customer booking, so blocking a slot that
     * someone has already paid for fails loudly instead of quietly stranding
     * them at the gate.
     *
     * @throws SlotUnavailableException
     */
    public function block(Court $court, CarbonInterface $startsAt, User $staff, ?string $reason = null): Booking
    {
        $startsAt = CarbonImmutable::instance($startsAt);

        $this->assertOnGrid($startsAt);
        $this->assertBookableWhen($startsAt, allowInProgress: true);

        return $this->insert($court, $startsAt, [
            'kind' => BookingKind::Maintenance,
            'status' => BookingStatus::Confirmed,
            'customer_name' => null,
            'customer_phone' => null,
            'amount_centavos' => 0,
            'is_peak' => $this->schedule->isPeak($startsAt),
            'hold_expires_at' => null,
            'created_by' => $staff->id,
            'notes' => $reason,
        ]);
    }

    /**
     * Attach GCash proof to a pending hold.
     *
     * This clears the expiry timer on purpose. Once a customer has paid, the
     * slot must not evaporate under them on a schedule -- staff now owe them a
     * decision, and the booking waits in the admin queue instead.
     */
    public function attachProof(Booking $booking, string $path, ?string $reference = null): Booking
    {
        if ($booking->status !== BookingStatus::Pending) {
            throw BookingException::notPending();
        }

        $booking->update([
            'payment_proof_path' => $path,
            'payment_reference' => $reference,
            'proof_uploaded_at' => now(),
            'hold_expires_at' => null,
        ]);

        return $booking->refresh();
    }

    /** @throws BookingException */
    public function confirm(Booking $booking, User $staff): Booking
    {
        if ($booking->status !== BookingStatus::Pending) {
            throw BookingException::notPending();
        }

        $booking->update([
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => $staff->id,
            'hold_expires_at' => null,
        ]);

        return $booking->refresh();
    }

    /**
     * Release a slot back to the grid.
     *
     * @throws BookingException
     */
    public function cancel(Booking $booking, ?User $staff = null, ?string $reason = null): Booking
    {
        if (! $booking->status->isLive()) {
            throw BookingException::notLive();
        }

        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $staff?->id,
            'cancellation_reason' => $reason,
        ]);

        return $booking->refresh();
    }

    /** Staff rejected the GCash proof: the slot goes straight back on sale. */
    public function reject(Booking $booking, User $staff, ?string $reason = null): Booking
    {
        return $this->cancel($booking, $staff, $reason ?? 'Payment proof rejected');
    }

    /**
     * Release an unpaid hold.
     *
     * Deliberately unconditional about the clock: this is the mechanism, and the
     * queued job (or the sweeper backing it up) owns the policy of *when*.
     * Refuses only when the customer has already uploaded proof, because
     * expiring a paid booking on a timer would be the worst bug in the system.
     */
    public function expire(Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Pending || $booking->hasProof()) {
            return false;
        }

        $booking->update(['status' => BookingStatus::Expired]);
        $booking->refresh();

        return true;
    }

    /**
     * The next slot on this court that nobody holds.
     *
     * Called when a request loses the race, so that "that slot just went" can be
     * followed by "here is one that has not".
     */
    public function nextAvailable(Court $court, CarbonInterface $after): ?CarbonImmutable
    {
        $after = CarbonImmutable::instance($after);
        $horizon = $after->addDays(self::SUGGESTION_HORIZON_DAYS);

        $taken = Booking::query()
            ->where('court_id', $court->id)
            ->live()
            ->whereBetween('active_slot_at', [$after, $horizon])
            ->pluck('active_slot_at')
            ->map(fn (CarbonInterface $at) => $at->format('Y-m-d H:i'))
            ->flip();

        for ($day = 0; $day <= self::SUGGESTION_HORIZON_DAYS; $day++) {
            foreach ($this->schedule->slotsFor($after->addDays($day)) as $slot) {
                if ($slot->lessThanOrEqualTo($after) || $slot->isPast()) {
                    continue;
                }

                if (! $this->schedule->isWithinBookingWindow($slot)) {
                    return null;
                }

                if (! $taken->has($slot->format('Y-m-d H:i'))) {
                    return $slot;
                }
            }
        }

        return null;
    }

    /**
     * The one place a booking row is created.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws SlotUnavailableException
     */
    protected function insert(Court $court, CarbonImmutable $startsAt, array $attributes): Booking
    {
        $referenceAttempts = 0;

        while (true) {
            try {
                return Booking::create([
                    'reference' => Booking::newReference(),
                    'court_id' => $court->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $this->schedule->endFor($startsAt),
                    ...$attributes,
                ]);
            } catch (QueryException $e) {
                // Lost the race for the slot.
                if ($this->violates($e, self::SLOT_INDEX)) {
                    throw new SlotUnavailableException(
                        court: $court,
                        requested: $startsAt,
                        nextAvailable: $this->nextAvailable($court, $startsAt),
                    );
                }

                // Two customers drew the same reference code: vanishingly rare,
                // and nothing to trouble either of them with.
                if ($this->violates($e, 'bookings_reference_unique') && ++$referenceAttempts < 3) {
                    continue;
                }

                throw $e;
            }
        }
    }

    /** Did this exception come from the named unique index? */
    protected function violates(QueryException $e, string $index): bool
    {
        return ($e->errorInfo[1] ?? null) === self::DUPLICATE_ENTRY
            && str_contains($e->getMessage(), $index);
    }

    /** @throws BookingException */
    protected function assertCourtOpen(Court $court): void
    {
        if (! $court->is_active) {
            throw BookingException::courtUnavailable($court->name);
        }
    }

    /** @throws BookingException */
    protected function assertOnGrid(CarbonImmutable $startsAt): void
    {
        if (! $this->schedule->isValidSlot($startsAt)) {
            throw BookingException::notOnGrid($startsAt->format('g:ia'));
        }
    }

    /** @throws BookingException */
    protected function assertBookableWhen(CarbonImmutable $startsAt, bool $allowInProgress = false): void
    {
        $cutoff = $allowInProgress ? $this->schedule->endFor($startsAt) : $startsAt;

        if ($cutoff->isPast()) {
            throw BookingException::inThePast();
        }
    }

    /** @throws BookingException */
    protected function assertWithinBookingWindow(CarbonImmutable $startsAt): void
    {
        if (! $this->schedule->isWithinBookingWindow($startsAt)) {
            throw BookingException::tooFarAhead($this->schedule->advanceDays());
        }
    }
}
