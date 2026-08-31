<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the one-tap demo: a realistic week of bookings a court owner can
 * evaluate without creating an account or entering a single booking by hand.
 *
 * Every row here is created through BookingService -- the same hold/attachProof
 * /confirm/block calls a real customer or staff member would trigger -- rather
 * than inserted directly. That means the demo data can never contradict the
 * schema's own guarantee, and a reset can never silently produce a double
 * booking; a collision throws exactly as it would in production, and is
 * simply skipped.
 *
 * Content is scripted relative to CarbonImmutable::today(), not fixed
 * calendar dates, so "Reset demo" always produces a week that starts today --
 * a demo seeded once and never refreshed would look stale within a day.
 */
class DemoSeeder
{
    public function __construct(protected BookingService $bookings) {}

    /** Referenced by every scripted demo booking that needs a receipt on file. */
    protected const DEMO_PROOF_PATH = 'proofs/demo-gcash-receipt.png';

    public function reseed(): void
    {
        $this->ensureDemoProofImage();

        DB::transaction(function () {
            Booking::query()->delete();

            $courts = Court::active()->inDisplayOrder()->get();

            if ($courts->count() < 4) {
                return;
            }

            $owner = User::where('role', 'owner')->first() ?? User::factory()->owner()->create([
                'name' => 'Venue Owner',
                'email' => 'owner@courtside.test',
            ]);

            foreach ($this->script() as $row) {
                $this->apply($row, $courts, $owner);
            }
        });
    }

    /**
     * The week's content. 'court' is an index into the venue's active courts
     * in display order (0-3), always Court 1-4 regardless of database id.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function script(): array
    {
        $names = [
            ['Miguel Santos', '09171234501'], ['Ana Reyes', '09171234502'], ['Katrina Lim', '09171234503'],
            ['Paolo Gutierrez', '09171234504'], ['Bea Mercado', '09171234505'], ['Rico Panganiban', '09171234506'],
            ['Divine Tolentino', '09171234507'], ['Karl Bautista', '09171234508'], ['Jasmine Ocampo', '09171234509'],
            ['Dennis Yabut', '09171234510'], ['Trisha Manalo', '09171234511'], ['Joshua dela Cruz', '09171234512'],
        ];
        $guest = fn (int $i) => ['name' => $names[$i][0], 'phone' => $names[$i][1]];

        return [
            // Today: only slots still ahead of "now" can ever be booked (the
            // same guard a real customer hits), so today is intentionally
            // light -- whatever's left of the evening when the demo is viewed.
            ['day' => 0, 'court' => 0, 'time' => '18:00', 'status' => 'confirmed', 'guest' => $guest(0)],
            ['day' => 0, 'court' => 1, 'time' => '18:00', 'status' => 'confirmed', 'guest' => $guest(2)],
            ['day' => 0, 'court' => 0, 'time' => '19:00', 'status' => 'awaiting_verification', 'guest' => $guest(3)],
            ['day' => 0, 'court' => 2, 'time' => '20:00', 'status' => 'awaiting_payment', 'guest' => $guest(4)],

            // Tomorrow: the busiest scripted day -- peak mostly sold, one more
            // receipt waiting on staff, plus a maintenance block, so the admin
            // view has all four states to show on a single day.
            ['day' => 1, 'court' => 0, 'time' => '18:00', 'status' => 'confirmed', 'guest' => $guest(5)],
            ['day' => 1, 'court' => 1, 'time' => '19:00', 'status' => 'confirmed', 'guest' => $guest(6)],
            ['day' => 1, 'court' => 2, 'time' => '20:00', 'status' => 'confirmed', 'guest' => $guest(7)],
            ['day' => 1, 'court' => 3, 'time' => '19:00', 'status' => 'awaiting_verification', 'guest' => $guest(8)],
            ['day' => 1, 'court' => 1, 'time' => '10:00', 'status' => 'confirmed', 'guest' => $guest(9)],
            ['day' => 1, 'court' => 3, 'time' => '09:00', 'status' => 'maintenance', 'reason' => 'Deep cleaning before the weekend'],

            // The rest of the week: enough booked to feel like a real venue,
            // plenty open so the booking flow has room to actually demo.
            ['day' => 2, 'court' => 0, 'time' => '19:00', 'status' => 'confirmed', 'guest' => $guest(10)],
            ['day' => 2, 'court' => 2, 'time' => '07:00', 'status' => 'confirmed', 'guest' => $guest(11)],
            ['day' => 3, 'court' => 1, 'time' => '18:00', 'status' => 'confirmed', 'guest' => $guest(0)],
            ['day' => 4, 'court' => 3, 'time' => '20:00', 'status' => 'confirmed', 'guest' => $guest(1)],

            // Weekend: busier, matching how a real pickleball court fills up.
            ['day' => 5, 'court' => 0, 'time' => '08:00', 'status' => 'confirmed', 'guest' => $guest(2)],
            ['day' => 5, 'court' => 1, 'time' => '18:00', 'status' => 'confirmed', 'guest' => $guest(3)],
            ['day' => 5, 'court' => 2, 'time' => '19:00', 'status' => 'confirmed', 'guest' => $guest(4)],
            ['day' => 6, 'court' => 0, 'time' => '09:00', 'status' => 'confirmed', 'guest' => $guest(5)],
            ['day' => 6, 'court' => 3, 'time' => '18:00', 'status' => 'confirmed', 'guest' => $guest(6)],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, Court>  $courts
     */
    protected function apply(array $row, Collection $courts, User $owner): void
    {
        $court = $courts->get($row['court']);

        if (! $court) {
            return;
        }

        $startsAt = CarbonImmutable::today()->addDays($row['day'])->setTimeFromTimeString($row['time']);

        if ($startsAt->isPast()) {
            return;
        }

        try {
            if ($row['status'] === 'maintenance') {
                $this->bookings->block($court, $startsAt, $owner, $row['reason']);

                return;
            }

            $booking = $this->bookings->hold($court, $startsAt, $row['guest'], staff: $owner);

            match ($row['status']) {
                'confirmed' => (function () use ($booking, $owner) {
                    $this->bookings->attachProof($booking, self::DEMO_PROOF_PATH, 'GC'.$booking->reference);
                    $this->bookings->confirm($booking, $owner);
                })(),
                'awaiting_verification' => $this->bookings->attachProof($booking, self::DEMO_PROOF_PATH, 'GC'.$booking->reference),
                default => null,
            };
        } catch (SlotUnavailableException|BookingException) {
            // Scripted slots don't collide by construction, but seeding is
            // not the place to let a fixture bug take down the whole reset.
        }
    }

    /**
     * A generated stand-in receipt, not a real GCash screenshot -- there is no
     * such thing for a fictional demo payment. Rendering an unrelated stock
     * photo as though it were a receipt would be more dishonest than a plain
     * labelled placeholder, and the persistent DEMO banner already tells
     * anyone looking that nothing here is a real transaction.
     */
    protected function ensureDemoProofImage(): void
    {
        if (Storage::disk('public')->exists(self::DEMO_PROOF_PATH)) {
            return;
        }

        $image = imagecreatetruecolor(640, 960);
        imagefilledrectangle($image, 0, 0, 640, 960, imagecolorallocate($image, 245, 247, 246));
        imagefilledrectangle($image, 0, 0, 640, 140, imagecolorallocate($image, 31, 111, 74));

        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 18, 33, 27);
        $muted = imagecolorallocate($image, 120, 132, 126);

        imagestring($image, 5, 40, 55, 'GCash', $white);
        imagestring($image, 4, 40, 200, 'DEMO RECEIPT', $ink);
        imagestring($image, 3, 40, 230, '(not a real payment)', $muted);
        imagestring($image, 4, 40, 320, 'Amount:  P400.00', $ink);
        imagestring($image, 4, 40, 360, 'To:      RALLY POINT PICKLEBALL', $ink);
        imagestring($image, 4, 40, 400, 'Ref No:  DEMO0000000000', $ink);
        imagestring($image, 3, 40, 900, 'Generated for the Courtside demo.', $muted);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put(self::DEMO_PROOF_PATH, $contents);
    }
}
