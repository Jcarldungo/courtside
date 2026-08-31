<?php

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Why this table looks like this
    |--------------------------------------------------------------------------
    |
    | Two customers tap "Book Court 2, 7pm" in the same second. The obvious
    | implementation reads the slot, sees it free, and writes -- twice, because
    | both reads happened before either write. No amount of care in PHP closes
    | that window; only the database can, because only the database serialises
    | the writes.
    |
    | So the rule "one live booking per court per slot" is expressed as a unique
    | index, and the API's job shrinks to catching the violation and answering
    | politely.
    |
    | The naive index is a trap:
    |
    |     UNIQUE (court_id, starts_at)
    |
    | That is correct for exactly one day. The moment a hold expires or a
    | customer cancels, the dead row keeps its (court_id, starts_at) values and
    | poisons that slot forever -- nobody can ever book Court 2 at 7pm again.
    |
    | MySQL has no partial indexes, so the fix is to make the indexed value NULL
    | for rows that no longer occupy the court, and rely on NULLs being distinct
    | inside a unique index: unlimited dead rows may share a slot, but only one
    | live row can hold it.
    |
    |     active_slot_at = starts_at  when status is pending or confirmed
    |     active_slot_at = NULL       when status is cancelled or expired
    |
    | It is a STORED GENERATED column rather than a value the application
    | maintains, which is the entire point: there is no code path -- no service
    | method, no tinker session, no future teammate's raw query -- that can
    | insert a conflicting live booking. The constraint is a property of the
    | data, not a habit of the codebase.
    |
    | Bookings and maintenance blocks share this table so that one index covers
    | both: a customer cannot book a blocked court, and staff cannot block a
    | court-hour a customer has already paid for.
    |
    */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // Short, unambiguous code the customer reads out over the phone.
            $table->string('reference', 12)->unique();

            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default(BookingKind::Booking->value);
            $table->string('status', 20)->default(BookingStatus::Pending->value);

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // The guarantee. Derived from status + starts_at by the database.
            // Built from BookingStatus::live() so the enum and the DDL cannot drift.
            $table->dateTime('active_slot_at')->storedAs(sprintf(
                'case when `status` in (%s) then `starts_at` end',
                collect(BookingStatus::live())->map(fn (string $s) => "'{$s}'")->implode(', ')
            ));

            // Guests, not accounts: a court's customers will not register to
            // reserve an hour. Null for maintenance blocks.
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();

            $table->unsignedInteger('amount_centavos')->default(0);
            $table->boolean('is_peak')->default(false);

            // Payment hold: pending until proof lands, then staff verify it.
            $table->dateTime('hold_expires_at')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->dateTime('proof_uploaded_at')->nullable();

            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            // Null when the public made it; set when staff booked it at the counter.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['court_id', 'active_slot_at'], 'bookings_court_active_slot_unique');

            // Day-view queries for the public grid and today's admin schedule.
            $table->index(['starts_at']);
            $table->index(['court_id', 'starts_at']);
            // The expiry sweeper's query: pending holds whose timer has run out.
            $table->index(['status', 'hold_expires_at']);
            $table->index(['kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
