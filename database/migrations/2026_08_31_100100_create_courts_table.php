<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // "Court 1", or "Room 3" at a KTV bar
            $table->string('surface')->nullable();         // "Outdoor acrylic", "Wooden"
            $table->text('description')->nullable();

            // Money is stored in centavos. Rates are captured onto each booking
            // at the moment it is made, so raising the peak rate never rewrites
            // what an existing customer already agreed to pay.
            $table->unsignedInteger('rate_peak_centavos')->default(0);
            $table->unsignedInteger('rate_offpeak_centavos')->default(0);

            $table->boolean('is_active')->default(true);   // false hides it from the public grid entirely
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
