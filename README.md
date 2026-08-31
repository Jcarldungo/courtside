# Courtside

A booking system for sports venues that only have a Facebook page.

Built for pickleball courts in Pampanga, Philippines — where bookings today
happen in Facebook comments, availability lives in someone's memory, and
"proof of payment" is a GCash screenshot forwarded to a personal number.
Courtside replaces that with a real public site, a conflict-safe booking
grid, and a staff dashboard, while keeping the GCash-first payment flow that
customers already trust.

**Live demo:** _link goes here once deployed_ · one tap to view as the owner,
one tap to reset the data · **[Portfolio](https://janncarl.vercel.app)**

**Stack:** Laravel 12 · Inertia 2 · React 19 + TypeScript · Tailwind 4 ·
MySQL · database queue · Pest

---

## The centrepiece: preventing double-booking under real contention

Prime time at a Philippine pickleball court is 6–9pm. Two people tap "Book
Court 2, 7pm" in the same second. The obvious implementation —

```php
if (! $court->isBookedAt($time)) {
    Booking::create([...]);
}
```

— passes code review and fails in production, because the check and the
write are two separate round-trips to the database. Between them, a second
request can read the same "still open" state and write a conflicting
booking. No amount of care in the application layer closes that window;
only the database can, because only the database serialises the writes.

### The naive fix is also wrong

The obvious schema fix is a unique index:

```sql
UNIQUE (court_id, starts_at)
```

That's correct for exactly one day. The first time a hold expires or a
customer cancels, the dead row keeps its `(court_id, starts_at)` values —
and poisons that slot forever. Nobody can ever book Court 2 at 7pm again.

### What Courtside actually does

MySQL has no partial indexes, so the fix is to make the indexed value `NULL`
for rows that no longer occupy the court, and rely on `NULL`s being distinct
inside a unique index — unlimited *dead* rows may share a slot, but only one
*live* row can hold it:

```sql
active_slot_at DATETIME AS (
    CASE WHEN status IN ('pending','confirmed') THEN starts_at END
) STORED,

UNIQUE KEY bookings_court_active_slot_unique (court_id, active_slot_at)
```

`active_slot_at` is a **stored generated column**, not a value the
application maintains. That's the whole point: there is no code path — no
service method, no `tinker` session, no future teammate's raw query — that
can insert a conflicting live booking. The constraint is a property of the
data, not a habit of the codebase.

Maintenance blocks live in the *same* table as customer bookings
(`bookings.kind = 'maintenance'`), so this one index guards both directions:
a customer cannot book a court staff just blocked, and staff cannot block a
court-hour a customer already paid for.

```php
// app/Services/BookingService.php
protected function insert(Court $court, CarbonImmutable $startsAt, array $attributes): Booking
{
    try {
        return Booking::create([...]);
    } catch (QueryException $e) {
        if ($this->violates($e, 'bookings_court_active_slot_unique')) {
            throw new SlotUnavailableException(
                court: $court,
                requested: $startsAt,
                nextAvailable: $this->nextAvailable($court, $startsAt),
            );
        }
        throw $e;
    }
}
```

`BookingService` never asks "is this slot free?" before inserting. Such a
check would be theatre — between the `SELECT` and the `INSERT`, another
request can take the slot, which is the exact bug this is about. **The
insert *is* the check.** The service's real job is catching MySQL error
1062 on that specific index and turning it into an answer a customer can
act on: a `409 Conflict` (for API clients) or an Inertia redirect carrying
the same payload (browsers only understand 2xx/3xx/422), either way
including the *next open slot on that court* — so losing a race becomes one
more tap instead of a dead end.

### Proof, not assertion

This was verified directly against MariaDB before a line of Laravel was
written, and is covered by 12 Pest tests in
[`tests/Feature/Booking/SlotContentionTest.php`](tests/Feature/Booking/SlotContentionTest.php),
including one that bypasses the service layer entirely and writes straight
to the model — because everything *else* runs through `BookingService`, and
a test suite that never proves the *database* rejects a duplicate is only
testing that the application is well-behaved, not that a bug in it can't
double-book a court:

```php
it('is rejected by the database itself when the service layer is bypassed', function () {
    Booking::factory()->for($this->court)->create(['starts_at' => $this->peak]);

    expect(fn () => Booking::factory()->for($this->court)->create(['starts_at' => $this->peak]))
        ->toThrow(QueryException::class);
});

it('produces exactly one winner when the whole peak hour is contested at once', function () {
    // 10 concurrent hold attempts on the same court-slot
    // -> exactly 1 succeeds, 9 get SlotUnavailableException,
    //    and the table has exactly one live row for that slot.
});
```

---

## What's built

| | |
|---|---|
| **Public site** | Landing page (venue, rates, hours, location, live "tonight" availability teaser), courts×slots scoreboard grid, guest booking with no account required |
| **Payment hold** | Reservation sits `pending` for 15 minutes; a delayed **queued job** releases it precisely on time, backed by a **per-minute scheduled sweeper** that keeps working even if the queue worker itself is dead — the most likely failure mode on a ₱300/month host |
| **Admin** | Today's schedule (staff view of the same grid, with customer detail), confirm/reject a GCash receipt, block a court for maintenance — gated by real `auth`, no public registration |
| **Demo mode** | One tap to seed a realistic week (peak slots booked, receipts flagged for review, a maintenance block) and one tap to view it as the owner — **a court owner will never create an account to evaluate this** |

### Why these specific technical choices

- **Laravel**, because its queue system is what makes the 15-minute hold
  actually reliable, not just "usually works."
- **MySQL**, because a real client is most likely to land on Hostinger
  shared hosting, and the double-booking guarantee had to be proven on the
  exact engine a client will actually run — MariaDB, not SQLite. The whole
  test suite runs against MySQL for that reason (`phpunit.xml`), not the
  Laravel default.
- **`APP_TIMEZONE=Asia/Manila`, forced.** Laravel 11+ hardcodes
  `config('app.timezone')` to UTC regardless of what's in `.env` — the test
  suite caught this reading every 7pm slot as 3am the next day in Manila
  before a single booking page existed.

---

## Re-skinning for a different venue

Everything that changes between one venue and the next — name, logo colours,
hours, rates, photos, GCash details — lives in
[`config/venue.php`](config/venue.php). Colour is delivered as CSS custom
properties injected server-side
([`App\Support\Venue::cssVariables()`](app/Support/Venue.php)), which
Tailwind 4's `@theme` layer maps onto utility classes
([`resources/css/app.css`](resources/css/app.css)) — so a re-skin is:

1. Edit `config/venue.php` (name, hours, rates, GCash number, six hex colours).
2. Drop new photos into `public/images/venue/`.
3. `php artisan config:clear`.

No rebuild, no component touched, no search-and-replace through JSX. The
`unit`/`units` config keys ("court"/"courts") mean the same system re-skins
to a badminton hall, a KTV room, or a car wash bay without a code change —
this is deliberately not pickleball-specific under the hood.

---

## Running locally

Requires PHP 8.2+, Composer, Node 20+, and a MySQL/MariaDB server.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
# Edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD to match your MySQL setup

php artisan migrate
php artisan db:seed          # courts + an owner account (password printed once)
php artisan storage:link

npm run build                # or `npm run dev` alongside `php artisan serve`
php artisan serve
```

### Try the demo

```bash
php artisan courtside:demo   # seeds a realistic week
```

Then set `DEMO_MODE=true` in `.env` and visit the site — the banner's
**"View as owner"** link signs you into the admin with no login form, and
**"Reset demo data"** reseeds relative to whatever "today" is when you click
it. Both routes 404 when `DEMO_MODE=false`, which is what makes them safe to
ship at all — a real client's `.env` sets it false and neither route exists.

### Adding a staff or owner account

There is no public registration — a booking system's admin isn't something
strangers should be able to sign up for. The venue owner runs:

```bash
php artisan courtside:staff "Jane Dela Cruz" jane@venue.test           # staff
php artisan courtside:staff "Jane Dela Cruz" jane@venue.test --owner   # owner
```

The password is printed once and isn't stored anywhere retrievable.

### Tests

```bash
php artisan test        # 86 tests, all against a real MySQL database
npm run types            # tsc --noEmit
```

---

## Deploying

Deployed to [Railway](https://railway.app) as three services from this
repo: the web app, a queue worker (`php artisan queue:work`), and managed
MySQL. The scheduler (`php artisan schedule:run`, driven by cron) is what
releases an abandoned hold if the queue worker itself is ever down — see
[`routes/console.php`](routes/console.php).

For a client hosted on Hostinger shared hosting (no persistent queue
worker), `courtside:release-holds` alone — wired to cron — is enough; the
queued job is the *precise* mechanism, the sweeper is the *unconditional*
one, and either can carry the guarantee on its own.

---

## What's deliberately not here (v1 scope)

Player matchmaking, open-play headcount caps, memberships, leagues, player
ratings, a real payment gateway, SMS. Pampanga's pickleball scene already
runs on [Reclub](https://pickleball.reclub.co/) for exactly that — round
robins, RSVP sessions, DUPR ratings — and Reclub doesn't sell court-hours or
give a venue its own website. Courtside and Reclub aren't competing for the
same job; a venue's `open_play_url` config key links straight to whichever
one that venue already uses.

---

## Author

Built by **Jann Carl** — 4th-year BSIT (Web Development), Holy Angel
University, Pampanga. Portfolio: [janncarl.vercel.app](https://janncarl.vercel.app).

Available for freelance work building booking/reservation systems for
Philippine small businesses — courts, clinics, studios, event spaces —
that currently run on a Facebook page and a notebook.
