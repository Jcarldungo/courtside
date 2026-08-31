<?php

namespace Database\Seeders;

use App\Models\Court;
use Illuminate\Database\Seeder;

/**
 * The venue's actual courts. Real names and real rates, because this is what
 * ships to every environment -- local, staging, and the venue's own production
 * data on day one -- not sample data to be swept away later.
 */
class CourtSeeder extends Seeder
{
    public function run(): void
    {
        if (Court::exists()) {
            return;
        }

        collect([
            ['name' => 'Court 1', 'surface' => 'Outdoor acrylic'],
            ['name' => 'Court 2', 'surface' => 'Outdoor acrylic'],
            ['name' => 'Court 3', 'surface' => 'Cushioned acrylic'],
            ['name' => 'Court 4', 'surface' => 'Cushioned acrylic'],
        ])->each(fn (array $court, int $i) => Court::create([
            ...$court,
            'rate_peak_centavos' => 40000,
            'rate_offpeak_centavos' => 30000,
            'sort_order' => $i,
        ]));
    }
}
