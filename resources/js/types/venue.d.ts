/**
 * The shape App\Support\Venue::toArray() sends down on every page, plus the
 * rest of what HandleInertiaRequests shares globally. One source of truth so
 * a component never guesses at a venue field's name.
 */
export interface VenuePhoto {
    src: string;
    alt: string;
}

export interface Venue {
    name: string;
    short_name: string;
    tagline: string;
    unit: string;
    units: string;
    contact: {
        phone: string;
        phone_link: string;
        email: string;
        facebook: string;
        open_play_url: string;
    };
    location: {
        line1: string;
        city: string;
        province: string;
        postcode: string;
        landmark: string;
        map_url: string;
        latitude: number;
        longitude: number;
    };
    payment: {
        method: string;
        account_name: string;
        account_number: string;
        instructions: string;
    };
    amenities: Array<{ label: string; detail: string }>;
    photos: {
        hero: string;
        gallery: VenuePhoto[];
    };
    hours: {
        opens_at: string;
        closes_at: string;
        label: string;
        peak_label: string;
    };
    booking: {
        slot_minutes: number;
        hold_minutes: number;
        advance_days: number;
    };
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    role: 'owner' | 'staff';
    role_label: string;
    is_owner: boolean;
}

export interface SlotConflict {
    message: string;
    court: { id: number; name: string };
    requested_at: string;
    next_available_at: string | null;
    next_available_label: string | null;
}

export interface SharedProps {
    venue: Venue;
    auth: { user: AuthUser | null };
    flash: {
        status: string | null;
        message: string | null;
        conflict: SlotConflict | null;
    };
    demo: { enabled: boolean };
}
