# Courtside

A booking system for sports venues that only have a Facebook page.

Built for pickleball courts in Pampanga, Philippines, where bookings currently
happen in Facebook comments and payment is a GCash screenshot. Single-tenant:
one deploy per venue, re-skinned from one tokens file.

**Stack:** Laravel 12 · Inertia 2 · React 19 + TypeScript · Tailwind 4 · MySQL · database queue

## The problem this is built around

Two people tap "Book Court 2, 7pm" in the same second. Check-then-insert in
application code passes code review and double-books in production. Courtside
pushes the guarantee down into the schema — see the full write-up below once
Phase 1 lands.

_This README grows into the case study as the build progresses._
