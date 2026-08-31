<?php

/**
 * Caught by hand: at 9:45pm the landing page said peak hours were "fully
 * booked" when the truth was that peak hours (6-9pm) had simply ended 45
 * minutes earlier. Both states produce an empty open_peak_slots list, so the
 * distinction has to be computed explicitly rather than inferred from the
 * slot list being empty.
 */

use App\Models\Court;
use App\Services\BookingService;
use Illuminate\Support\Carbon;

it('says peak hours are over, not "fully booked", once peak has actually ended', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-05 21:45:00', 'Asia/Manila'));
    Court::factory()->create();

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('tonight.peak_has_passed', true)
            ->where('tonight.open_peak_count', 0));

    Carbon::setTestNow();
});

it('says peak is fully booked, not "over", when every peak slot is genuinely taken', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-05 17:00:00', 'Asia/Manila'));
    $court = Court::factory()->create();
    $service = app(BookingService::class);

    foreach (['18:00', '19:00', '20:00'] as $time) {
        $service->hold($court, Carbon::parse("2026-09-05 {$time}", 'Asia/Manila'), ['name' => 'Guest', 'phone' => '09171234567']);
    }

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('tonight.peak_has_passed', false)
            ->where('tonight.open_peak_count', 0));

    Carbon::setTestNow();
});

it('lists the open peak slots while peak hours are still ahead', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-05 10:00:00', 'Asia/Manila'));
    Court::factory()->create();

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('tonight.peak_has_passed', false)
            ->where('tonight.open_peak_count', 3));

    Carbon::setTestNow();
});
