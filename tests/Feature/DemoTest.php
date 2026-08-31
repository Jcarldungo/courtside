<?php

/**
 * One-tap demo mode: a court owner evaluating this system creates no account
 * and never sees data that has visibly gone stale since it was last touched.
 *
 * Every route here must 404 the moment DEMO_MODE is false, because 'enter'
 * signs a stranger in as the owner with no password and 'reset' wipes every
 * booking -- neither is a risk a real client's deployment can carry.
 */

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Services\DemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Court::factory()->count(4)->sequence(
        ['name' => 'Court 1', 'sort_order' => 0],
        ['name' => 'Court 2', 'sort_order' => 1],
        ['name' => 'Court 3', 'sort_order' => 2],
        ['name' => 'Court 4', 'sort_order' => 3],
    )->create();
});

it('refuses to enter or reset the demo when demo mode is off', function () {
    config(['venue.demo_mode' => false]);

    $this->get(route('demo.enter'))->assertNotFound();
    $this->post(route('demo.reset'))->assertNotFound();
});

it('signs a visitor straight into the owner admin view with no login form', function () {
    config(['venue.demo_mode' => true]);
    $owner = User::factory()->owner()->create();

    $this->get(route('demo.enter'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($owner);
});

it('404s entering the demo if nobody has seeded an owner yet', function () {
    config(['venue.demo_mode' => true]);

    $this->get(route('demo.enter'))->assertNotFound();
});

it('reseeds a realistic week: booked peak slots, a few pending payments, one maintenance block', function () {
    config(['venue.demo_mode' => true]);
    Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', 'Asia/Manila'));

    $this->post(route('demo.reset'))->assertRedirect();

    expect(Booking::customerBookings()->where('status', BookingStatus::Confirmed)->count())->toBeGreaterThan(5)
        ->and(Booking::customerBookings()->where('status', BookingStatus::Pending)->count())->toBeGreaterThanOrEqual(2)
        ->and(Booking::maintenance()->count())->toBe(1)
        ->and(User::where('role', 'owner')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('wipes the previous demo state on every reset rather than accumulating', function () {
    config(['venue.demo_mode' => true]);
    Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', 'Asia/Manila'));

    $this->post(route('demo.reset'));
    $firstCount = Booking::count();

    $this->post(route('demo.reset'));
    $secondCount = Booking::count();

    expect($firstCount)->toBeGreaterThan(0)
        ->and($secondCount)->toBe($firstCount);

    Carbon::setTestNow();
});

it('never seeds a slot earlier than the current moment', function () {
    config(['venue.demo_mode' => true]);
    Carbon::setTestNow(Carbon::parse('2026-09-05 21:45:00', 'Asia/Manila'));

    app(DemoSeeder::class)->reseed();

    $todayEarlier = Booking::query()
        ->whereDate('starts_at', '2026-09-05')
        ->where('starts_at', '<', Carbon::now())
        ->count();

    expect($todayEarlier)->toBe(0);

    Carbon::setTestNow();
});

it('reset is reachable from a logged-in admin session too, not only the public site', function () {
    config(['venue.demo_mode' => true]);
    $owner = User::factory()->owner()->create();
    Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', 'Asia/Manila'));

    $this->actingAs($owner)->post(route('demo.reset'))->assertRedirect();

    expect(Booking::count())->toBeGreaterThan(0);

    Carbon::setTestNow();
});
