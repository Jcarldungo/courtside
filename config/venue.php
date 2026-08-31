<?php

/*
|--------------------------------------------------------------------------
| The venue file
|--------------------------------------------------------------------------
|
| This is the re-skin surface. Everything that changes between one venue and
| the next lives here: identity, contact details, operating hours, pricing
| behaviour, photography and colour. Nothing below is referenced by name
| anywhere in a React component -- the values are shared to the front end as
| Inertia props and as CSS custom properties, so re-skinning is editing this
| file and dropping in new photos. No rebuild, no code changes.
|
| The 'unit' keys keep the system honest about not being pickleball-only: a
| badminton hall sets 'court'/'courts', a KTV bar sets 'room'/'rooms', and the
| interface copy follows.
|
*/

return [

    'name' => env('VENUE_NAME', 'Rally Point Pickleball'),
    'short_name' => env('VENUE_SHORT_NAME', 'Rally Point'),
    'tagline' => 'Four covered courts in Angeles City. Open from 6am.',

    // What one bookable thing is called, in this venue's language.
    'unit' => 'court',
    'units' => 'courts',

    'contact' => [
        'phone' => '0917 555 0142',
        'phone_link' => '+639175550142',
        'email' => 'hello@rallypointpickleball.ph',
        'facebook' => 'https://www.facebook.com/',
        // Reclub and Facebook run the open-play scene here. Rather than compete
        // with that, link out to it: this system sells court hours.
        'open_play_url' => 'https://pickleball.reclub.co/',
    ],

    'location' => [
        'line1' => '142 Teresa Avenue, Barangay Malabanias',
        'city' => 'Angeles City',
        'province' => 'Pampanga',
        'postcode' => '2009',
        'landmark' => 'Beside the Malabanias covered court, 5 minutes from Marquee Mall.',
        'map_url' => 'https://maps.google.com/?q=Angeles+City+Pampanga',
        'latitude' => 15.1450,
        'longitude' => 120.5887,
    ],

    'payment' => [
        'method' => 'GCash',
        'account_name' => 'RALLY POINT PICKLEBALL OPC',
        'account_number' => '0917 555 0142',
        'instructions' => 'Send the exact amount, screenshot the receipt, then upload it here to confirm your slot.',
    ],

    /*
    | Operating schedule. The slot grid is generated from these values, and the
    | booking API validates against them, so a venue that opens at 5am and
    | closes at midnight changes two strings.
    */
    'schedule' => [
        'opens_at' => '06:00',
        'closes_at' => '22:00',
        'slot_minutes' => 60,
        // Peak pricing window. Slots starting inside it bill at the peak rate.
        'peak_starts_at' => '18:00',
        'peak_ends_at' => '21:00',
        // How far ahead the public may book.
        'advance_days' => 14,
    ],

    // Minutes a reservation may sit `pending` before the queue releases the slot.
    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),

    /*
    | One-tap demo seeding and owner auto-login. A court owner will not create an
    | account to evaluate software, so the demo has to be a single tap -- and for
    | exactly that reason it must be off for a paying client.
    */
    'demo_mode' => (bool) env('DEMO_MODE', false),

    /*
    | Colour is delivered as CSS custom properties at request time, which is why
    | a re-skin needs no `npm run build`. Tailwind's theme maps its brand
    | utilities onto these variables.
    */
    'theme' => [
        'brand' => '#1f6f4a',        // court green
        'brand-strong' => '#16543a',
        'brand-tint' => '#e8f3ed',
        'accent' => '#f2b33d',       // ball yellow
        'accent-strong' => '#d9931f',
        'ink' => '#12211b',
        'surface' => '#ffffff',
        'surface-sunken' => '#f5f7f6',
    ],

    // Photo credits (Unsplash License — free to use, attribution appreciated
    // not required): hero by Youssef Sarhan, net-detail by Curtis Adams,
    // evening-play by Milo Miloezger, paddle-detail by Meg Alt.
    'photos' => [
        'hero' => 'images/venue/hero.jpg',
        'gallery' => [
            ['src' => 'images/venue/evening-play.jpg', 'alt' => 'A player about to serve, pickleball net in view, on an outdoor court.'],
            ['src' => 'images/venue/net-detail.jpg', 'alt' => 'Close-up of a regulation pickleball net stretched across a green court.'],
            ['src' => 'images/venue/paddle-detail.jpg', 'alt' => 'Two pickleball balls resting on a paddle, ready for a rental pickup.'],
        ],
    ],

    'amenities' => [
        ['label' => 'Covered courts', 'detail' => 'Play through the rain and the 2pm heat.'],
        ['label' => 'Floodlights', 'detail' => 'Every court lit until closing at 10pm.'],
        ['label' => 'Free parking', 'detail' => 'Twelve slots on site, tricycle-friendly gate.'],
        ['label' => 'Paddle rental', 'detail' => '₱50 per paddle. Balls provided free.'],
        ['label' => 'Water station', 'detail' => 'Refill free, or buy cold drinks at the counter.'],
        ['label' => 'Shower & CR', 'detail' => 'Two shower rooms, clean and well lit.'],
    ],

];
