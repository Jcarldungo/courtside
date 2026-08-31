<?php

/**
 * The 15-minute payment hold.
 *
 * A reservation sits `pending` while the customer opens GCash, sends the money
 * and screenshots the receipt. If they wander off, the slot has to go back on
 * sale by itself -- a court cannot afford to have its 7pm Saturday held hostage
 * by someone who closed the tab.
 *
 * Two mechanisms, deliberately: a delayed queued job per booking (precise), and
 * a scheduled sweeper (unconditional). The sweeper exists because the queue
 * worker is the single most likely thing to be dead on a cheap host, and a hold
 * that never releases is worse than one that releases a minute late.
 */

use App\Enums\BookingStatus;
use App\Jobs\ReleaseExpiredHold;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', 'Asia/Manila'));

    $this->service = app(BookingService::class);
    $this->court = Court::factory()->create(['name' => 'Court 1']);
    $this->peak = Carbon::parse('2026-09-05 19:00:00', 'Asia/Manila');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('schedules the release the moment a hold is placed', function () {
    Queue::fake();

    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    Queue::assertPushed(ReleaseExpiredHold::class, function (ReleaseExpiredHold $job) use ($booking) {
        return $job->booking->is($booking)
            && Carbon::parse($job->delay)->eq($booking->hold_expires_at);
    });
});

it('releases an unpaid hold once the timer runs out', function () {
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    Carbon::setTestNow(Carbon::now()->addMinutes(16));
    (new ReleaseExpiredHold($booking))->handle($this->service);

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($booking->fresh()->active_slot_at)->toBeNull();

    // And the court-hour is genuinely back on sale.
    expect($this->service->hold($this->court, $this->peak, ['name' => 'Ana Reyes', 'phone' => '09181234567'])->exists)
        ->toBeTrue();
});

it('leaves a hold alone while the customer is still inside the window', function () {
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    // Job fires early -- a retry, a clock skew, a duplicate push.
    Carbon::setTestNow(Carbon::now()->addMinutes(5));
    (new ReleaseExpiredHold($booking))->handle($this->service);

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

it('never expires a hold once GCash proof has been uploaded', function () {
    Storage::fake('public');
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->service->attachProof($booking, 'proofs/receipt.png', 'GC2409X8842');

    // Timer is cleared on upload: the customer has paid, so the slot now waits
    // on staff rather than on a clock.
    expect($booking->fresh()->hold_expires_at)->toBeNull();

    Carbon::setTestNow(Carbon::now()->addHours(3));
    (new ReleaseExpiredHold($booking))->handle($this->service);

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending)
        ->and($booking->fresh()->active_slot_at)->not->toBeNull();
});

it('leaves a confirmed booking alone', function () {
    $staff = User::factory()->staff()->create();
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);
    $this->service->attachProof($booking, 'proofs/receipt.png');
    $this->service->confirm($booking, $staff);

    Carbon::setTestNow(Carbon::now()->addHours(3));
    (new ReleaseExpiredHold($booking))->handle($this->service);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('sweeps up holds the queue worker never got to', function () {
    // Three abandoned holds across the evening, timers long gone.
    $abandoned = collect([17, 18, 20])->map(fn (int $hour) => $this->service->hold(
        $this->court,
        $this->peak->copy()->setTime($hour, 0),
        ['name' => 'Walk-in', 'phone' => '09170000000'],
    ));

    Carbon::setTestNow(Carbon::now()->addMinutes(30));

    $this->artisan('courtside:release-holds')
        ->expectsOutputToContain('Released 3')
        ->assertSuccessful();

    expect(Booking::live()->count())->toBe(0)
        ->and($abandoned->every(fn (Booking $b) => $b->fresh()->status === BookingStatus::Expired))->toBeTrue();
});

it('sweeps nothing when every hold is still fresh', function () {
    $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->artisan('courtside:release-holds')
        ->expectsOutputToContain('Released 0')
        ->assertSuccessful();

    expect(Booking::live()->count())->toBe(1);
});

it('does not let the sweeper touch a booking that has been paid for', function () {
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);
    $this->service->attachProof($booking, 'proofs/receipt.png', 'GC2409X8842');

    Carbon::setTestNow(Carbon::now()->addDay());

    $this->artisan('courtside:release-holds')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

it('records who confirmed a payment and when', function () {
    $staff = User::factory()->staff()->create(['name' => 'Counter Staff']);
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);
    $this->service->attachProof($booking, 'proofs/receipt.png', 'GC2409X8842');

    $confirmed = $this->service->confirm($booking, $staff);

    expect($confirmed->status)->toBe(BookingStatus::Confirmed)
        ->and($confirmed->confirmed_by)->toBe($staff->id)
        ->and($confirmed->confirmed_at->eq(Carbon::now()))->toBeTrue()
        ->and($confirmed->hold_expires_at)->toBeNull();
});

it('refuses to confirm a hold nobody has paid on yet', function () {
    $staff = User::factory()->staff()->create();
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    expect(fn () => $this->service->confirm($booking, $staff))
        ->toThrow(\App\Exceptions\BookingException::class, 'No payment proof');

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

it('puts the slot back on sale when staff reject the proof', function () {
    $staff = User::factory()->staff()->create();
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);
    $this->service->attachProof($booking, 'proofs/receipt.png', 'WRONG-AMOUNT');

    $rejected = $this->service->reject($booking, $staff, 'Sent ₱300, slot costs ₱400');

    expect($rejected->status)->toBe(BookingStatus::Cancelled)
        ->and($rejected->active_slot_at)->toBeNull()
        ->and($rejected->cancellation_reason)->toBe('Sent ₱300, slot costs ₱400');

    expect($this->service->hold($this->court, $this->peak, ['name' => 'Ana Reyes', 'phone' => '09181234567'])->exists)
        ->toBeTrue();
});
