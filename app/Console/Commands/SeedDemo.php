<?php

namespace App\Console\Commands;

use App\Services\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Run once when standing up a demo deployment (and safe to run again any
 * time): builds the owner account this venue's admin view logs into and
 * seeds the realistic week. The public "Reset demo" button calls the same
 * DemoSeeder afterward, so this command and that button always agree.
 */
class SeedDemo extends Command
{
    protected $signature = 'courtside:demo';

    protected $description = 'Seed (or reseed) the one-tap demo: courts, an owner account, and a realistic week of bookings';

    public function handle(DemoSeeder $seeder): int
    {
        if (! config('venue.demo_mode')) {
            $this->warn('DEMO_MODE is false -- seeding anyway, but the public demo routes will 404 until it is true.');
        }

        $seeder->reseed();

        $this->info('Demo data seeded.');

        return self::SUCCESS;
    }
}
