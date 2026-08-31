<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Venue;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),

            // Every page knows the venue, so no component hardcodes a name,
            // a phone number or an opening time.
            'venue' => fn () => Venue::toArray(),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'is_owner' => $user->isOwner(),
                ] : null,
            ],

            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'message' => fn () => $request->session()->get('message'),
                // A lost race arrives here so the page can offer the next open
                // slot instead of just an error string.
                'conflict' => fn () => $request->session()->get('conflict'),
            ],

            'demo' => [
                'enabled' => (bool) config('venue.demo_mode'),
            ],
        ];
    }
}
