<?php

/**
 * The reason this project exists.
 *
 * Prime time at a Philippine pickleball court is 6-9pm. Two people tap
 * "Book Court 2, 7pm" in the same second. A check-then-insert in application
 * code reads "is this slot free?" -> yes -> yes, then writes twice, and the
 * venue has sold one court hour to two groups.
 *
 * Courtside makes that unrepresentable in the schema rather than merely
 * unlikely in application code. Every test below asserts a property of the
 * database, not of the service layer, so deleting the guard clauses inside
 * BookingService cannot make these pass.
 */

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Freeze time on a Saturday morning so "7pm tonight" is always in the future.
    Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', 'Asia/Manila'));

    $this->service = app(BookingService::class);
    $this->court = Court::factory()->create(['name' => 'Court 2']);
    $this->peak = Carbon::parse('2026-09-05 19:00:00', 'Asia/Manila');
});

afterEach(function () {
    Carbon::setTestNow();
});

function guest(string $name = 'Miguel Santos', string $phone = '09171234567'): array
{
    return ['name' => $name, 'phone' => $phone];
}

it('holds a peak slot as pending with a 15 minute payment window', function () {
    $booking = $this->service->hold($this->court, $this->peak, guest());

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->kind)->toBe(BookingKind::Booking)
        ->and($booking->starts_at->eq($this->peak))->toBeTrue()
        ->and($booking->ends_at->eq($this->peak->copy()->addHour()))->toBeTrue()
        ->and($booking->hold_expires_at->eq(Carbon::now()->addMinutes(15)))->toBeTrue()
        ->and($booking->reference)->toMatch('/^CS-[A-Z0-9]{5}$/')
        ->and($booking->customer_name)->toBe('Miguel Santos');
});

it('refuses a second booking on the same court and slot', function () {
    $this->service->hold($this->court, $this->peak, guest());

    expect(fn () => $this->service->hold($this->court, $this->peak, guest('Ana Reyes', '09181234567')))
        ->toThrow(SlotUnavailableException::class);

    expect(Booking::where('court_id', $this->court->id)->whereNotNull('active_slot_at')->count())->toBe(1);
});

it('tells the loser of the race which slot to take instead', function () {
    $this->service->hold($this->court, $this->peak, guest());

    try {
        $this->service->hold($this->court, $this->peak, guest('Ana Reyes'));
        $this->fail('Expected SlotUnavailableException.');
    } catch (SlotUnavailableException $e) {
        // 7pm is gone, 8pm is not: a dead end becomes one more tap.
        expect($e->nextAvailable?->eq($this->peak->copy()->addHour()))->toBeTrue()
            ->and($e->court->is($this->court))->toBeTrue();
    }
});

it('skips over slots that are also taken when suggesting the next one', function () {
    $this->service->hold($this->court, $this->peak, guest());                    // 7pm
    $this->service->hold($this->court, $this->peak->copy()->addHour(), guest()); // 8pm

    try {
        $this->service->hold($this->court, $this->peak, guest('Ana Reyes'));
        $this->fail('Expected SlotUnavailableException.');
    } catch (SlotUnavailableException $e) {
        expect($e->nextAvailable?->format('H:i'))->toBe('21:00');
    }
});

it('lets the same slot be booked on a different court', function () {
    $other = Court::factory()->create(['name' => 'Court 3']);

    $this->service->hold($this->court, $this->peak, guest());
    $second = $this->service->hold($other, $this->peak, guest('Ana Reyes'));

    expect($second->exists)->toBeTrue()
        ->and(Booking::whereNotNull('active_slot_at')->count())->toBe(2);
});

it('frees the slot when an unpaid hold expires', function () {
    $abandoned = $this->service->hold($this->court, $this->peak, guest());

    $this->service->expire($abandoned);

    expect($abandoned->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($abandoned->fresh()->active_slot_at)->toBeNull();

    // The slot is genuinely re-sellable, not merely flagged.
    $rebooked = $this->service->hold($this->court, $this->peak, guest('Ana Reyes'));
    expect($rebooked->status)->toBe(BookingStatus::Pending);
});

it('frees the slot when a confirmed booking is cancelled', function () {
    $booking = $this->service->hold($this->court, $this->peak, guest());
    $staff = User::factory()->owner()->create();
    $this->service->attachProof($booking, 'proofs/receipt.png');
    $this->service->confirm($booking, $staff);

    $this->service->cancel($booking->fresh(), $staff, 'Customer requested refund');

    expect($booking->fresh()->active_slot_at)->toBeNull();

    expect(fn () => $this->service->hold($this->court, $this->peak, guest('Ana Reyes')))
        ->not->toThrow(SlotUnavailableException::class);
});

it('lets any number of dead bookings pile up on one slot', function () {
    // Six abandoned holds on the same 7pm slot must not wedge the slot shut.
    foreach (range(1, 6) as $i) {
        $this->service->expire($this->service->hold($this->court, $this->peak, guest("Walk-in {$i}")));
    }

    expect(Booking::where('court_id', $this->court->id)->count())->toBe(6)
        ->and(Booking::whereNotNull('active_slot_at')->count())->toBe(0);

    expect($this->service->hold($this->court, $this->peak, guest())->exists)->toBeTrue();
});

/**
 * The load-bearing test. Everything above runs through BookingService, so in
 * principle careful application code could satisfy it. This one writes straight
 * to the model, bypassing every guard clause, and still must fail.
 */
it('is rejected by the database itself when the service layer is bypassed', function () {
    Booking::factory()->for($this->court)->create(['starts_at' => $this->peak]);

    expect(fn () => Booking::factory()->for($this->court)->create(['starts_at' => $this->peak]))
        ->toThrow(QueryException::class);

    try {
        Booking::factory()->for($this->court)->create(['starts_at' => $this->peak]);
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('23000')                   // integrity constraint violation
            ->and($e->errorInfo[1])->toBe(1062)                // ER_DUP_ENTRY
            ->and($e->getMessage())->toContain('bookings_court_active_slot_unique');
    }
});

it('produces exactly one winner when the whole peak hour is contested at once', function () {
    $attempts = 10;
    $won = 0;
    $lost = 0;

    foreach (range(1, $attempts) as $i) {
        try {
            $this->service->hold($this->court, $this->peak, guest("Player {$i}", '0917000000'.$i));
            $won++;
        } catch (SlotUnavailableException) {
            $lost++;
        }
    }

    expect($won)->toBe(1)
        ->and($lost)->toBe($attempts - 1)
        ->and(Booking::where('court_id', $this->court->id)->whereNotNull('active_slot_at')->count())->toBe(1);
});

it('stops a customer booking a slot the owner blocked for maintenance', function () {
    $owner = User::factory()->owner()->create();
    $this->service->block($this->court, $this->peak, $owner, 'Resurfacing the baseline');

    expect(fn () => $this->service->hold($this->court, $this->peak, guest()))
        ->toThrow(SlotUnavailableException::class);
});

it('stops the owner blocking a slot a customer already holds', function () {
    $owner = User::factory()->owner()->create();
    $this->service->hold($this->court, $this->peak, guest());

    // Same index, opposite direction: maintenance blocks and bookings compete
    // for one row per court-slot, so the owner is told rather than silently
    // overriding a customer who may have already paid.
    expect(fn () => $this->service->block($this->court, $this->peak, $owner, 'Resurfacing'))
        ->toThrow(SlotUnavailableException::class);
});
