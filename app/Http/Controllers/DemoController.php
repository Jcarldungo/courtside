<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DemoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The one-tap demo. A court owner evaluating this system will never create an
 * account to do it -- so both routes here exist to make sure they never have
 * to: one signs them straight into the admin view, the other hands them a
 * fresh, current week of realistic data to look at while they're there.
 *
 * Both 404 outside demo mode. That is what makes it safe for
 * DemoSeeder::reseed() to wipe every booking and for enter() to sign a
 * stranger in as the owner with no password -- neither exists at all once
 * venue.demo_mode is false, which it must be for a real client (see
 * config/venue.php).
 */
class DemoController extends Controller
{
    public function enter(): RedirectResponse
    {
        abort_unless(config('venue.demo_mode'), 404);

        $owner = User::where('role', 'owner')->first();
        abort_if(! $owner, 404, 'No demo owner account exists. Run php artisan courtside:demo first.');

        Auth::login($owner);

        return redirect()->route('dashboard')->with('status', 'demo-entered');
    }

    public function reset(DemoSeeder $seeder): RedirectResponse
    {
        abort_unless(config('venue.demo_mode'), 404);

        $seeder->reseed();

        return back()->with('status', 'demo-reset');
    }
}
