<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The entire staff-management "UI".
 *
 * Nobody self-registers into a booking system's admin: the only account a
 * customer needs is the reference code in their booking link. So the venue
 * owner runs this once per hire, over SSH or a hosting panel's terminal, and
 * hands the printed password to whoever they just added.
 */
class CreateStaffAccount extends Command
{
    protected $signature = 'courtside:staff {name} {email} {--owner : Grant full owner access instead of staff}';

    protected $description = 'Create a staff or owner login for the admin area';

    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        try {
            validator(['email' => $email], ['email' => ['required', 'email']])->validate();
        } catch (ValidationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("A user with {$email} already exists.");

            return self::FAILURE;
        }

        $password = Str::password(12);

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $this->option('owner') ? UserRole::Owner : UserRole::Staff,
            'email_verified_at' => now(),
        ]);

        $this->newLine();
        $this->info('Account created:');
        $this->line("  Email:    {$email}");
        $this->line("  Password: {$password}");
        $this->newLine();
        $this->warn('This password is shown once. Send it to them now — it is not stored anywhere retrievable.');

        return self::SUCCESS;
    }
}
