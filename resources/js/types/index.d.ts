import { SharedProps } from './venue';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

/**
 * `& Record<string, unknown>` is load-bearing, not decorative: Inertia's own
 * `PageProps` (from @inertiajs/core) carries an index signature, and TS
 * requires this type to structurally satisfy that when it is passed as the
 * generic argument to `usePage<PageProps<...>>()`. Known keys still keep
 * their real types; only genuinely unknown keys fall back to `unknown`.
 */
export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & SharedProps & Record<string, unknown>;
