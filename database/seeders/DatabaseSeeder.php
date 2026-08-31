<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Baseline data every environment needs: the venue's real courts and one
     * owner account. Sample bookings for demos come from DemoSeeder instead,
     * run on demand rather than on every deploy.
     */
    public function run(): void
    {
        $this->call(CourtSeeder::class);

        if (User::where('email', 'owner@courtside.test')->exists()) {
            return;
        }

        // A random password, not a fixed default: a fixed one would be the
        // same for every deploy of this codebase, including a client's.
        $password = env('OWNER_PASSWORD') ?? Str::password(12);

        User::create([
            'name' => 'Venue Owner',
            'email' => 'owner@courtside.test',
            'password' => Hash::make($password),
            'role' => UserRole::Owner,
            'email_verified_at' => now(),
        ]);

        $this->command?->warn("Owner account created — owner@courtside.test / {$password}");
    }
}
