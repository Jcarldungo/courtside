<?php

use App\Exceptions\BookingException;
use App\Exceptions\SlotUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
        | Losing the race for a court-slot is not a server error and not a bug --
        | it is the expected outcome for one of two people who tapped at the same
        | instant. It gets a first-class response in both shapes this app speaks.
        |
        | JSON clients get 409 Conflict, which is precisely what the status code
        | means, plus the next open slot on that court.
        |
        | The browser flow cannot use 409: Inertia only understands 2xx, 3xx and
        | 422, and anything else surfaces as a generic error modal. So the same
        | payload is flashed and the customer is redirected back to the grid,
        | where the page offers them the alternative slot as a single tap.
        */
        $exceptions->render(function (SlotUnavailableException $e, Request $request) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json($e->toArray(), 409);
            }

            return back(303)
                ->withInput()
                ->with('conflict', $e->toArray())
                ->withErrors(['starts_at' => $e->publicMessage()]);
        });

        // A request that was wrong on its face: off-grid slot, closed court,
        // illegal state transition. Unprocessable, not conflicting.
        $exceptions->render(function (BookingException $e, Request $request) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back(303)
                ->withInput()
                ->withErrors(['starts_at' => $e->getMessage()]);
        });
    })->create();
