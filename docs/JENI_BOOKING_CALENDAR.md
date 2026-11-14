# Jeni: Booking & Calendar Management — Issue #9

Branch target: `feat/booking-calendar` (separate from `feat/atomic-checkout`).

## Existing functionality reused

- Customer booking, snapshot prices, payment, cancellation (from PawCare core).
- Owner calendar day/week/month filters, business operating hours, closures, and slot capacity (from PawCare/Elga).

## New in this branch

- Owner preorder settings at `/owner/jeni/preorder` (role `business_owner`, operates only on own primary business).
- Customer slot availability at `/customer/jeni/kalender` (role `customer`, active businesses only).
- Preorder > 30 days and <= owner configurable maximum (31–365 days; 180 default).
- `pre_order_slot_capacity` effective cap is the smaller of regular capacity and configured preorder cap.
- `is_preorder` snapshot column on bookings and badge on owner calendar.
- Booking model `creating` hook enforces preorder policy for both legacy `BookingService::create` and Atomic Checkout's `Booking::create`. It acquires a business row lock and recounts overlaps inside the surrounding transaction.
- Sidebar links for owner and customer.

A preorder is a *real future booking*, not an unpaid hold or waitlist. Payment and cancellation still use the normal PawCare workflow. Disabling preorder later does not delete existing reservations. Existing regular bookings for dates 0–30 days ahead continue working.

## Requirements

1. Start from current `main` on a new local `feat/booking-calendar` branch.
2. Apply `booking-calendar.patch` using `git apply` (no script execution and no wholesale file overwrite required).
3. Run `php artisan migrate`, `php artisan route:list --path=jeni`, and `php artisan test --filter=JeniBookingCalendarTest` when PHP is usable.
4. Use MySQL or MariaDB for meaningful concurrent checkout/lock testing; SQLite does not emulate production row locks.
5. Compare this branch against `main`, open a separate PR referencing Issue #9. When PR #15 is merged, rebase/merge newest `main` and rerun checks before integrating.

## Caveats

- The built-in original booking form still shows its generic slot summary; the new customer calendar provides preorder-aware capacity and eligibility before proceeding to the original booking form.
- The existing slot-availability service truncates reservations at closing time instead of rejecting; this patch corrects that behavior and prevents intervals overflowing closing time.
- The marker `is_preorder` is stored when a booking is created, not recalculated when the date approaches.
- This patch has been statically checked and PHP-linted, but runtime Laravel tests, migrations, Redis, and real concurrency tests are **not certified** here.
