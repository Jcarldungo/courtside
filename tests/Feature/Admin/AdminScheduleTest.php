<?php

/**
 * The staff side of the same schedule the public books from: today's grid,
 * confirm/reject a GCash receipt, block a court for maintenance.
 *
 * No account on this system is ever a customer account -- the users table
 * only ever holds owner/staff logins provisioned by `courtside:staff` -- so
 * `auth` alone is the correct gate here. There is no third role to exclude.
 */

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', 'Asia/Manila'));
    $this->service = app(BookingService::class);
    $this->court = Court::factory()->create(['name' => 'Court 1']);
    $this->peak = Carbon::parse('2026-09-05 19:00:00', 'Asia/Manila');
});

afterEach(fn () => Carbon::setTestNow());

it('sends a guest to log in rather than the schedule', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows a logged-in staff member the schedule with booking detail', function () {
    $staff = User::factory()->staff()->create();
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Schedule')
            ->where('board.courts.0.cells.13.state', 'pending')
            ->where('board.courts.0.cells.13.booking.customer_name', 'Miguel Santos')
            ->where('board.courts.0.cells.13.booking.customer_phone', '09171234567')
        );

    expect($booking->exists)->toBeTrue();
});

it('confirms a booking once a receipt is on file', function () {
    $staff = User::factory()->staff()->create();
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);
    $this->service->attachProof($booking, 'proofs/receipt.png', 'GC2409X8842');

    $this->actingAs($staff)
        ->post(route('admin.bookings.confirm', $booking))
        ->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->confirmed_by)->toBe($staff->id);
});

it('will not let staff confirm a booking with no receipt', function () {
    $staff = User::factory()->staff()->create();
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->actingAs($staff)
        ->post(route('admin.bookings.confirm', $booking))
        ->assertSessionHasErrors();

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

it('rejects a bad receipt and frees the slot immediately', function () {
    $staff = User::factory()->staff()->create();
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);
    $this->service->attachProof($booking, 'proofs/receipt.png');

    $this->actingAs($staff)
        ->post(route('admin.bookings.reject', $booking), ['reason' => 'Amount does not match'])
        ->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->fresh()->cancellation_reason)->toBe('Amount does not match')
        ->and($booking->fresh()->active_slot_at)->toBeNull();

    expect($this->service->hold($this->court, $this->peak, ['name' => 'Ana Reyes', 'phone' => '09181234567'])->exists)
        ->toBeTrue();
});

it('cancels a confirmed booking for a no-show or refund', function () {
    $staff = User::factory()->owner()->create();
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);
    $this->service->attachProof($booking, 'proofs/receipt.png');
    $this->service->confirm($booking, $staff);

    $this->actingAs($staff)
        ->post(route('admin.bookings.cancel', $booking), ['reason' => 'No-show'])
        ->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

it('blocks an open court for maintenance', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->post(route('admin.maintenance.store'), [
            'court_id' => $this->court->id,
            'starts_at' => '2026-09-05 19:00',
            'reason' => 'Resurfacing the baseline',
        ])
        ->assertRedirect();

    $block = Booking::maintenance()->sole();
    expect($block->court_id)->toBe($this->court->id)
        ->and($block->notes)->toBe('Resurfacing the baseline')
        ->and($block->active_slot_at)->not->toBeNull();
});

it('refuses to block a slot a customer already holds', function () {
    $staff = User::factory()->staff()->create();
    $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->actingAs($staff)
        ->post(route('admin.maintenance.store'), [
            'court_id' => $this->court->id,
            'starts_at' => '2026-09-05 19:00',
        ])
        ->assertSessionHasErrors();

    expect(Booking::maintenance()->count())->toBe(0);
});

it('removes a maintenance block through the same cancel action as a booking', function () {
    $staff = User::factory()->staff()->create();
    $block = $this->service->block($this->court, $this->peak, $staff, 'Resurfacing');

    $this->actingAs($staff)
        ->post(route('admin.bookings.cancel', $block))
        ->assertRedirect();

    expect($block->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($block->fresh()->active_slot_at)->toBeNull();

    expect($this->service->hold($this->court, $this->peak, ['name' => 'Ana Reyes', 'phone' => '09181234567'])->exists)
        ->toBeTrue();
});

it('lets staff look at yesterday to reconcile payments, unlike the public grid', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('dashboard', ['date' => '2026-09-01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('board.date', '2026-09-01'));
});

it('creates a staff login from the command line with a one-time password', function () {
    $this->artisan('courtside:staff', ['name' => 'Counter Staff', 'email' => 'staff@rallypoint.test'])
        ->assertSuccessful()
        ->expectsOutputToContain('staff@rallypoint.test');

    $user = User::where('email', 'staff@rallypoint.test')->sole();
    expect($user->role->value)->toBe('staff');
});

it('creates an owner login with the --owner flag', function () {
    $this->artisan('courtside:staff', ['name' => 'New Owner', 'email' => 'owner2@rallypoint.test', '--owner' => true])
        ->assertSuccessful();

    expect(User::where('email', 'owner2@rallypoint.test')->sole()->role->value)->toBe('owner');
});

it('refuses to create a second account on an email already in use', function () {
    User::factory()->create(['email' => 'taken@rallypoint.test']);

    $this->artisan('courtside:staff', ['name' => 'Dup', 'email' => 'taken@rallypoint.test'])
        ->assertFailed();
});
