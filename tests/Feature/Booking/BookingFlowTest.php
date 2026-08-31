<?php

/**
 * The public booking flow, end to end: see the grid, take a slot, pay, wait.
 *
 * The conflict tests here pin down the *contract*, not just the behaviour. A
 * lost race has to arrive at the customer as something they can act on, in both
 * shapes the app speaks: JSON for the documented API, and an Inertia redirect
 * carrying the same payload for the browser flow.
 */

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', 'Asia/Manila'));

    $this->service = app(BookingService::class);
    $this->court = Court::factory()->create(['name' => 'Court 1', 'sort_order' => 1]);
    $this->other = Court::factory()->create(['name' => 'Court 2', 'sort_order' => 2]);
    $this->peak = Carbon::parse('2026-09-05 19:00:00', 'Asia/Manila');
});

afterEach(function () {
    Carbon::setTestNow();
});

function payload(array $overrides = []): array
{
    return array_merge([
        'court_id' => null,
        'starts_at' => '2026-09-05 19:00',
        'customer_name' => 'Miguel Santos',
        'customer_phone' => '09171234567',
    ], $overrides);
}

it('shows the venue on the landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Landing')
            ->where('venue.name', 'Rally Point Pickleball')
            ->has('courts', 2)
            ->has('venue.amenities')
        );
});

it('shows a grid of courts and slots for a date', function () {
    $this->get(route('book', ['date' => '2026-09-05']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Book')
            ->where('board.date', '2026-09-05')
            // 06:00 to 21:00 inclusive on an hourly grid.
            ->has('board.slots', 16)
            ->has('board.courts', 2)
            ->where('board.courts.0.cells.13.state', 'open')
        );
});

it('marks a slot as taken on the grid once it is held', function () {
    $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->get(route('book', ['date' => '2026-09-05']))
        ->assertInertia(fn (Assert $page) => $page
            // 7pm is the 14th slot after a 6am open: index 13.
            ->where('board.courts.0.cells.13.state', 'taken')
            ->where('board.courts.1.cells.13.state', 'open')
        );
});

it('never leaks a customer name to the public grid', function () {
    $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $response = $this->get(route('book', ['date' => '2026-09-05']));

    expect($response->getContent())->not->toContain('Miguel Santos')
        ->and($response->getContent())->not->toContain('09171234567');
});

it('takes a slot and sends the customer to the payment page', function () {
    $response = $this->post(route('bookings.store'), payload(['court_id' => $this->court->id]));

    $booking = Booking::sole();

    $response->assertRedirect(route('bookings.show', $booking->reference));

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->customer_name)->toBe('Miguel Santos')
        ->and($booking->amount_centavos)->toBe(40000)   // peak rate captured at booking time
        ->and($booking->is_peak)->toBeTrue()
        ->and($booking->hold_expires_at->eq(Carbon::now()->addMinutes(15)))->toBeTrue();
});

it('answers a lost race with 409 and the next open slot', function () {
    $this->service->hold($this->court, $this->peak, ['name' => 'Ana Reyes', 'phone' => '09181234567']);

    $this->postJson(route('bookings.store'), payload(['court_id' => $this->court->id]))
        ->assertStatus(409)
        ->assertJsonPath('court.name', 'Court 1')
        ->assertJsonPath('next_available_label', '8:00pm')
        ->assertJsonStructure(['message', 'court' => ['id', 'name'], 'requested_at', 'next_available_at', 'next_available_label']);

    expect(Booking::count())->toBe(1);
});

it('sends the browser back with the conflict rather than an error screen', function () {
    $this->service->hold($this->court, $this->peak, ['name' => 'Ana Reyes', 'phone' => '09181234567']);

    // Inertia only understands 2xx, 3xx and 422, so the same payload arrives as
    // a redirect with flashed data instead of a raw 409.
    $this->post(route('bookings.store'), payload(['court_id' => $this->court->id]))
        ->assertRedirect()
        ->assertSessionHas('conflict', fn (array $conflict) => $conflict['next_available_label'] === '8:00pm'
            && str_contains($conflict['message'], 'was just taken'))
        ->assertSessionHasErrors('starts_at');
});

it('rejects a slot that is not on the venue grid', function () {
    $this->post(route('bookings.store'), payload(['court_id' => $this->court->id, 'starts_at' => '2026-09-05 19:30']))
        ->assertSessionHasErrors('starts_at');

    expect(Booking::count())->toBe(0);
});

it('rejects a booking after the venue has closed', function () {
    $this->post(route('bookings.store'), payload(['court_id' => $this->court->id, 'starts_at' => '2026-09-05 23:00']))
        ->assertSessionHasErrors('starts_at');
});

it('rejects a slot in the past', function () {
    $this->post(route('bookings.store'), payload(['court_id' => $this->court->id, 'starts_at' => '2026-09-05 07:00']))
        ->assertSessionHasErrors('starts_at');
});

it('rejects a slot beyond the booking window', function () {
    $this->post(route('bookings.store'), payload(['court_id' => $this->court->id, 'starts_at' => '2026-10-30 19:00']))
        ->assertSessionHasErrors('starts_at');
});

it('requires a name and a contactable mobile number', function () {
    $this->post(route('bookings.store'), payload([
        'court_id' => $this->court->id,
        'customer_name' => '',
        'customer_phone' => '12345',
    ]))->assertSessionHasErrors(['customer_name', 'customer_phone']);
});

it('will not book a court that is closed', function () {
    $closed = Court::factory()->inactive()->create(['name' => 'Court 9']);

    $this->post(route('bookings.store'), payload(['court_id' => $closed->id]))
        ->assertSessionHasErrors('court_id');
});

it('shows the customer their booking, its countdown and the GCash details', function () {
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->get(route('bookings.show', $booking->reference))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Booking')
            ->where('booking.reference', $booking->reference)
            ->where('booking.status', 'pending')
            ->where('booking.hold_seconds_remaining', 900)
            ->where('payment.account_number', '0917 555 0142')
        );
});

it('accepts a GCash screenshot and stops the clock', function () {
    Storage::fake('public');
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->post(route('bookings.proof.store', $booking->reference), [
        'proof' => \Illuminate\Http\UploadedFile::fake()->image('gcash.jpg', 900, 1600),
        'payment_reference' => 'GC2409X8842',
    ])->assertRedirect(route('bookings.show', $booking->reference));

    $booking->refresh();

    expect($booking->payment_proof_path)->not->toBeNull()
        ->and($booking->payment_reference)->toBe('GC2409X8842')
        ->and($booking->hold_expires_at)->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Pending);

    Storage::disk('public')->assertExists($booking->payment_proof_path);
});

it('refuses a proof upload on a slot that already expired', function () {
    Storage::fake('public');
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);
    $this->service->expire($booking);

    $this->post(route('bookings.proof.store', $booking->reference), [
        'proof' => \Illuminate\Http\UploadedFile::fake()->image('gcash.jpg'),
    ])->assertSessionHasErrors('proof');
});

it('rejects a proof upload that is not an image', function () {
    Storage::fake('public');
    $booking = $this->service->hold($this->court, $this->peak, ['name' => 'Miguel Santos', 'phone' => '09171234567']);

    $this->post(route('bookings.proof.store', $booking->reference), [
        'proof' => \Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf'),
    ])->assertSessionHasErrors('proof');
});
