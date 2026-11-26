# Chicago Yacht Booking — Platform Plan

**Status:** v0.4 (2026-11-23), with the user's decisions applied. R2–R17 are confirmed; R1 has a recommendation (Stellar Business). Phases 0–2 and 3.1–3.3 are built (§14).
**Design source:** `C:\Users\DETRIOTS\Desktop\Yacht-Web`, a static HTML/Tailwind/JS mockup with 13 pages.
**Target:** this Laravel 13 app (`D:\Yacht_laravel\Yacht-Booking`).

**What changed in v0.4 (2026-11-23):**
- **Captains get their own account and dashboard** (D19, replaces "no captain login"): they apply or are invited, the admin approves them, and they see trip offers, their trips, availability and pay.
- **Trip offers:** the admin offers a paid trip to all eligible captains or to selected ones; **the first captain to accept gets it** (§7.8).
- **Trip extensions:** during the trip, the customer can add time and pay for it, if the boat and the captain are free (D20, §7.9).
- Phase 8 is rewritten for both (§14).

**What changed in v0.2:**
- The admin dashboard is custom (no Filament).
- Interactive pages use plain JS + JSON (no Livewire/Alpine).
- Roles are a simple built-in system (no Spatie) with one admin.
- Laravel Boost as a dev-only tool, added before Phase 2 (D17).
- Hosting is on Namecheap.
- Owners can withdraw 72 hours after a trip, and the admin can make deductions when there is a complaint.
- Cancellation policies follow Boatsetter: hours-based and editable.
- Captains get an email when they are assigned.
- Optional features moved to "Later".
- New §8.2: the security standard for forms and JSON endpoints.

---

## 1. Goals

A secure, role-based yacht charter marketplace for Chicago:

- **Customers** book a boat, an experience, a date, a duration and a time slot, then pay online and manage their trips.
- **Boat owners** list boats with the List Your Boat wizard, manage them, see bookings and withdraw their earnings.
- **Captains** accept trip offers, see their trips and pay, and are told about every change in their own dashboard (D19).
- **Customers on a running trip** can add time and pay for it, if the boat and the captain are free (D20).
- **The admin** runs everything: fleet, experiences, prices, captains, bookings, payments, refunds, owner payouts and deductions, the contact inbox, site content and users.
- The look stays the same as the mockup: navy/brass, Playfair Display + Manrope.
- **Fast to v1:** only core features first, with optional features in §17 "Later".
- Built step by step. Every step passes its QA gate (§15) before the next one starts.
- **Code that is easy to read:** Laravel defaults (controllers, FormRequests, Policies, Blade, Mail, Queues) and as few packages as possible.

---

## 2. Decisions

### 2.1 Confirmed

| # | Decision |
|---|---|
| D1 | Laravel Blade + Tailwind. The mockup design is the source of truth. The design is ported first (Phase 1). |
| D2 | Role-based access: **customer**, **owner**, **captain** (added 2026-11-23, D19) and **admin**. One admin for now; staff/manager roles later. |
| D3 | **Custom admin dashboard**, built from scratch with Laravel defaults (no Filament). |
| D4 | Interactive flows use **vanilla JS (ES modules via Vite) + JSON endpoints**. No Livewire/Alpine. The endpoints must be hardened (§8.2). |
| D5 | Auth: **Laravel Fortify** with our own Blade screens. |
| D6 | **Checkout needs an account**, created inline on the checkout page. |
| D7 | **Owners set their own prices** in the wizard (a new Pricing step); the admin can adjust them before approval. |
| D8 | **Platform commission** on owner boats is configurable: a global % plus a per-owner override. Placeholder 20%; the client gives the final figure. |
| D9 | **Owner payouts:** earnings become withdrawable **72 hours after the trip ends**. The owner requests a withdrawal → the admin approves → Stripe Connect transfer. If a customer complains about the boat, the admin can **deduct** from the owner's earnings. The admin can also pause an owner's payouts or hold one payment. |
| D10 | **Captains:** the customer sees a fixed "Captain & crew" fee at checkout. After payment the captain is found through **trip offers** (first to accept) or **assigned directly** by the admin (§7.8). The captain is told in their dashboard, by the bell and by email. *(Updated 2026-11-23: "no captain login" replaced by D19.)* |
| D11 | The admin adds, edits and offers experiences. Boats the admin adds can go live directly; owner boats need approval. |
| D12 | Boats that can't be booked show an **Unavailable** tag. |
| D13 | **Refunds follow Boatsetter-style policies** (Flexible / Moderate / Strict), based on hours before the trip. The rules can be edited, and the admin can always issue a custom refund (§7.4). |
| D14 | The contact form stores each message and sends emails from the **business email on Namecheap**. |
| D15 | Files are stored on disk in the project (`storage/`). **The DB stores only the path.** |
| D16 | **Hosting: Namecheap.** The plan type is still open (R1). |
| D17 | **Laravel Boost (dev-only) is added just before Phase 2** (decided 2026-11-21) for version-correct docs search, DB schema/query, logs and browser-logs tools. Its guidelines live only inside the `<laravel-boost-guidelines>` block; our `CLAUDE.md` rules outside that block stay the source of truth. Phase 0–1 run without it. |
| D18 | Optional features (reviews, waiver, deposit hold, messaging, SMS, gift cards, staff roles) come **later**. *(The captain portal moved into v1 on 2026-11-23, D19.)* |
| D19 | **Captain portal** (user decision 2026-11-23). Captains have a login with the `captain` role and a dashboard at `/captain`: trip offers (accept / decline), their trips, acknowledging an assignment, their availability, profile + license documents, and pay. A captain account is created by **applying** (admin approval needed) or by an **admin invite**. Built in Phase 8 (§7.8). |
| D20 | **Trip extensions** (user decision 2026-11-23). During the trip, until a cut-off before the end, the customer can **add time and pay for it** as a separate payment. It is allowed only if the boat **and** the captain are free for the extra time. The captain, the owner (owner boats) and the admin are told when it is paid. Built in Phase 8 (§7.9). |
| D21 | **Boatsetter-style renter flow, our design** (user decision 2026-11-25). For Chicago only. Booking starts on the boat page card (date, duration, start time, optional experience, captain choice, guests), then one checkout page. The 4-step `/book` wizard is retired. Experiences become optional and work like categories. Every fee line is shown. Fleet filters, no map. Our design stays, only adjusted for the flow. Research: [docs/research/boatsetter-renter-flow.md](research/boatsetter-renter-flow.md). **Experiences are free** (user, 2026-11-25, as on Boatsetter): each keeps its own start times (sunset ≠ fireworks); the admin can add **paid extras** to an experience (décor, a photographer, any facility; add-ons priced in Admin → Add-ons). Built in 4.3. **Changed the same day after the user's QA:** an experience has **no trip length of its own** (the guest books the boat by the hour and gets the experience for that time); it **departs inside a window** (sunset in the evening, fireworks at night) and the guest picks any of the boat's start times inside it, told plainly when a time or day doesn't fit; its extras are **optional add-ons suggested at checkout**, charged only when ticked. |
| D22 | **Direct checkout, full payment only** (user decisions 2026-11-25). No deposit, no balance charge and **no request to book** (owner boats too): every booking is a hold paid in full at checkout, then confirmed. Trip Assurance and security-deposit holds come later (D18). **2026-11-26 (user): the payment is received at checkout from 4.5** — Stripe's core (planned for 5.1 / 5.2) came forward; there is no stubbed step. |

### 2.2 Small confirmations

| # | Question | Answer |
|---|---|---|
| R1 | Which Namecheap plan, and which PHP version? | **Recommended: Stellar Business** (shared, PHP 8.0–8.5 available). Pin Composer to **PHP 8.4** (§16). The user is deciding. |
| R2 | How are the Moderate/Strict windows counted? | ✅ In whole days, converted to hours. Moderate: ≥120h 100%, 72–120h 50%, <72h 0%. Strict: ≥720h 100%, 360–720h 50%, <360h 0% (§7.4). |
| R3 | Minimum withdrawal amount? | ✅ $50 (stored as a setting). |
| R4 | How long after the trip can a customer complain? | ✅ 72 hours, the same as the payout delay. |
| R5 | What happens when an owner cancels a confirmed trip? | ✅ The customer gets a full refund, and **the admin can deduct a penalty** from the owner. Nothing is deducted automatically. |
| R6 | Does the captain see customer details? | ✅ **Only the booking reference and the customer's name** (updated 2026-11-23). The captain recognizes the group at the dock by the reference (e.g. `CYB-7K3P9Q`) and the customer's **full name** (first + last name, the user's choice), shown in the captain dashboard. Never the phone, email, address or customer notes. **Emails to the captain carry the reference only**, no name (§7.8). |
| R7 | Commission %? | ✅ 20% for now (stored as a setting). |
| R8 | Emails during development? | ✅ `MAIL_MAILER=log` locally (emails go to `storage/logs/laravel.log`). Real SMTP (Namecheap business mailbox) in Phase 9/11. (2026-11-22) |
| R9 | Must customers verify their email? | ✅ Yes, **before paying** (checkout requires `verified`, Phase 4). Owners verify before listing a boat (Phase 2.3). (2026-11-22) |
| R10 | Is 2FA mandatory for the admin? | ✅ **No — the admin's choice** (user decision 2026-11-22, replaces "mandatory"): on / off in Admin → Settings → Security. Recommended on the live site. Owner withdrawals still need 2FA (Phase 7). |
| R11 | How does a captain get an account? | ✅ **Both ways** (2026-11-23): the captain applies (`/captains/apply`, with license details and documents) and waits for the admin's approval, **or** the admin invites a captain by email. |
| R12 | Trip offer timing? | ✅ An offer stays open **24 hours** by default (the admin can change it per request). A captain can **release** an accepted trip up to **48 hours** before the start, with a reason; after that only the admin can remove them. Both are settings. |
| R13 | Does a trip offer show the captain's pay? | ✅ **Yes:** the pay for that trip (pay rate × hours) is shown on the offer. |
| R14 | Extension step? | ✅ **Whole hours only** (user decision 2026-11-23): the customer picks +1 h, +2 h, +3 h, … up to the free time. No half hours. |
| R15 | When can a trip be extended? | ✅ **Only during the trip**, until **30 minutes before it ends** (a setting). Adding hours before the trip is done by the admin for now. |
| R16 | Is an extension refundable? | ✅ **No**, once paid. Only the admin can refund it (e.g. the trip ended early because of the weather). |
| R17 | Extensions on owner boats? | ✅ **Allowed.** Every boat has an **"Allow trip extensions"** switch, **on by default**, controlled by the admin. |

---

## 3. Tech stack (minimal packages)

| Area | Choice | Notes |
|---|---|---|
| Framework | Laravel 13, PHP 8.4 (production) | Local is PHP 8.5. `composer.json` `config.platform.php` is pinned to **8.4.1** (Symfony 8.1 needs ≥ 8.4.1), so every package also installs on Namecheap. |
| Database | MySQL/MariaDB locally (`yacht_db`, XAMPP); **MariaDB 11.4** on Namecheap; utf8mb4 | Use the `mariadb` driver in production. Write standard SQL only. |
| Frontend | Blade + Tailwind v4 (Vite) + **vanilla JS ES modules** | Ported from the mockup's JS without global `window.CYB` state. Assets are built locally (`npm run build`) and `public/build` is uploaded. |
| Icons / fonts | `lucide` (npm, only the icons we use), self-hosted Playfair Display + Manrope | No CDNs, so the CSP can stay strict. |
| Auth | **laravel/fortify** | Login, register, email verification, password reset, 2FA, password confirmation, throttling. |
| Roles | **Built-in:** `users.role` + Gates + Policies + a small `role` middleware | No package. Staff roles can be added later without touching controllers (§4.3). |
| Admin | **Custom** Blade dashboard: controllers, FormRequests, Policies and our own admin UI components | Designed in Phase 1.7 and wired up phase by phase. |
| Payments | **stripe/stripe-php**: Payment Element (cards, Apple/Google Pay) + **Stripe Connect Express** (owner payouts) | Card data never reaches our server. Stripe handles owner bank accounts, KYC and 1099s. |
| Images | intervention/image-laravel (GD or Imagick) | Re-encodes uploads to WebP and strips EXIF/GPS. Needs GD or Imagick on the host. |
| PDF | barryvdh/laravel-dompdf | Receipts and invoices. |
| Audit log | Our own `audit_logs` table + an `Audit` service | Simple to read. Used for every admin and money action. |
| Bot protection | Cloudflare Turnstile, called with the Laravel `Http` client (no package) + honeypot | On register, contact, newsletter and checkout. |
| Mail | Laravel Mail over **SMTP** from the Namecheap business mailbox | Local: `MAIL_MAILER=log` (already set). |
| Queue / scheduler | Database queue (already set). On shared hosting: a cron job runs `schedule:run` every minute, and the scheduler starts `queue:work --stop-when-empty` | No Supervisor is needed on shared hosting. |
| Tests | PHPUnit 12 (already installed) + headless-Chrome page checks | QA gate on every step. |
| AI tooling | Claude Code + our own `CLAUDE.md`; **Laravel Boost** added before Phase 2 (dev-only, `require-dev`) | Boost never ships to production (`composer install --no-dev`). |

---

## 4. Accounts, roles and access ✅ approved

### 4.1 One account for everyone

- **One `users` table and one login page** for everyone.
- Signing up makes you a **customer**. Clicking **List Your Boat** makes you an **owner**: same email, same password. An owner can still book trips.
- When an owner logs in, the header account menu also shows **"Owner dashboard"**.
- **Captains** (D19, R11) get the `captain` role in one of two ways:
  - They **apply** at `/captains/apply`. This creates the account and a captain profile with `application_status = pending`. Until the admin approves, the captain area shows only "Your application is being reviewed" and no trips.
  - Or the admin creates the captain profile and sends an **invite** (a signed link, valid 7 days). The captain sets a password and is approved straight away.
  - The header account menu shows **"Captain dashboard"**. A captain can still book trips as a customer.
  - An owner account can't also be a captain in v1 (one role per account). An owner who wants to drive platform trips uses a separate email.
- **The admin never signs up publicly.** The account is created with `php artisan app:create-admin`. 2FA is the admin's choice (R10: Settings → Security, recommended), and after login the admin goes to `/admin`.
- **The admin dashboard shows everyone:**
  - A Users screen with tabs for Customers, Owners and Captains.
  - Each user's page shows their bookings, payments, boats, earnings, withdrawals, complaints, login history and admin notes.
  - Actions on a user: suspend, ban, reset 2FA.

### 4.2 Flows

**Customer**

1. Browse without logging in and build the booking (experience → boat → date and time → extras).
2. At checkout, log in or create an account inline.
3. Pay.
4. In "My trips": see the details, receipt, add-to-calendar, cancel, or file a complaint (within 72 hours of the trip).

**Owner**

1. List Your Boat → log in or register → verify email.
2. Fill in the wizard. It auto-saves to the DB at every step and includes the Pricing step.
3. Submit → the admin approves, asks for changes, or rejects.
4. Before the first withdrawal: connect payouts (Stripe Connect) and turn on 2FA.
5. See bookings, earnings and withdrawals in the owner dashboard.

**Captain** (D19, §7.8)

1. Apply (or accept the admin's invite) → verify email → the admin approves.
2. Mark days off in "Availability".
3. Get trip offers (dashboard, bell, email) → accept or decline. The first captain to accept gets the trip.
4. Or be assigned directly by the admin → press **Acknowledge** so the admin knows it was seen.
5. See upcoming trips, trip changes and extensions, and pay in the captain dashboard.

**Redirect after login**

- Admin → `/admin`.
- Captain → `/captain`.
- Owner with listings → `/owner`.
- Everyone else → the page they were on, or `/account/trips`.

### 4.3 Roles

| Role | Who | Notes |
|---|---|---|
| `customer` | Anyone who registers | Default. |
| `owner` | Users who list a boat | Set by the system when the first listing starts. Includes all customer abilities. |
| `captain` | Platform captains (D19) | Set by the system when a captain applies or accepts an invite. Sees trips only once the admin has approved them. Includes all customer abilities. |
| `admin` | The client / you (one account) | Full rights. Only created with the artisan command. 2FA optional (R10), recommended. |
| *(later)* `manager`, `support` | Staff | Added later. |

How it works in code:

- The `users.role` column is **never mass-assignable**. Only system actions change it: registration sets `customer`, the first listing sets `owner`, a captain application or invite sets `captain`, and the artisan command creates `admin`.
- Every permission is a **named Gate**, for example `Gate::define('approve-payouts', fn ($u) => $u->isAdmin())`. Controllers and views only check these names (`can('approve-payouts')`).
- Staff roles can be added later by changing the Gate definitions. Controllers don't change.
- **Ownership** is checked by **Policies** (`BoatPolicy`, `BookingPolicy`, `PayoutPolicy`, `ComplaintPolicy`, `CaptainTripOfferPolicy`).

### 4.4 Access matrix

| Area | Guest | Customer | Owner | Captain | Admin |
|---|---|---|---|---|---|
| Public site, build a booking | ✅ | ✅ | ✅ | ✅ | ✅ |
| Checkout / pay, extend a running trip | login | ✅ | ✅ | ✅ | – |
| My trips, complaints (own only) | – | ✅ | ✅ | ✅ | – |
| List Your Boat, My boats (own only) | login | ✅ (becomes owner) | ✅ | – | – |
| Earnings / withdrawals (own only) | – | – | ✅ + 2FA | – | – |
| Apply as a captain | ✅ (creates the account) | ✅ | – | – | – |
| `/captain` dashboard: offers sent to them, their assigned trips, availability, pay (own only) | – | – | – | ✅ (approved only) | – |
| `/admin` dashboard | – | – | – | – | ✅ (2FA optional, R10) |
| Refunds, payouts, deductions, settings | – | – | – | – | ✅ + password re-confirmation |

A captain sees a booking only while they hold an open offer for it or are assigned to it. A trip they declined, lost or were removed from returns 404.

If someone opens another user's record, the app returns **404, not 403**, so attackers can't even learn that the record exists.

### 4.5 Route areas and middleware

| Area | Prefix | Middleware |
|---|---|---|
| Public pages + public JSON (availability, quote) | `/`, `/book/*` | `web`, security headers, throttle on JSON endpoints |
| Auth (Fortify) | `/login`, `/register`, … | `guest`, throttle |
| Customer | `/account/*` | `auth`, `active` |
| Checkout / hold | `/checkout/*` | `auth`, `active`, throttle |
| Owner | `/list-your-boat`, `/owner/*` | `auth`, `active`, `verified` (+ `role:owner` after the first listing) |
| Owner money | `/owner/payouts/*` | + `2fa`, `password.confirm` |
| Captain application | `/captains/apply` | `web`, throttle, Turnstile + honeypot |
| Captain | `/captain/*` | `auth`, `active`, `verified`, `role:captain`; trip pages also need an approved captain profile; offer answers throttled |
| Admin | `/admin/*` | `auth`, `active`, `verified`, `can:access-admin`, `admin.idle` (30 min); money actions also need `password.confirm`. No `2fa` (R10). |
| Stripe webhooks | `/webhooks/stripe` | **No** session or CSRF; Stripe signature check; throttle |

- **All JSON endpoints live in `routes/web.php`** (session cookie + CSRF), not in stateless `api.php` (§8.2).
- Custom middleware:
  - `EnsureAccountIsActive`: logs out suspended or banned users immediately.
  - `EnsureUserHasRole`.
  - `RequireTwoFactor`.
  - `AdminIdleTimeout`.
  - `SecurityHeaders`.

---

## 5. Modules

1. **Identity and access:** users, roles, 2FA, sessions, login log, audit log.
2. **Fleet:** harbors, boats (the client's own boats + owner boats), photos, amenities, availability rules, blocks/blackouts, insurance.
3. **Catalog:** experiences, add-ons, cancellation policies, which boats offer which experiences.
4. **Captains:** accounts (apply / invite + approval), profiles, licenses, documents, availability, **trip offers (first to accept)**, direct assignment + acknowledgement, the captain dashboard, notifications, captain pay.
5. **Booking engine:** quote, availability, holds, bookings, line items, status history, promo codes, **trip extensions**.
6. **Payments:** Stripe customers, saved cards, payments, refunds, disputes, webhooks.
7. **Owner finance:** ledger, commission, 72-hour release, complaints + deductions, withdrawals, payout pause/hold, Stripe Connect.
8. **Listings:** the List Your Boat wizard (with the Pricing step), the admin review queue, re-approval of edits.
9. **Communication:** contact form + admin inbox + replies, transactional email, in-app notifications, newsletter (simple).
10. **Content:** settings, hero, FAQs, testimonials, gallery, legal pages, SEO.
11. **Reporting and audit:** dashboard stats, CSV exports, audit log viewer.

---

## 6. Database design

**Conventions**

- `bigint` primary keys. User-facing records also get a **`ulid`**, which is what appears in URLs, so IDs can't be guessed.
- **Money:**
  - Integer **cents** (`*_cents`, USD). Never floats.
  - Rates in basis points (`*_bp`; 2000 = 20%).
- **Times** are stored in UTC and shown in **America/Chicago**.
- **Integrity:**
  - Foreign-key constraints.
  - Indexes on status and date columns.
  - Soft deletes on users, boats, experiences and captains.
- **JSON columns** only for data we never query (questionnaires, "includes" lists).
- **Bookings store snapshots** of line items, prices and the cancellation policy, so old bookings never change when prices change.
- **Files:** only the path relative to the disk is stored, in `media` (§9).

### 6.1 Identity and access

- **users:**
  - id, ulid, first_name, last_name, email (unique), email_verified_at, phone, password, avatar_path.
  - **role** (customer|owner|captain|admin, *not fillable*), status (active|suspended|banned), status_reason.
  - two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at.
  - stripe_customer_id, marketing_opt_in, last_login_at, last_login_ip, password_changed_at (2.5), timestamps, deleted_at.
- **sessions, password_reset_tokens, cache, jobs, failed_jobs:** already exist.
- **login_events:** user_id?, email, event (login|failed|logout|lockout|2fa_failed|password_changed|2fa_enabled|2fa_disabled|recovery_code_used|other_devices_logged_out|email_changed|account_deleted), ip, user_agent, device_id (SHA-256 of the device cookie), created_at. Kept 1 year (pruned daily). *(Built in 2.4 — `2fa_enabled` … `other_devices_logged_out` and `device_id` added then; `email_changed` and `account_deleted` in 2.5.)*
- **audit_logs:** id, user_id?, action (e.g. `payout.approved`, `boat.price_changed`), subject_type (short morph name, e.g. `user`), subject_id, before (json), after (json), reason, ip, user_agent, created_at. Never changed or deleted; kept for good. *(Built in 2.4.)*
- **owner_profiles:**
  - user_id (unique), business_type (individual|company), company_name, address fields, years_operating.
  - stripe_account_id, connect_status (none|pending|active|restricted), payouts_enabled.
  - **payouts_paused**, payouts_paused_reason, payouts_paused_at.
  - commission_bp (optional per-owner override), verified_at, admin_notes (encrypted).

### 6.2 Fleet

- **harbors:** id, slug, name, area, address, lat, lng, map_query, notes, image_path, is_active, sort.
- **boats** (one table for house and owner boats):
  - **Identity and status:** id, ulid, slug, **ownership** (house|owner), owner_id?, **status** (draft|pending_review|changes_requested|approved|published|paused|suspended|rejected|archived).
  - **Unavailable tag:** **booking_enabled** (false = Unavailable tag), unavailable_reason.
  - **Description:** title, short, description, **highlights** (json, the boat's own selling points shown as its Amenities list — 3.7).
  - **Specs:** type (power|sail), category, make, model, year, length_ft, registration_no, uscg_inspected, max_guests, staterooms, sleeps, beds, heads, engine_count, engine_type, horsepower, engine_year.
  - **Location:** harbor_id?, location_type, street, city, state, zip, country, lat, lng.
  - **Rules and booking settings:** cancellation_policy_id, rules (json), advance_notice_minutes, allow_multiple_bookings, buffer_minutes, **extensions_enabled** (default true, admin-only; R17, added in 3.4).
  - **Operation:** renter_operated_allowed, captain_source (owner|platform), fishing_charter, fuel_policy.
  - **Pricing:** hourly_rate_cents, weekend_multiplier_bp?, min_hours, durations (json), duration_prices (json, optional fixed price per duration), cleaning_fee_cents, captain_rate_cents (customer-facing, per hour), crew_count.
  - **Display:** badge, is_featured, sort, rating_avg, reviews_count. These are entered by the admin until reviews are added.
  - **Listing workflow:** wizard_step, submitted_at, reviewed_at, review_notes, pending_changes (json), published_at, timestamps, deleted_at.
- **amenities:** id, ulid, name, group (feature|cabin|galley|bathroom|deck|extra|navigation; `highlight` only for a rollback — 3.7), icon. Pivot **amenity_boat**. Specs also have **engine_model** (3.7).
- **boat_availability_rules:** boat_id, weekday (0–6), is_open, **start_times** (json "HH:MM" list, Chicago time; replaced opens_at / closes_at in 4.2, Boatsetter-style). No row for a weekday = closed. Boats also have **weekend_fixed_prices** (bool, 4.2).
- **boat_blocks:** boat_id? (null = all boats), starts_at, ends_at, type (owner_block|maintenance|blackout|weather), reason, created_by.
- **boat_insurances:**
  - boat_id, insurance_type (recreational|commercial), owner_answers (json), boat_answers (json), boat_value_cents.
  - carrier, policy_number (encrypted), expires_on, liability_limit, coi_media_id (private file).
  - status (pending|verified|expired).

### 6.3 Catalog

- **experiences:**
  - id, slug, name, category, icon, tagline, description, includes (json).
  - time_slots (json: its **departure window**, every half hour of it on the start-time grid), allowed_weekdays (json?), season_starts_on?, season_ends_on?.
  - is_active, is_featured, sort, seo_title, seo_description. *(No package fee since 4.3: the experience is free. No `durations` since the 4.4 fixes: the boat's lengths apply.)*
- **addon_experience** (4.3): experience_id, addon_id, sort — add-ons **suggested** with the experience at checkout, optional (charged only when the guest ticks them, 4.4 fixes).
- **boat_experience:** boat_id, experience_id, is_active. *(The per-boat package-fee override went in 4.3.)*
- **addons:** id, slug, name, description, icon, price_cents, unit (flat|per_guest), max_quantity, is_active, sort.
- **cancellation_policies:** id, slug (flexible|moderate|strict), name, badge, points (json, display text), **rules** (json, editable by the admin, e.g. `[{"min_hours_before":120,"refund_pct":100},{"min_hours_before":72,"refund_pct":50},{"min_hours_before":0,"refund_pct":0}]`), is_active.

### 6.4 Captains

- **captains:**
  - id, ulid, name, slug, photo_path, bio, **email (required, captain-provided; used for assignment emails)**, phone.
  - license_type (oupv|master|other), license_number (encrypted), license_expires_on, max_passengers_licensed.
  - years_experience, languages (json), pay_rate_cents (internal, per hour).
  - is_active, is_public, sort, deleted_at.
  - *Added in Phase 8.1 (D19):* **user_id** (nullable, unique: the captain's login), **application_status** (pending|approved|rejected), approved_at, rejection_reason, invited_at. `is_active` stays the admin's on/off switch for an approved captain.
- **captain_unavailability:** captain_id, starts_at, ends_at, reason. Entered by the captain (their days off) or by the admin.
- **captain_trip_offers** (Phase 8.2, §7.8):
  - id, ulid, booking_id, captain_id, **status** (offered|accepted|declined|filled|withdrawn|expired).
  - pay_cents (the pay shown on the offer, R13), expires_at, offered_by, responded_at, decline_reason?, timestamps.
  - One open offer per captain per booking. Index on (captain_id, status) and (booking_id, status).
- License documents are stored in `media`, on the private disk.

### 6.5 Booking engine

- **bookings:**
  - **Identity:** id, ulid, reference (unique, e.g. `CYB-7K3P9Q`), customer_id, boat_id, experience_id?.
  - **Captain:** captain_id?, captain_assigned_at, **captain_assigned_via** (offer|direct), **captain_notified_at**, **captain_acknowledged_at**, captain_notes (written by the admin, shown to the captain; never customer personal data).
  - **Status:** `status` (§7.3), `payment_status` (unpaid|paid|partially_refunded|refunded|failed).
  - **Trip:** starts_at, ends_at, hours, guests, occasion, customer_notes, admin_notes.
  - **Contact snapshot:** contact_first_name, contact_last_name, contact_email, contact_phone, heard_from.
  - **Money:** (payment_plan, balance_due_cents and balance_due_at were dropped in 4.3, D22) subtotal_cents, discount_cents, tax_cents, gratuity_bp, gratuity_cents, total_cents, amount_paid_cents, amount_refunded_cents, balance_due_cents, balance_due_at.
  - **Snapshots:** promo_code_id?, policy_snapshot (json), pricing_snapshot (json).
  - **Lifecycle:** hold_expires_at, confirmed_at, completed_at, cancelled_at, cancelled_by_role (customer|owner|admin|system), cancellation_reason, source (web|admin|phone), timestamps.
- **booking_items:** booking_id, **booking_extension_id?** (set on the lines of an extension), type (charter|captain|package|addon|cleaning|discount|tax|gratuity|fee), addon_id?, label, description, quantity, unit_amount_cents, amount_cents, **payee** (platform|owner|captain), sort.
- **booking_extensions** (Phase 8.5, §7.9):
  - id, ulid, booking_id, hours (whole hours, R14), old_ends_at, new_ends_at.
  - **status** (hold|paid|expired|cancelled|refunded), hold_expires_at, confirmed_at.
  - subtotal_cents, tax_cents, gratuity_cents, total_cents, payment_id?, idempotency_key (unique), requested_by, timestamps.
  - The booking's `ends_at` and `hours` change only when the extension is **paid**. Its lines are `booking_items` with `booking_extension_id`, so receipts and the ledger keep the extension separate.
- **booking_status_history:** booking_id, from_status, to_status, actor_id?, note, created_at.
- **booking_complaints:**
  - id, ulid, booking_id, customer_id, boat_id, owner_id?, category (boat_condition|cleanliness|late_start|safety|missing_amenities|captain|other), description.
  - **owner_response**, status (open|under_review|resolved|rejected).
  - resolution (no_action|deduction|refund|deduction_and_refund), resolution_note, deduction_cents, refund_id?, ledger_entry_id?, opened_at, resolved_at.
  - Photos are attached through `media`.
- **promo_codes:** id, code (unique), type (percent|fixed), value, applies_to (charter|subtotal), min_subtotal_cents, max_discount_cents, starts_at, ends_at, max_redemptions, max_per_user, redemptions_count, is_active.
- **promo_redemptions:** promo_code_id, booking_id, user_id, discount_cents.

### 6.6 Payments and finance

- **customer_payment_methods:** user_id, stripe_payment_method_id, brand, last4, exp_month, exp_year, is_default. This is a display cache only.
- **payments:**
  - id, booking_id, user_id, provider_intent_id (unique), provider_charge_id.
  - kind (deposit|balance|full|extension|extra), amount_cents.
  - status (requires_payment|requires_action|processing|succeeded|failed|canceled), failure_code, failure_message, paid_at.
  - idempotency_key (unique), meta (json).
- **refunds:** id, payment_id, booking_id, amount_cents, reason (customer_cancel|owner_cancel|weather|complaint|admin|dispute), policy_pct?, status, provider_refund_id, created_by, note.
- **disputes:** id, payment_id, provider_dispute_id, amount_cents, reason, status, evidence_due_by.
- **webhook_events:** id, event_id (unique), type, payload (json), received_at, processed_at, error.
- **ledger_entries** (the money truth for owners and captains):
  - id, account_type (owner|captain|platform), account_id?, booking_id?, payout_id?, complaint_id?.
  - type (earning|commission|gratuity|refund_reversal|deduction|adjustment|payout), amount_cents (signed).
  - status (pending|available|on_hold|paid|reversed), available_at, description, created_by, meta.
- **payouts** (withdrawals):
  - id, ulid, payee_type (owner|captain), payee_id, amount_cents, method (stripe_connect|manual).
  - status (requested|approved|processing|paid|rejected|on_hold|failed), provider_transfer_id.
  - requested_at, reviewed_at, paid_at, rejection_reason, note.

### 6.7 Communication and content

- **contact_messages:**
  - id, name, email, phone, preferred_date, guests_range, experience_id?, message.
  - status (new|open|replied|closed|spam), replied_at, source_page, ip, user_agent.
- **contact_replies:** contact_message_id, user_id, body, sent_at.
- **newsletter_subscribers:** email (unique), status (pending|subscribed|unsubscribed), token, confirmed_at, unsubscribed_at.
- **notifications:** Laravel database notifications, for the bell icon.
- **settings:** key (unique), value (json), group. Cached.
  - Business info: name, phone, email, address, hours, social links.
  - Booking and pricing: tax_rate_bp, weekend_multiplier_bp, gratuity options, hold_minutes, season dates. *(deposit_bp and balance_due_days were dropped in 4.3, D22.)*
  - Owner money: commission_bp, **payout_delay_hours = 72**, **complaint_window_hours = 72**, **min_withdrawal_cents = 5000**.
  - Captains (R12): **captain_offer_hours = 24**, **captain_release_hours = 48**, captain_ack_reminder_hours = 12, captain_turnaround_minutes = 30 (the minimum gap between two trips of one captain). Placeholders except the first two.
  - Extensions (R15): **extension_cutoff_minutes = 30**, extension_hold_minutes = 10, extension_max_trip_hours = 8 (the longest a trip can become). Placeholders except the first. Extensions are always whole hours (R14), so there is no step setting.
- **pages:** slug, title, body (sanitized HTML), seo fields, is_published. Holds terms, privacy and cancellation policy.
- **faqs, testimonials:** content shown on the site.
- **media** (every file):
  - id, uuid, model_type, model_id, collection, disk (public|private), **path**.
  - original_name, mime, size_bytes, width, height, variants (json: paths of the size variants).
  - alt, sort, uploaded_by.

---

## 7. Business rules

### 7.1 Price (`QuoteService`, calculated on the server only)

```
charter   = round(hourly_rate × (Fri–Sun ? weekend_multiplier : 1)) × hours
            (or the boat's fixed duration price, if the owner set one)
captain   = captain_rate × hours                      (always included, its own line)
addons    = the add-ons the guest ticked, flat or price × guests (optional; the experience is free and
            its suggested extras are ordinary add-ons — nothing is charged unless ticked)
cleaning  = fuel & cleaning fee
discount  = promo (% of charter, or a fixed amount, capped)
tax       = tax_rate × (subtotal − discount)          (9% placeholder)
gratuity  = gratuity % × (charter + captain)          (optional: 0/15/18/20)
total     = subtotal − discount + tax + gratuity
payment   = the full total, charged at checkout (D22: no deposit, no balance, no request to book)
```

The JS only *shows* prices it gets back from the server. The server ignores any price sent by the browser and recalculates at hold time and again at payment.

### 7.2 Availability (`AvailabilityService`)

A slot is free only if all of these hold:

- The boat is `published` and `booking_enabled`.
- The start is one of the boat's **start times** that weekday (every half hour 5 AM – 10 PM on the grid, Chicago time; the trip may end later), and, with an experience, also one of the experience's start times.
- It doesn't overlap a block, a blackout or the end of the season.
- The experience allows that weekday and season.
- It is at least `advance_notice` ahead of now.
- It doesn't overlap any `hold` or `confirmed` booking of that boat (+ buffer), **using each booking's current end time** (after paid extensions) **and any extension still on hold** (§7.9).
- Guests ≤ max_guests.

**No double booking:** the hold is created in a DB transaction that locks the boat row (`lockForUpdate`) and re-checks the overlap before inserting.

**One "busy times" source.** `AvailabilityService` gives the busy intervals of a boat (bookings + extension holds + buffer, blocks, closed hours) and of a captain (assigned trips + `captain_turnaround_minutes`, unavailability). The boat page's booking card, the fleet's date filter, holds, trip offers (§7.8) and extensions (§7.9) all use it, so they can never disagree.

### 7.3 Booking lifecycle

```
hold (15 min) ──payment ok (webhook)──▶ confirmed ──trip ends──▶ completed
   └─timeout─▶ expired                     ├─▶ cancelled (customer | owner | admin | weather) ─▶ refund (§7.4)
                                           └─▶ no_show
```

There is **no request to book** (D22, 2026-11-25): owner boats are booked the same way. The `requested` / `declined` statuses were removed in 4.3.

Payment status and captain assignment are tracked separately. Every change is written to `booking_status_history` + `audit_logs`.

- **Captain state** (worked out from the booking and its offers, not stored as its own status): needs a captain → offered → assigned → acknowledged (§7.8).
- **An extension doesn't change the booking status.** A paid extension moves `ends_at` and writes a history line ("extended by 1 h, ends 9:00 PM").

### 7.4 Cancellation and refunds (Boatsetter-style, flexible)

Each boat uses one policy. The owner chooses it in the wizard; for house boats, the admin chooses. The rules are stored as data, so the admin can edit the hours and percentages without code changes.

| Policy | Full refund | 50% refund | No refund |
|---|---|---|---|
| **Flexible** (recommended) | ≥ 24 h before the start | – | < 24 h |
| **Moderate** | ≥ 5 days (120 h) | 3–5 days (72–120 h) | < 3 days (72 h) |
| **Strict** | ≥ 30 days (720 h) | 15–30 days (360–720 h) | < 15 days (360 h) |

**How the refund is calculated** (customer cancels):

```
refund_pct       = from the policy snapshot, using hours between now and the trip start
refundable_total = refund_pct × (total − gratuity) + gratuity      (gratuity is always returned)
refund           = max(0, amount_paid − (total − refundable_total))
```

Example: total $1,000 (no gratuity), paid in full (D22).
- Cancel in the 50% window → refund $500.
- Cancel in the 100% window → refund $1,000.

**Special cases**

- **Weather or safety cancellation** (by the admin): 100% refund, or a reschedule if the customer prefers.
- **Owner cancels a confirmed trip:** 100% refund to the customer. The admin can deduct a penalty from the owner's earnings, as a `deduction` ledger entry with a reason (R5 ✅).
- **Admin override:** the admin can always issue a custom full or partial refund with a written reason. This needs password re-confirmation and is audited.

**Every refund automatically reverses the owner's share** of that booking (a `refund_reversal` ledger entry).

**Trip extensions are not refundable by the customer** (R16). Only the admin can refund one, fully or partly, e.g. when the weather ends the trip early (custom refund, with a reason and an audit entry).

### 7.5 Money split for owner boats (proposed; confirm with the client and an accountant)

| Line | Goes to |
|---|---|
| Charter | Owner, minus commission (20% placeholder) |
| Captain fee | Platform when a platform captain drives (the platform pays the captain); owner when the owner captains |
| Package + add-ons | Platform |
| Fuel & cleaning | Owner (owner boats) / platform (house boats) |
| Tax | Platform (remits it) |
| Gratuity | Captain / crew, 100% |

House boats (the client's own fleet): all revenue goes to the platform. Only captain pay and gratuity go into the captain ledger.

**Extensions use the same split** for their lines (charter, captain, tax, gratuity), and the captain's pay grows by pay rate × extra time.

### 7.6 Owner earnings, 72-hour release, complaints and withdrawals

```
booking confirmed                          → owner ledger entry "pending"
trip ends + 72 h, no open complaint        → "available"   (scheduled job, hourly)
owner requests withdrawal                   → payout "requested"
   (needs: available ≥ $50, payouts connected, 2FA, password re-confirm, payouts not paused)
admin approves                             → Stripe Connect transfer → "processing" → "paid"
```

**Complaints (customer → about the boat or trip)**

1. The customer files a complaint from the trip page **within 72 hours after the trip**. They choose a category, write a description and can attach photos.
2. The booking's owner earnings switch to **on_hold** automatically. The owner is notified and can write a response.
3. The admin reviews it and resolves it with one of these:
   - **No action**: the earnings are released.
   - **Deduction**: any amount up to the booking's earnings is taken from the owner, as a negative `deduction` entry with a reason. The owner gets an email.
   - **Refund to the customer** (optional, can be combined with a deduction).
   - **Reject** the complaint.
4. If a deduction is larger than the owner's available balance, the balance goes negative and is recovered from future earnings.

**Admin powers (all audited)**

- Approve, reject or hold a withdrawal.
- **Pause all payouts** of an owner (with a reason).
- Hold any single entry.
- Add a manual adjustment (+/−) with a reason.

### 7.7 Listing lifecycle

```
draft → pending_review → approved → published ⇄ paused (owner)
             ↕                          ↓
     changes_requested            suspended (admin) / archived
             ↓
          rejected
```

- Admin-added boats can be published directly.
- Edits to price, specs, location or insurance after publishing go into `pending_changes` and need re-approval. Text and photo edits publish immediately.
- **Wizard rules kept from the mockup:**
  - At least 4 photos.
  - More than 32 ft → no renter operation.
  - More than 33 ft → must be captained.
  - Commercial insurance → an extra step.
- **New Pricing step:**
  - Hourly rate, optional fixed price per duration, minimum hours, weekend multiplier and the fuel & cleaning fee.
  - The owner sees an "estimated earnings after commission" preview.
- A daily job turns off `booking_enabled` when insurance expires, and emails the owner and the admin.

### 7.8 Captains: accounts, trip offers, assignment (D19)

**Accounts (R11)**

- **Apply:** `/captains/apply` creates the account (role `captain`) and a captain profile with `application_status = pending`. The form asks for name, phone, bio, license type, number and expiry, years of experience, languages and the license document (private disk). The admin approves or rejects it with a reason, and the captain gets an email.
- **Invite:** the admin creates the profile in Admin → Captains and presses "Send invite". The captain gets a signed link (valid 7 days), sets a password and is approved straight away.
- Changes to the license (type, number, expiry, document) after approval go back to the admin for review. Text changes (bio, languages, photo) apply at once.

**Eligible captain.** A captain can be offered or assigned a trip only if every one of these passes:

1. The profile is approved and active, and the account is active.
2. Their license is valid on the trip date.
3. The license type allows the guest count (an OUPV "six-pack" captain can take at most 6 passengers).
4. They have no overlapping trip (+ `captain_turnaround_minutes`) and no unavailability at that time (§7.2).

The admin sees every captain in the request and assign forms. Ineligible ones are greyed out with the reason.

**Trip offers: the first captain to accept gets the trip**

1. A booking is **confirmed** (paid) → it shows as "Needs a captain" on the admin home and in the bookings list. Boats with `captain_source = owner` are skipped: the owner drives.
2. In the booking page the admin presses **"Request captains"** and picks **all eligible captains** or **selected ones**, plus a deadline (default `captain_offer_hours` = 24 h, never later than the trip start). An optional "Notes for captain" goes with it.
3. Each chosen captain gets a `captain_trip_offers` row and is told in **three places**: the dashboard, the bell and an email.
4. The offer shows the date and time (Chicago time), duration, boat, harbor + map, guest count, experience, admin notes and **the pay for the trip** (R13). It has **Accept** and **Decline** buttons.
5. **Accept** runs in a DB transaction that **locks the booking row** (`lockForUpdate`). It then re-checks that the booking is still confirmed, still has no captain, the offer is still open and not expired, and the captain is still eligible (point 4 above: they may have accepted another trip meanwhile). Then it assigns the captain (`captain_assigned_via = offer`, `captain_acknowledged_at` = now). Two captains pressing Accept in the same second → one gets the trip, the other sees "This trip was just taken."
6. The other offers become **filled**: they leave those dashboards, and those captains get a short "Trip filled" note in the bell, not an email.
7. Nobody accepts by the deadline → the offers become **expired**, and the admin gets an alert. The admin can send new offers or assign directly.
8. The admin can **withdraw** open offers at any time.

**Direct assignment** (the admin picks one captain, as before)

- The same eligibility checks apply. The captain is told in the dashboard, by the bell and by email (+ `.ics`), and `captain_notified_at` is set. A "Resend" button is available.
- The captain presses **Acknowledge** in the dashboard or from the email link (`captain_acknowledged_at`). Not acknowledged after `captain_ack_reminder_hours` → a reminder goes to the captain and an alert to the admin. This covers missed emails.
- **Reassignment:** the old captain is told "unassigned"; the new captain gets the assignment.

**Releasing a trip (R12).** A captain can give back an accepted or assigned trip up to `captain_release_hours` (48 h) before the start, with a reason. The booking goes back to "Needs a captain" and the admin is alerted. Closer to the trip, only the admin can remove them. Every release is written to the booking history and the audit log.

**What the captain sees (R6)**

- In the captain dashboard: date and time, harbor + map, boat, guests, experience, occasion, admin notes, the **booking reference** and the customer's **full name**, so the group can be recognized at the dock.
- **Never** the customer's phone, email, address or own notes. The admin passes on anything else through "Notes for captain".
- **Emails to the captain** carry the trip details and the **reference only** (no customer name), plus a link to the dashboard and an `.ics` file.

**Changes after assignment.** If the booking is cancelled, rescheduled or extended (§7.9), the captain is told (dashboard, bell, email).

The customer sees the captain's name, photo and bio on the trip page and in the reminder email.

### 7.9 Trip extensions (D20)

**When the customer can extend (R15, R17)**

- Only while the trip is running, from its start until **`extension_cutoff_minutes` (30) before its end**. Before the trip, the admin changes the hours by hand for now.
- Only if the boat has **"Allow trip extensions"** on (`extensions_enabled`, default on, set by the admin).
- Only for a confirmed, fully paid booking. By trip day the balance has always been charged (§7.1).

**What is offered.** The trip page (My trips → trip, on the phone) shows an **"Extend your trip"** card. The server works out the options in **whole hours** (R14): +1 h, +2 h, +3 h, … The longest option is the number of whole hours that fit in the smallest of these (a 1 h 40 min gap gives +1 h only):

- the time until the boat's next booking or block, minus the buffer (another customer's details are never shown, only "available until 9:00 PM");
- the boat's closing time that day;
- the time until the captain's next trip (minus the turnaround) or unavailability;
- the time until the trip reaches `extension_max_trip_hours`.

If not even one hour is free, the card says so and shows the business phone.

**Price (calculated on the server only, §7.1).** It uses the booking's **pricing snapshot** (the rates at booking time), not today's rates:

```
charter  = hourly rate (with the same weekend multiplier as the trip) × extra hours
captain  = captain_rate × extra hours
tax      = tax_rate × (charter + captain)
gratuity = the booking's gratuity % × (charter + captain)
total    = charter + captain + tax + gratuity
```

No cleaning fee, package fee, add-ons or promo code. A boat's fixed duration prices don't apply to extensions (the hourly rate does).

**Flow**

1. The customer picks an option. `POST` creates a `booking_extensions` row with status **hold** for `extension_hold_minutes` (10). It runs in a DB transaction that locks the boat row and re-checks the boat and the captain, the same as a booking hold. While on hold, the extra time counts as busy for everyone else (§7.2). This needs an idempotency key and a rate limit.
2. **Payment** is a separate Stripe payment (`payments.kind = extension`): one tap with a saved card ("Pay $X with Visa •••4242"), or a new card. 3-D Secure works as usual.
3. **Only the webhook confirms it** (§8.4). On success, in one transaction: the extension becomes **paid**; the booking's `ends_at` and `hours` move; the extension lines are added; a history line is written; and the owner and captain ledger entries are added (§7.5).
4. **Who is told:**
   - **The captain:** "Extension paid — the trip now ends at 9:00 PM", in the dashboard, by the bell and by email. No approval is needed: the system has already checked that the captain is free.
   - **The owner** (owner boats) and **the admin**.
   - **The customer:** a receipt and an updated `.ics` file.
5. The payment fails or the hold expires → the extension becomes **expired** or **cancelled** and nothing changes.

**Refund (R16).** An extension can't be refunded by the customer. Only the admin can refund it (§7.4).

---

## 8. Security plan

### 8.1 Authentication, sessions, authorization

- **Fortify:** register, login, logout, email verification, password reset, password confirmation, **2FA (TOTP + recovery codes)**.
  - 2FA is optional for the admin (their choice, R10 — recommended live) and required for owner money actions.
- **Passwords:** `Password::min(12)->mixedCase()->numbers()->uncompromised()`, hashed with bcrypt (12 rounds).
- **Throttling:**
  - Login: 5/min per email + IP, with lockout.
  - Password reset, 2FA and registration are throttled too.
- **No account enumeration:**
  - Login and reset errors are generic.
  - Reset links are single-use and expire after 60 minutes.
- **Account changes:**
  - Changing the email needs the current password and re-verification, and the old address is told (2.5).
  - Changing the password logs out all other devices and sends an email.
  - A login from a new device sends an email to owners and the admin.
- **Sessions:**
  - `SESSION_DRIVER=database` (already set), `SESSION_ENCRYPT=true`.
  - Cookies are `secure` (production), `http_only` and `same_site=lax`. The production cookie name uses the `__Host-` prefix.
  - The session ID is regenerated on login and on a role change, and invalidated (with the CSRF token) on logout.
  - Admin idle timeout is 30 minutes, and "remember me" is off for the admin.
  - An "Active sessions" page lets users log out other devices.
  - All cookies are encrypted. **Nothing sensitive goes in localStorage.** The mockup's localStorage demo storage will be removed.
- **Authorization:**
  - Named Gates plus a Policy on every model.
  - Records in URLs are ULIDs, with scoped route-model binding.
  - Other users' records return 404.
  - `role`, `status`, `owner_id` and every price field are **never** taken from a request.

### 8.2 Forms and JSON endpoints — the security standard (every form and endpoint must pass this)

These rules apply to every Blade form, every JSON endpoint called from our JS, and every admin screen.

**Transport and request integrity**

1. **Same-site session auth.** JSON endpoints are in `routes/web.php`, so they use the encrypted session cookie and **CSRF protection**. There are no API tokens and no CORS (cross-origin calls are refused).
2. **One JS fetch helper** (`resources/js/lib/http.js`) is used for every call. It:
   - sends `X-CSRF-TOKEN` from the page's meta tag, `Accept: application/json` and `credentials: 'same-origin'`;
   - uses a timeout;
   - handles 401/403/404/419/422/429 the same way everywhere (for example, 419 = session expired → reload with a message).
3. **HTTP methods:** `GET` only reads. Every change uses `POST`, `PATCH` or `DELETE`, so it is always CSRF-checked.

**Validation (input)**

4. **A FormRequest for every endpoint and form.**
   - `authorize()` calls the Policy or Gate.
   - `rules()` lists **every allowed field**.
   - Controllers use only `$request->validated()`. Extra fields sent by an attacker are ignored. `$request->all()` is never used, and models have explicit `$fillable`.
5. **Strict rules for every field:**
   - Field types:
     - Type and **max length** on every string.
     - Integer ranges (e.g. guests 1 to the boat's max).
     - **Enums** via `Rule::enum`.
     - **Scoped exists checks** (e.g. the boat must be published: `Rule::exists('boats','ulid')->where('status','published')`).
     - Dates in the allowed booking window, and time slots from the experience's list.
     - Arrays with `max` items and `distinct`.
     - Emails as `email:rfc,strict`, phones by regex.
   - Text handling:
     - Text fields are trimmed, and plain-text fields reject HTML.
     - The admin's rich text is cleaned with an HTML sanitizer.
6. **The server is the source of truth:**
   - The browser sends **only choices** (boat ULID, experience slug, date, time, hours, guests, add-on slugs, promo code). Prices, totals, status, owner and role are always calculated or decided on the server.
   - Wizard steps are validated on the server. Submitting **re-validates every step**, so a step can't be skipped by calling the endpoint directly.
7. **Admin tables:**
   - Sort and filter parameters are checked against a **whitelist** of columns, never put into SQL directly.
   - Pagination size is capped.
   - Only Eloquent / query-builder bindings are used; there is no raw SQL with user input.

**Responses (output)**

8. **Only safe fields go out.**
   - JSON is built with **API Resources** that list exactly which fields are returned. Internal data (captain pay rates, owner emails, admin notes, other users' data) is never exposed.
   - Errors are generic: `APP_DEBUG=false` in production, so there are no stack traces.
9. **XSS protection in JS and Blade:**
   - JS renders server text with `textContent` or one escape helper. `innerHTML` is never used with unescaped data.
   - Initial page data is embedded with `Js::from()` / `@js` (safe against `</script>` injection).
   - Blade always uses `{{ }}`.
10. **Strict Content-Security-Policy with nonces.** Vanilla JS lets us block inline scripts and `eval` completely. Allowed sources: self, Stripe (`js.stripe.com` + its frames), Turnstile, and the Google Maps embed.

**Abuse protection**

11. **Rate limits** use a named limiter per endpoint:
    - quote/availability, e.g. 60/min;
    - hold creation, e.g. 10/min (booking holds and extension holds);
    - captain offer answers (accept / decline / acknowledge / release), e.g. 30/min;
    - captain applications, e.g. 5/hour per IP;
    - promo code checks, e.g. 10/min (stops code guessing);
    - uploads;
    - contact and newsletter, e.g. 5/hour per IP;
    - login, register and password reset.
12. **Idempotency:** hold creation (bookings and extensions), payments, refunds, payouts and withdrawals require a client idempotency key, stored with a unique index. A double-click or retry can never create two of anything. Each runs inside a **DB transaction**. Accepting a trip offer locks the booking row, so a repeated Accept returns the same result.
13. **Bots:** Turnstile + a honeypot field on every public, unauthenticated form (register, captain application, contact, newsletter) and on checkout.
14. **Uploads:**
    - Separate endpoints with mime, size and dimension validation, re-encoding, and UUID filenames (§9).
    - PHP `upload_max_filesize` / `post_max_size` set to match.
15. **Sensitive actions** (refunds, payouts, deductions, settings, payout account changes, role changes, Trash / restore / delete permanently) need **password re-confirmation** and a written reason, and are written to the audit log. In the admin the password is typed in the action's own dialog (`SensitiveActionRequest`, 3.4.1). It is skipped when it was confirmed in the last 3 hours, the same window and session key as `password.confirm`, so the reason the admin wrote is never lost on a redirect.

**Proof that it works**

16. **Every endpoint has tests** for: not logged in, wrong user (expects 404), wrong role, invalid input (expects 422), valid input, rate limit hit (expects 429), and duplicate request (idempotency).

### 8.3 HTTP hardening

- **Headers** set by `SecurityHeaders`:
  - CSP (above) and HSTS (production).
  - `X-Content-Type-Options: nosniff` and `frame-ancestors 'none'`.
  - `Referrer-Policy: strict-origin-when-cross-origin`, a `Permissions-Policy`, and `Cross-Origin-Opener-Policy: same-origin`.
  - The `X-Powered-By` header is removed.
- **HTTPS everywhere:** HTTPS is forced (Namecheap's free SSL) and the session cookie is secure.
- **Stripe webhooks:** signature + timestamp check. Each event is processed once (`webhook_events.event_id` is unique).

### 8.4 Payments (PCI)

- **Card data stays with Stripe:**
  - The Stripe Payment Element is used (PCI SAQ-A), so card data never touches our server or logs.
  - Saved cards are Stripe PaymentMethods; we keep only brand and last 4 digits.
- **Server controls the money:**
  - Amounts are calculated on the server and sent in cents.
  - Stripe calls carry idempotency keys.
- **Confirmation from Stripe only:** a booking is `confirmed` only when **Stripe** reports success — its signed webhook, or our server retrieving the PaymentIntent (the return page and its status poll, so a missed or late webhook never strands a paid guest; 4.5). The browser's redirect or its word alone never confirms it.
- **Checks and fraud:** a nightly reconcile job compares Stripe with our DB, and Stripe Radar screens for fraud.

### 8.5 Data, audit and operations

- **Data protection:**
  - Encrypted columns: license numbers, insurance policy numbers, admin notes.
  - Logs never contain cards, passwords or tokens.
- **Audit:**
  - `audit_logs` records every admin action, status change and money action.
  - `login_events` records logins, with alerts on repeated failures.
- **Production configuration:**
  - `APP_DEBUG=false`; config, route and view caches enabled.
  - The production DB user is not `root` and has only the rights it needs.
  - `.env` is outside the web root and never committed.
- **Dependencies:** `composer audit` / `npm audit` run before every deploy, and dependencies are updated monthly.
- **Backups:** encrypted, including `storage/`, with a tested restore (§16).
- **Legal pages:** Terms, Privacy and Cancellation Policy pages, plus an unsubscribe link in marketing emails.
- **Account deletion:** personal data is anonymized, but financial records are kept (about 7 years).

---

## 9. Files and storage (D15)

| Disk | Folder | Files | Access |
|---|---|---|---|
| `public` | `storage/app/public` → `public/storage` (`php artisan storage:link`) | Boat photos, experience images, captain photos, avatars, gallery, hero media | Public URL |
| `local` (private) | `storage/app/private` | Insurance COIs, captain licenses, complaint photos, receipts, exports | **Only through a controller that checks the Policy**, or a short-lived signed URL |

- The DB stores **only the path relative to its disk**, in `media.path`, e.g. `boats/01JD…/photos/9f1c….webp`. Variant paths go in `media.variants`. Records with one image (harbors, experiences, captains) keep it in their own `*_path` column (one file, no variants).
- **Upload pipeline** (`MediaService`, Phase 3.3; settings in `config/media.php`):
  1. Check the **real type from the file's bytes** (finfo) — JPG, PNG, WEBP, GIF (first frame) for images; PDF too for documents — and an allowed name extension (`.php`, `.phtml`, `.svg` … are refused; a PNG named `.jpg` is fine, because the stored name comes from the real type). Size: 10 MB by default per form, 25 MB for listing photos (the mockup's wizard), 25 MB hard ceiling in the service. Images over **40 megapixels are refused before they are opened** (a decompression bomb could exhaust a shared host's memory); minimum 600 × 400 either way round by default.
  2. **Re-encode images to WebP** (quality 80, GD). This drops EXIF/GPS, comments and anything hidden after the image data; photos are turned upright from their EXIF orientation first.
  3. Sizes: the main file ≤ 1600 px wide, plus `card` 800 px and `thumb` 400 px (each made from the one before; a small image is never enlarged — a missing size falls back to the main file). `Media::url('card')`, `Media::srcset()`.
  4. Saved under a **random UUID filename**. The visitor's file name is only kept as a label (`original_name`, cleaned).
  5. Only `.webp` / `.pdf` files are ever written. `storage/app/public/.htaccess` also refuses to serve any script or dotfile there (second lock, Apache / LiteSpeed).
  - PDFs are allowed only as documents (`MediaService::addDocument`), always on the private disk, kept byte for byte. Private files are opened through `GET /files/{uuid}` (`FileController` + `MediaPolicy`: the admin or the uploader; anyone else → 404; PDFs as a download, images inline; `Cache-Control: no-store`; limiter `files` 60/min). Later phases add readers to `MediaPolicy` (e.g. the boat owner for a complaint, Phase 7).
  - FormRequests validate files with **`SafeUpload::image()` / `SafeUpload::document()`** (the same checks, as a field error). If a controller ever skips the rule, the service still answers with a 422 on `file`.
  - A failed database write removes the files it just saved.
- Deleting a record → `MediaService::delete()` / `deleteAllFor()`; a queued job (`DeleteStoredFiles`) deletes the files **after the transaction commits** (a rolled-back delete keeps its files). A weekly command removes orphaned files (with the permanent delete in 3.4).
- Design assets (logo, favicon, hero video and the mockup's images) are built with Vite into `public/`; they are not in `media`.
- Because files live on disk, **backups must include `storage/`**. Moving to S3 later is a config change only.
- **Host requirements:** the **GD** extension (or Imagick: `IMAGE_DRIVER`), `exif` (upright photos) and `fileinfo`; `upload_max_filesize` ≥ 25M, `post_max_size` ≥ 30M, `memory_limit` ≥ 256M (MediaService raises it to 512M while it works, where the host allows). Locally (XAMPP): remove the `;` before `extension=gd` in `C:\xampp\php\php.ini` and restart PHP — every PHP process (artisan serve, Apache) must be restarted, because on Windows they share one OPcache and a mix of with/without-GD processes breaks it.

---

## 10. Code architecture

```
app/
  Enums/          Role, BoatStatus, BookingStatus, PaymentStatus, PayoutStatus, LedgerType, ComplaintStatus,
                  CaptainApplicationStatus, CaptainOfferStatus, ExtensionStatus…
  Models/         User, OwnerProfile, Boat, Harbor, Experience, Addon, Captain, CaptainTripOffer,
                  CaptainUnavailability, Booking, BookingItem, BookingExtension, BookingComplaint, Payment,
                  Refund, LedgerEntry, Payout, PromoCode, ContactMessage, Media, Setting, AuditLog…
  Policies/       one per model (ownership + Gates)
  Http/
    Controllers/  Public/, Account/, Owner/, Captain/, Admin/, Webhooks/   (thin; HTML or JSON)
    Requests/     one FormRequest per form / endpoint / wizard step
    Resources/    API Resources = the JSON whitelist
    Middleware/   EnsureAccountIsActive, EnsureUserHasRole, RequireTwoFactor, AdminIdleTimeout, SecurityHeaders
  Services/       QuoteService, AvailabilityService, BookingService, PaymentService (Stripe, behind an
                  interface so tests can fake it), RefundService, CancellationPolicyService, LedgerService,
                  PayoutService, ComplaintService, CaptainAssignmentService (eligibility, offers, direct
                  assignment, release), TripExtensionService, MediaService, SettingsService,
                  AuditService, TurnstileService
  Actions/        CreateBookingHold, ConfirmBooking, CancelBooking, AssignCaptain, OfferTripToCaptains,
                  AcceptTripOffer, ReleaseTrip, ApproveCaptain, CreateExtensionHold, ConfirmExtension,
                  SubmitListing, ApproveListing, RequestWithdrawal, ApproveWithdrawal, ResolveComplaint…
  Events/ Listeners/  e.g. BookingConfirmed → emails, ledger entries, notifications
  Jobs/           ChargeBalance, ExpireHolds (bookings + extensions), ExpireCaptainOffers,
                  RemindUnacknowledgedTrips, ReleaseEarnings, SendTripReminders, ProcessStripeWebhook,
                  InsuranceExpiryCheck, CleanupOrphanFiles
  Mail/ Notifications/
  Support/        Money, ChicagoTime
resources/
  views/
    layouts/      app (public), wizard (minimal header), account, owner, captain, admin, mail
    partials/     header, footer, mobile-menu, admin-sidebar, admin-topbar
    components/   public: button, badge, yacht-card, experience-card, section-heading, input, select,
                  radio-card, accordion, lightbox, toast, stepper, price-summary
                  admin:  data-table, filters, stat-card, status-badge, form-section, modal, confirm-dialog,
                          empty-state, tabs, pagination
    pages/ booking/ checkout/ account/ owner/ captain/ admin/ auth/ errors/
  js/
    lib/          http.js (CSRF fetch), dom.js (escape/render), money.js, dates.js, toast.js
    pages/        home.js, yachts.js, yacht.js, booking/*.js, checkout.js, contact.js, extend-trip.js
    owner/        wizard/*.js, boats.js, payouts.js
    captain/      offers.js, availability.js
    admin/        tables.js, booking-calendar.js, assign-captain.js, uploads.js
routes/  web.php (public + JSON) · account.php · owner.php · captain.php · admin.php · webhooks.php · console.php (scheduler)
```

- **Rule:** business logic lives in Services and Actions, never in controllers, Blade or JS. Status changes go through one method per model (e.g. `BookingService::transition()`), which checks the allowed moves and writes the history.
- **Scheduler** (one cron job every minute on Namecheap):

  | Job | Runs |
  |---|---|
  | queue worker | every minute |
  | ExpireHolds (bookings + extensions) | every minute |
  | ExpireCaptainOffers | every 5 minutes |
  | RemindUnacknowledgedTrips | hourly |
  | ChargeBalance | hourly |
  | ReleaseEarnings | hourly (72 h rule) |
  | SendTripReminders | hourly |
  | InsuranceExpiryCheck | daily |
  | Reconcile | nightly |
  | Backups | daily |

### 10.1 URL map

| Area | Routes |
|---|---|
| Public | `/` · `/yachts` · `/yachts/{slug}` · `/experiences` · `/pricing` · `/about` · `/contact` · `/terms` · `/privacy` · `/cancellation-policy` · `/sitemap.xml` |
| Booking JSON | `/book` (wizard page) + JSON: `GET /book/availability`, `POST /book/quote`, `POST /book/hold` |
| Checkout | `/checkout/{booking:ulid}` (+ JSON: `POST …/payment-intent`, `POST …/promo`) · `/bookings/{ulid}/confirmation` |
| Auth | `/login` · `/register` · `/forgot-password` · `/reset-password/{token}` · `/email/verify` · `/two-factor-challenge` · `/user/confirm-password` |
| Customer | `/account` (profile, security, sessions, cards) · `/account/trips` · `/account/trips/{ulid}` (cancel, receipt, complaint; extend: JSON `GET …/extension-options`, `POST …/extensions`, `POST …/extensions/{ulid}/payment-intent`) |
| Captain | `/captains/apply` · `/captain` (home: offers + next trips) · `/captain/offers` (JSON `POST …/{ulid}/accept`, `…/decline`) · `/captain/trips` · `/captain/trips/{ulid}` (acknowledge, release) · `/captain/availability` · `/captain/earnings` · `/captain/profile` · `/captain/invite/{token}` |
| Owner | `/list-your-boat` → `/owner/boats/{ulid}/wizard/{step}` (JSON `PATCH` per step, `POST` photos) · `/owner` · `/owner/boats` · `/owner/bookings` · `/owner/calendar` · `/owner/earnings` · `/owner/payouts` · `/owner/complaints` |
| Admin | `/admin` · `/admin/bookings` (+ calendar) · `/admin/boats` · `/admin/listings` · `/admin/experiences` · `/admin/addons` · `/admin/captains` · `/admin/harbors` · `/admin/users` · `/admin/payouts` · `/admin/complaints` · `/admin/payments` · `/admin/promo-codes` · `/admin/inbox` · `/admin/content` · `/admin/settings` · `/admin/audit-log` |
| Webhooks | `/webhooks/stripe` |

---

## 11. Emails and notifications (queued, branded, plain-text version included)

| Audience | Emails |
|---|---|
| Account | Verify email, password reset, password changed, new-device login, 2FA on/off |
| Customer | Booking confirmation (+ .ics + PDF receipt), payment receipt, balance reminder (T-10 days), balance charged / failed, trip reminder T-24h (harbor + captain), captain assigned, **trip extended (receipt + updated .ics)**, cancellation + refund, complaint received / resolved |
| Owner | Listing submitted / approved / changes requested / rejected, new booking, booking cancelled, **trip extended**, complaint opened (please respond) / resolved, deduction applied, withdrawal requested / approved / paid / rejected, payouts paused, insurance expiring |
| **Captain** | Application received / approved / rejected, invite, **new trip offer**, offer withdrawn, **trip assigned (+ .ics)**, please acknowledge (reminder), trip released, unassigned, trip changed / cancelled, **trip extended**, trip reminder T-24h. Trip details, admin notes and the **booking reference only — no customer name or contact details** (R6). "Trip filled" is a bell note only |
| Admin | New booking, unassigned trip within 7 days, **new captain application**, **offers expired with no captain**, **trip not acknowledged**, **captain released a trip**, **trip extended**, failed balance charge, listing to review, withdrawal to approve, new complaint, new contact message, dispute opened |
| Contact form | Auto-reply to the sender; notification to the business inbox (Reply-To = sender); admin replies are sent from the dashboard |

There are also in-app notifications (bell icon) for customers, owners, captains and the admin. Captains need them from Phase 8, so the bell is built there and reused in 9.3.

---

## 12. Admin dashboard (custom, D3)

| Section | Contents |
|---|---|
| **Home** | Today's and upcoming trips; revenue (gross / net / commission); bookings by status; **action list** (listings to review, withdrawals to approve, open complaints, trips without a captain, offers expired with no captain, trips not acknowledged, captain applications, failed charges, new messages) |
| **Bookings** | List with search, filters, sort and pagination + **calendar**; detail (line items, payments, extensions, history, notes); change status; **request captains (all eligible / selected, deadline) or assign directly**, offer status per captain, withdraw offers; cancel / refund; resend emails; manual booking (phone orders) |
| **Fleet** | Boats (house + owner: photos, pricing, availability, blocks, experiences offered, **Unavailable toggle**, **Allow trip extensions toggle**); **Listings review queue**; harbors; amenities |
| **Catalog** | Experiences (slots, durations, weekdays, season, fees, images); add-ons; cancellation policies (edit hours and %); promo codes |
| **Captains** | **Applications queue (approve / reject with a reason)**, invite, profiles (email, phone, license + expiry, documents, pay rate), license changes to review, availability, offers, trips, earnings |
| **Finance** | Payments, refunds, disputes, owner ledgers, **withdrawals (approve / reject / hold)**, **pause owner payouts**, deductions and adjustments, captain payouts, CSV export |
| **Complaints** | Queue, detail (customer text and photos, owner response, booking earnings), resolve (no action / deduction / refund / reject) |
| **Users** | Customers, owners and captains: detail, suspend / ban, reset 2FA, notes |
| **Inbox** | Contact messages (status, reply by email, spam); newsletter subscribers |
| **Content** | Business info, hero, FAQs, testimonials, gallery, legal pages, SEO |
| **Settings** | Tax, weekend multiplier, gratuity options, commission, payout delay (72 h), complaint window, minimum withdrawal, hold minutes, season; **captains** (offer hours, release hours, acknowledge reminder, turnaround); **extensions** (cut-off, hold minutes, longest trip) |
| **System** | Audit log, login log, failed jobs |

---

## 13. Pages

**From the mockup (ported 1:1):** home, yachts, yacht detail, experiences, pricing, about, contact, booking (4 steps), checkout, confirmation, the list-your-boat wizard, my boats, owner dashboard.

**New pages, in the same design:**

- **Auth:** login, register, forgot / reset password, verify email, 2FA challenge, confirm password.
- **Customer:** My trips; trip detail (cancel with a refund preview, receipt, calendar, complaint form, captain card, **"Extend your trip" card** during the trip); profile & security (password, 2FA, sessions); saved cards.
- **Captain** (no mockup exists: built with the **owner dashboard's design** — sidebar, cards, tables, pills — no new look): apply form, invite / set password, home (open offers + next trips), offers, trips + trip detail (acknowledge, release), availability, earnings, profile + license documents.
- **Owner:**
  - Wizard: a **Pricing step**.
  - Pages: bookings, calendar (blocks), earnings, withdrawals + payout setup (Stripe Connect), complaints (respond), boat edit.
- **Admin:** layout (sidebar + top bar) and every screen in §12, built from the admin UI kit (Phase 1.7).
- **Other:**
  - Legal pages: terms, privacy, cancellation policy.
  - Error pages: 403, 404, 419, 429, 500, 503.
  - Email templates.

**Demo-only behaviour to remove:** fake availability, localStorage storage, test-card autofill, "Demo: use sample photos".

---

## 14. Phases and steps

- Each step ends with its QA gate (§15) and your go-ahead before the next step starts.
- All work happens on `main` (decided 2026-11-22); each phase ends with one or more commits made by the user.
- **v1 launch = Phases 0–9 + 11.** Phase 10 (CMS / SEO / reports) starts as a minimal version before launch and grows afterwards.

### Phase 0 — Foundation (setup) ✅ done 2026-11-21 (committed on `main` together with Phase 1)
0.1 Replace the Laravel-Boost bootstrap text in `CLAUDE.md` / `AGENTS.md` with **our own project rules**: stack, conventions, the §8.2 security standard, the QA gate. (No Boost.)
0.2 Pin `config.platform.php` to **PHP 8.4.1** (R1) ✅ done; set up `.env.example`, APP_NAME and the timezone handling (UTC storage, Chicago display).
0.3 Tailwind v4 `@theme` from the mockup (navy, brass, shell, container); self-hosted Playfair Display + Manrope; Lucide via npm.
0.4 JS foundation: `lib/http.js` (CSRF fetch), `lib/dom.js` (escape/render), `money.js`, `dates.js`, `toast.js`.
0.5 Static assets: logo, favicon, hero video/poster, design images.
**QA:** `npm run build` passes; `php artisan test` is green; a theme test page shows the right colors and fonts.

### Phase 1 — Port the design to Blade (no DB yet; temporary demo data in a config file) ✅ done 2026-11-22 (awaiting approval)
1.1 Layout, header, footer, mobile menu, and the public Blade components. ✅ done 2026-11-21: `layouts/app`, `partials/{header,mobile-menu,footer}`, components (logo, button, badge, section-heading, page-hero, yacht-card with the Unavailable state, stars, form.{input,select,textarea,checkbox}, accordion), JS `ui/*` (header, mobile menu, reveal, accordion, carousel, counters, lightbox, newsletter demo), `config/site.php`, `config/demo.php` + `DemoCatalog`, `money()`, every public route named, and /dev/components (local). QA: header and footer pixel-match the mockup at 390/768/1280/1440, 56 page loads clean, 25 PHP + 26 JS tests. (The experience card moved to 1.2, because it is page-specific.)
1.2 Public pages: home, yachts, yacht detail, experiences, pricing, about, contact. ✅ done 2026-11-21. Controllers: Home, Yacht (index + show), Experience, Page (pricing, about, contact). `DemoQuote` (PHP) + `demo-quote.js` share the same price maths, checked by parity tests. Filters work without JS (GET) and instantly with JS. Unavailable boats can't be booked from any link. QA: every section matches the mockup at 4 widths; the only differences are intended copy fixes and v4's date-field normalisation (−1/−2px). 68 page loads clean, 46 PHP + 31 JS tests. Fixed mockup bugs: 16px tablet overflow on About, copy that contradicted the refund/gift-card decisions.
1.3 Booking wizard, checkout and confirmation screens (UI only). ✅ done 2026-11-21. `BookingController@create` (4-step wizard, `pages/booking.js`), `CheckoutController` (create / promo / store / confirmation), `CheckoutRequest` + `PromoCodeRequest`, `BookingSelection` (cleans every deep link and hidden field against the catalog: unknown or unavailable boats, out-of-window or wrong-weekday dates, non-slot times, same-day slots with ≤2h notice, unknown or duplicate add-ons). The server recalculates every price on submit and ignores posted prices, status, role and ids. Promo codes are checked one at a time (`POST /checkout/promo`, named limiter `promo`); `POST /checkout` uses the `checkout` limiter. Card numbers are never posted (unnamed inputs; only brand + last 4). Trips inside the 7-day balance window are paid in full. Phase 1 demo: nothing is stored and no payment is taken; the confirmation lives in the session. QA: booking/checkout/confirmation match the mockup at 4 widths; end-to-end browser flow (wizard → promo → validation → submit → confirmation) passes; 84 page loads clean; 111 PHP + 31 JS tests.
1.4 Owner screens: the list-your-boat wizard (+ the new Pricing step), my boats, owner dashboard, earnings, withdrawals, complaints. ✅ done 2026-11-21. Wizard ported 1:1 (`ListingController`, `layouts/focus`, `pages/list-boat.js`, options in `config/listing.php`) plus the **Pricing step** (hourly rate, minimum hours, fuel & cleaning fee, weekend markup 0–25%, optional fixed price per duration, live "estimated earnings after commission" table — `lib/owner-estimate.js`, same rules as `DemoOwner::charterCents`). Owner area (`x-owner.shell` pill nav): dashboard and my boats (mockup ports), plus new screens in the same design: bookings (tabs), calendar (month view, block/unblock days), earnings (balances + statement), payouts (withdraw form, payout method, history, paused state), complaints (guest report, owner response, outcome). Demo data: `config/demo_owner.php` + `DemoOwner` (relative dates; 72 h release, on-hold, deductions, payouts per §7.5–7.6). Phase 1 demo only: wizard drafts, calendar blocks, withdrawals and complaint responses stay in the browser — Phase 3/7 replace them with endpoints. QA: wizard layout matches the mockup at 768/1280/1440 (390: +22 px from the longer step-3 label); full wizard walk-through, pricing maths and escaping checked in the browser; 135 PHP + 37 JS tests.
1.5 Auth screens, customer account screens, legal and error pages. ✅ done 2026-11-21. Auth (preview only, same URLs/names Fortify uses; forms are `data-demo-form` until Phase 2): login, register, forgot / reset password, verify email, 2FA challenge (code ↔ recovery code), confirm password — `layouts/auth`, `x-form.password` (show/hide). Customer account (`x-account.shell`): My trips; trip detail with payment summary, captain card, add to calendar (`lib/ics.js`), **cancel with a live refund preview** (`RefundPreview`, §7.4 rules stored in `config/listing.php` as data) and the **report-a-problem form** inside the 72 h window; profile & security (details, password, 2FA, devices, saved cards, delete account). Legal pages (`x-legal-page`, DRAFT wording — client + lawyer must review; a draft banner shows locally only): terms, privacy, cancellation policy. Error pages 403/404/419/429/500/503 (standalone layout, no stack traces). Demo data: `config/demo_customer.php` + `DemoCustomer`. QA: 175→196 PHP + 39 JS tests; interaction checks (cancel dialog, report form, password match, 2FA switch); 60 page loads clean.
1.6 Port the mockup's JS into ES modules (no global state; `textContent` rendering). ✅ done 2026-11-21 (audit): every mockup behaviour has a module — `CYB.fmt/date` → `lib/money.js`, `lib/dates.js`; `CYB.quote` → `lib/demo-quote.js` (+ PHP parity); `CYB.availability` → `lib/demo-availability.js`; `CYB.listings` → `lib/listing-store.js`; toast/icons/reveal/counters/accordion/carousel/lightbox/quick-book/header/menu/newsletter → `lib/*`, `ui/*`; every page script → `pages/*`. Checked: no `innerHTML` outside `dom.js`, no `eval`, no globals, no inline handlers or styles. `localStorage` is used only by Phase 1 demos (listing drafts, calendar blocks) and the yacht page.
1.7 **Admin UI kit + shell:** sidebar, top bar, data table, filters, stat cards, forms, modals, status badges, empty states, plus static versions of the main admin screens. ✅ done 2026-11-21. Shell: `layouts/admin` (sidebar with counts → drawer on mobile, search, notifications, `ui/admin.js`). Kit: `x-admin.page-header`, `stat`, `card`, `table` (`min` prop, scrolls on mobile), `sort` (aria-sort), `pill`, `modal` (`<dialog>`), `sensitive-form` (reason + password note + audit). Screens (demo data via `DemoAdmin`): dashboard (stats, needs-attention, next trips, withdrawals, activity), bookings (search / whitelisted filters + sort / pagination) + booking detail (**captain assignment with the §7.8 checks**, notes for captain, refund / cancel modals), boats (Bookable switch), listing reviews + review page (checklist, owner pricing, approve / changes / reject), captains (+ add), users (role filter), inbox, payouts (approve / hold / reject, pause owner, adjustment), payments, complaints (resolve: no action / deduction / refund / reject), experiences & add-ons & promo codes & harbors, settings, audit log. QA: 196 PHP + 39 JS tests; drawer / modal / switch interaction checks.
**QA:** visual comparison with the mockup at 390 / 768 / 1280 / 1440 px; zero console errors; no horizontal overflow; accessibility basics; Lighthouse ≥ 90.

**Phase 1 closing items (2026-11-22):** header account menu — a profile icon with a dropdown (guest: log in / create account; signed in: avatar, trips, profile, owner links, log out) on every public page and in the mobile menu; `/owner/*` and `/account/*` preview the signed-in state with the demo accounts. **Laravel Boost ^2.10 installed (dev only, D17):** `.mcp.json` (MCP server `php artisan boost:mcp`), `boost.json`, guidelines block appended to `CLAUDE.md` (our rules above it take precedence). QA: 198 PHP + 39 JS tests; header fits at 390–1440 (height unchanged, nav never wraps); 32 + 64 page loads clean. Client progress report (PDF) delivered.

### Phase 2 — Authentication, roles and security baseline
2.1 Fortify + our screens: register, login, logout, verify, reset, throttling, password rules. ✅ done 2026-11-22. `laravel/fortify ^1.43` (features: registration, reset passwords, email verification; 2FA in 2.4, profile/password update in 2.5; passkeys not used — their migration removed). Users migration: `ulid` (route key, numeric id hidden), `first_name`/`last_name` (old `name` split, rollback restores it), `phone`, `marketing_opt_in`. `CreateNewUser` uses validated fields only (role/status/ids can never come from the request). Password rules: 12–128 chars, mixed case, a number, Have I Been Pwned check (skipped in tests). Throttles: login 5/min per email + IP, every auth route 30/min per IP (`auth-forms`), verification resend 6/min. No enumeration: one login error for wrong password and unknown email; "forgot password" always gives the same answer. A password reset logs the account out everywhere (sessions deleted, remember token rotated). After sign-up → "Check your inbox". Signed-in users opening /login go to My trips. The header account menu shows the real user. Accepted exception: Fortify's email-verification link contains the numeric user id (signed, expiring, and only works for the signed-in owner of that account). QA: 223 PHP + 39 JS tests (27 auth tests); real-browser flow register → verify → logout → wrong/right login → forgot → reset → only the new password works; migrations roll back with data intact; 52 page loads clean.
2.2 User columns (`role`, `status`, …), the Role enum, Gates, Policies skeleton, `app:create-admin`. ✅ done 2026-11-22. Migration: `role` (default customer), `status` (default active), `status_reason`, `last_login_at`, `last_login_ip`, soft deletes. Enums `Role`, `UserStatus`; `User::isAdmin/isOwner/isCustomer/isActive`; role/status never fillable. `UserAccountService` (makeOwner, setStatus with reason — suspending logs out everywhere, admins can't change their own status). Gates in `AuthServiceProvider::ABILITIES` (access-admin, access-owner-area, list-boats, request-withdrawal, manage-bookings, assign-captains, review-listings, manage-catalog, approve-payouts, issue-refunds, resolve-complaints, manage-users, manage-settings, view-audit-log); inactive accounts get none. `UserPolicy` (view / update / changeStatus / delete); model policies (Boat, Booking, Payout, Complaint) arrive with their models. Login: suspended / banned / deleted accounts refused (message only after the correct password; unknown emails run a dummy hash check for even timing); last login time + IP recorded. `php artisan app:create-admin` (hidden password prompt, same password rules, one admin unless --force, email pre-verified). Local seeder: test@example.com (customer) + owner@example.com (owner), password `Local-Test-2026`, never in production, never an admin. Header menu links follow the role. QA: 254 PHP tests (31 new); migrate / seed / rollback / re-migrate on a temporary database. The user runs the migrations and seeder on their own MySQL.
2.3 Middleware: active account, role, 2FA required, admin idle timeout, `SecurityHeaders` + CSP with nonces, HTTPS / proxies. Also set `'serve' => false` on the private `local` disk (`config/filesystems.php`). That removes Laravel's built-in signed `GET`/`PUT /storage/{path}` routes (found in Phase 0), because private files are served only by our own Policy-checked controller. (The `.js` class is already done in Phase 1.1: it is rendered server-side, with `public/css/noscript.css` for visitors without JS, so no inline script is needed.) ✅ done 2026-11-22. Routes: account → `auth` + `active`; owner area, List your boat and admin → also `verified`; the role check is a Gate (`can:access-owner-area`, `can:list-boats`, `can:access-admin`). `active` (`EnsureAccountIsActive`) logs a suspended / banned account out on its next request (JSON: 403). `admin.idle` (`AdminIdleTimeout`): the admin is logged out after 30 idle minutes (`ADMIN_IDLE_MINUTES`); the admin login ignores "remember me". `SecurityHeaders` on every response, error pages and redirects included: CSP with a fresh nonce per request (Vite adds it to its tags; no `'unsafe-inline'` / `'unsafe-eval'`; Google Maps frames allowed; the Vite dev server only while `npm run dev` runs), nosniff, `X-Frame-Options: DENY`, Referrer-Policy, Permissions-Policy, COOP, HSTS (production over HTTPS), `X-Powered-By` removed. HTTPS: `ForceHttps` (production: HTTP → HTTPS, 301 / 308, always to our own host), `URL::forceHttps` in production, session cookie secure by default in production, session encrypted by default (`SESSION_ENCRYPT=true`). Proxies: none trusted by default (`config/trustedproxy.php`, `TRUSTED_PROXIES` only behind Cloudflare or similar), so `X-Forwarded-*` can't fake a visitor's IP or HTTPS. The header account menu and the admin top bar show the real signed-in user (admin pages are still demo data until Phase 3). The "2FA required" middleware moves to 2.4, together with the setup screen it sends the admin to. QA: 298 PHP + 39 JS tests (44 access tests: route × role matrix incl. an unverified owner, return to the intended page, suspended logout, idle timeout, remember me, headers, nonce on every script / style tag, HTTPS redirect, trusted proxies); real-browser scan as guest / customer / owner / admin, 96 page loads at 390 + 1280 px: zero CSP violations, zero console errors, no overflow; account menu links checked per role. No new migrations.
2.4 2FA + recovery codes, and the "2FA required" middleware for the admin area (from 2.3); password confirmation; active sessions + logout other devices; `login_events`; `audit_logs` + `AuditService`; security emails. ✅ done 2026-11-22.
  - **2FA:** Fortify `twoFactorAuthentication(confirm, confirmPassword)` — TOTP + 8 recovery codes; it is on only after a valid code from the app; a code works once. Login → `/two-factor-challenge` (code or recovery code, 5 tries a minute; a used recovery code is replaced). Managed on **`/account/two-factor`** (behind `password.confirm`, `Cache-Control: no-store`): turn on (QR code as an image + setup key) → confirm with a code → recovery codes (shown once open, can be replaced) → turn off. Linked from the "Two-factor login" card on Profile & security. The admin never gets "remember me", also through the challenge.
  - **`2fa` middleware** (`EnsureTwoFactorEnabled`): without 2FA → `/account/two-factor` (JSON: 403); the reason survives the password confirmation. Owner money actions get it in Phase 7. **Not on the admin area (R10, user decision 2026-11-22):** 2FA is the admin's choice — **Admin → Settings → Security** shows On / Off and links to turn it on, manage it or turn it off (the page recommends keeping it on live). Lost phone + codes: **`php artisan app:reset-two-factor --email=…`** (reason, confirmation, logs out everywhere, emails the person, audit entry) — the only way back in for the single admin. The owner payouts checklist shows the real 2FA status.
  - **Password confirmation:** Fortify's "Confirm it's you" screen (3 hours, `auth.password_timeout`), used by the 2FA page, Fortify's 2FA routes and "log out other devices".
  - **Devices** (Profile & security): this device + the account's other database sessions (browser + OS from a small `UserAgent` helper, IP, last active). **"Log out other devices"** (`DELETE /account/sessions`: `password.confirm`, `throttle:account-security` 10/min): ends the other sessions and rotates the remember-me token. Session ending now lives in one place, `SessionService` (password reset, suspension, 2FA reset use it too).
  - **`login_events`** (`LoginEventService`, `SecurityEventSubscriber`): logins (also "remember me" and after the challenge), failed logins (linked to the account when the email exists; passwords never stored), lockouts (the `login` limiter fires `Lockout`, the visitor still gets our 429 page), logouts, 2FA failures / on / off, recovery code used, password changed, other devices logged out. `last_login_at` / `_ip` updated there.
  - **New-device detection:** a random `cyb_device` cookie (encrypted, httpOnly, 400 days); the log keeps only its SHA-256. A login from a browser without a known device id → email, **for owners and the admin only, never on the very first login**.
  - **Security emails** (queued; `SecurityContext` = device · IP · Chicago time): password changed (after a reset; also after a password change in 2.5), new-device login, 2FA turned on / off (incl. "turned off by our team").
  - **`audit_logs`** (`AuditService`, `AuditLog` refuses updates and deletes, morph map `user`): role change (first listing), status change (with the reason), 2FA reset, admin created by the command. The admin Audit log screen still shows demo data until Phase 3.
  - **Scheduler** (`routes/console.php`): `queue:work --stop-when-empty` every minute (the emails), `model:prune` daily.
  - **Fix (found by the user, 2026-11-22): no CSS with `npm run dev`.** The 2.3 CSP allowed the Vite dev server by its origin, but on Windows Vite wrote `http://[::1]:5173`, and a CSP can't name an IPv6 address (Chrome drops it) → the dev stylesheet and the dev-served fonts were blocked (2.3 QA had only run against `public/build`). Now: `vite.config.js` pins the dev server to `127.0.0.1`; in dev mode the origin is also in `font-src` / `img-src`; an IPv6 origin falls back to the bare scheme (dev only); tests never use the dev server (`tests/TestCase.php`) and the dev-mode CSP has its own test. Checked in Chrome with the dev server running.
  - **Fix (found by the user, 2026-11-22): the setup QR code could not be scanned** ("Unrecognized QR code" from the Chrome Authenticator extension). Fortify's QR has no quiet zone (margin 0), is grey and was shown at 176 px (≈3.9 px per module, blurred). Now our own render: black on white, the standard 4-module quiet zone inside the frame, exactly 5 screen pixels per module (265–285 px), shown at its real size. Checked by decoding the real page's QR with two decoders (jsQR + the stricter qrcode-reader): both read it at 100% zoom; the setup key always works as a fallback.
  - Open: "alerts on repeated failures" (§8.5) — the data is in `login_events`; the alert/admin view comes with the admin Users screen (Phase 3). The privacy policy should mention the device cookie when the legal text is written.
  - QA: 342 PHP (1,788 assertions) + 39 JS tests (16 2FA, 13 security-log, 9 user-agent, access matrix incl. "admin without 2FA"); migrations roll back; real-browser flows on an isolated SQLite server — admin at 390 px (forced to 2FA → confirm password → QR → wrong / right code → recovery codes → /admin opens → devices → log out others → challenge with a wrong / real recovery code → fresh TOTP at 1280) and owner at 1280 px (turn on, wrong code, payouts checklist, turn off); zero console errors / CSP violations, no overflow; the 5 queued emails delivered with device, IP and Chicago time.
2.5 Role-based redirects, header account menu (UI built in Phase 1: `partials/account-menu`, `HeaderComposer` — switch it to `$request->user()`), account pages (profile, email change, password, delete account). ✅ done 2026-11-22.
  - **Redirects (§4.2):** after login, after the 2FA code, and when a signed-in user opens /login: the page they wanted, else admin → `/admin`, owner → `/owner`, everyone else → My trips (`App\Support\Home`, our `LoginResponse`). The header account menu already showed the real user (2.3).
  - **Your details** (Fortify `updateProfileInformation`, PUT `/user/profile-information`, errors in their own bag): name, phone, marketing (the checkbox posts a hidden 0, so a failed password form no longer unticks it). **Email change:** needs the current password (the box appears as you edit the email; the server requires it too), the new address must be verified again (owner area and listing wait for it; "Send the link again" on the page), the **old address gets an email** (new address partly hidden), security-log entry.
  - **Password** (Fortify `updatePasswords`, PUT `/user/password`): current password + the password rules; logs every other device out (this one stays), "password changed" email, log entry. "Last changed" comes from the new `users.password_changed_at` (set by the User model on every password change; existing accounts start from their sign-up date).
  - **Delete account** (`DELETE /account`, `DeleteAccountRequest` — own account only, never the admin (403), password, `throttle:account-security`): a dialog with the password (it reopens after a wrong one). `UserAccountService::deleteAccount`: anonymize (name "Deleted account", `deleted-{ulid}@deleted.invalid`, phone, password, 2FA cleared) + soft delete — bookings and payments keep pointing at the row (§8.5); every session ends; the security log forgets the email; audit entry; confirmation email to the real address; logged out → login page with a message. The address is free for a new sign-up. `deletionBlockers()` is the hook for Phase 4 (upcoming trips) and Phases 6–7 (owner boats / money owed).
  - Queued security emails skip an account deleted before the queue sent them (found in QA: one went to the anonymized address).
  - Demo removed: `DemoCustomer::profile()` and its config (saved cards stay demo until Phase 5).
  - QA: 356 PHP (1,948 assertions) + 39 JS tests (14 new account tests: redirects per role / intended / 2FA, details, email change, invalid input + JSON 422, role/status ignored, password change, delete incl. guest / admin 403 / wrong password / rate limit / anonymization); migration rolls back; real-browser flows on an isolated SQLite server with `npm run dev` running (dev CSP): login per role, email change → verify, wrong / right password change, delete dialog wrong → reopens → right → login page; 1280 + 390 px, zero console / CSP errors, no overflow; queued emails checked.
2.6 JSON endpoint foundation: error format, named rate limiters, API Resource base, idempotency-key middleware. ✅ done 2026-11-22.
  - **Error format** (`App\Support\JsonErrors`, rendered in `bootstrap/app.php`): every JSON error is `{"message"}` (+ `"errors"` for 422 validation, Laravel's own) with a generic text — no model names / ids in 404s ("No query results for model …"), no route or method list in 405s, no trace when `APP_DEBUG=false`. Only 409 messages are ours to show. HTML requests keep our error pages. `http.js` shows the friendly text per status (400 added).
  - **Named rate limiters** (`AppServiceProvider`): quote 60/min, hold 10/min, checkout 10/min, promo 10/min, uploads 30/min, contact 5/hour, newsletter 5/hour, account-security 10/min — per account when signed in, else per IP (contact / newsletter always per IP); the auth ones are in `FortifyServiceProvider`. **A test fails if any route that changes data has no `throttle:`** (local dev tools under `/_…` excepted).
  - **API Resource base** (`App\Http\Resources\ApiResource`): `{"data": …}`; each resource lists its own fields (the base `toArray()` throws, so "every column" can't leak); `publicId()` = the ULID, `iso()` = UTC ISO 8601; money in cents. First user: `PromoCodeResource` (POST `/checkout/promo`; `checkout.js` reads `.data`).
  - **Idempotency** (`idempotent` → `EnsureIdempotency`): key from the `Idempotency-Key` header (http.js) or a hidden `idempotency_key` field; scoped to the account / guest session + method + path; kept 24 h in the cache (`Cache::add` is atomic). Same key + same request → the stored answer is replayed (`Idempotent-Replayed: true`), the action runs once; same key + different request → 422; still running → 409; a failed request frees the key. Not on a real route yet — holds, payments, refunds, payouts and withdrawals use it from Phase 4 (plus their own unique keys in the tables).
  - QA: 364 PHP (2,010 assertions) + 39 JS tests (8 new: error shapes / no leaks, 429 Retry-After, limiters + per-account counting, the "every write route is throttled" guard, resource base, idempotency: missing / bad key, replay, different request, other account, form field, failure frees the key, parallel twin 409); browser: checkout promo applied / refused at 1280 + 390, no overflow, no JS errors.

**Phase 2 done (2026-11-22).**
**QA:** route × role access-matrix test; throttle tests; no-enumeration tests; session regeneration and cookie-flag tests; CSP check (no console violations).

### Phase 3 — Catalog database + admin v1
3.1 Migrations, models, enums, factories: harbors, boats, amenities, availability rules, blocks, experiences, boat_experience, addons, cancellation policies (Boatsetter rules), captains, settings, media. ✅ done 2026-11-22.
  - 5 migrations, 13 tables: `create_fleet_tables` (harbors, cancellation_policies, boats, amenities, amenity_boat, boat_availability_rules, boat_blocks), `create_catalog_tables` (experiences, boat_experience, addons), `create_captains_table`, `create_settings_table`, `create_media_table`. Columns as §6.2–6.4 / §6.7, plus: `experiences.image_path` (the mockup's card image, like harbors / captains), `sort` on cancellation_policies and amenities, block times as `dateTime` (UTC). `owner_profiles` + `boat_insurances` come with Phase 6, `captain_unavailability` with assignment (Phase 4).
  - Models: `Boat` (ulid route key; **status, ownership, owner_id and the review workflow are not fillable**; scopes `listed` = published, `bookable` = published + no Unavailable tag; photos = `media` collection "photos", cover first), `Captain` (ulid; license number **encrypted**; license number + pay rate hidden; `canTake(guests, day)` = license capacity + not expired), `Experience` / `Addon` / `Harbor` (slug route keys), `CancellationPolicy::refundPercent(hoursBefore)`, `BoatBlock::overlapping()` (a boat's blocks + every-boat blackouts), `Setting` (JSON value), `Media` (uuid, morph). Enums: BoatStatus, BoatOwnership, BoatType, CaptainSource, AmenityGroup, BlockType, AddonUnit, LicenseType. Morph map: boat, harbor, experience, addon, amenity, cancellation_policy, captain, setting. `User::boats()`.
  - Factories for all but Setting / Media (MediaService creates media in 3.3).
  - QA: 374 PHP tests (10 new); migrate / rollback / re-migrate / seed on a temporary SQLite database.
3.2 Seeders from the mockup's `data.js` (6 boats, 8 experiences, 7 add-ons, 4 harbors, 3 policies, settings) + images.
  - **User decision (2026-11-22, option B):** the 6 demo boats go to the live site too — with the **Unavailable** tag there (bookable locally, for testing) — so the design stays full; the admin can edit, hide or delete them. Demo captains are local only (fake emails must never receive assignments).
  - **Images:** the design images in `public/images` stay untouched (the developer changes them if the client asks). Records the admin manages (boat photos, experience and harbor images) get their **own copy** on the public disk (`boats/…`, `experiences/…`, `harbors/…`), so deleting or replacing one never touches a design file.
  - Seeders only **add what is missing** — re-running them never overwrites the admin's changes. `CatalogSeeder` (policies, amenities, settings, harbors, experiences, add-ons) and `DemoFleetSeeder` (boats + photos; captains when local) can run on the live server with `--class`; `DatabaseSeeder` (local) runs both plus the test accounts.
  - ✅ done 2026-11-22. Seeds: 3 policies (mockup rules), 77 amenities (the wizard's feature / extra / navigation lists + the demo highlights), 18 settings (booking, owners, business), 4 harbors, 8 experiences, 7 add-ons, 6 published house boats (rates, guests, badges, ratings, harbor, Moderate policy, highlights, experiences, 27 photos as `media` rows with size / dimensions), 4 demo captains locally (licenses mapped to oupv / master, encrypted). QA: 378 PHP tests (4 new + `RolesTest` now fakes the public disk, because the local seeder copies images); seeding twice on SQLite adds nothing; the design images are only copied. Photos show on the site once 3.3 (`storage:link`) and 3.5 (pages read the database) are done.
3.3 `MediaService` (upload pipeline, variants, private disk) + `storage:link`. ✅ done 2026-11-22.
  - `intervention/image-laravel` ^4.1 (approved package; Intervention Image v4, GD driver; `config/intervention-image.php`: auto-orientation on, animation off, strip on). The pipeline, rule, private route and host requirements are in §9.
  - New: `App\Services\MediaService` (`inspect`, `storeImage`, `addImage`, `addDocument`, `delete`, `deleteAllFor`, `deleteFiles`, `url`, `download`, `directoryFor`), `App\Rules\SafeUpload`, `App\Exceptions\InvalidUploadException` (a 422 on `file`), `App\Support\StoredFile`, `App\Jobs\DeleteStoredFiles`, `MediaPolicy`, `FileController` (`files.show`), limiter `files`, `Media::url()` / `srcset()` / `paths()`, `storage/app/public/.htaccess`. `php artisan storage:link` run locally (`public/storage`, gitignored).
  - **Seeders now store the design images through MediaService** (WebP; boat photos with card / thumb sizes): about 45 s for the 39 images. The design files stay untouched. A boat whose photos fail is rolled back (so re-running doesn't skip it). Without GD, seeding stops with a clear message before any harbor or boat is created. A local database seeded in 3.2 keeps its JPEG copies (they still work: no variants, so the main file is used); `php artisan migrate:fresh --seed` (+ `app:create-admin`) gives the WebP versions — optional.
  - QA: 403 PHP (2,343 assertions) + 39 JS tests (25 new: WebP + sizes + random names, no enlarging, EXIF / GPS / comment / appended PHP dropped, upright photos, 10 refused files — PHP as .jpg, image as .php / .phtml, PHP behind a GIF header, SVG, HTML as .webp, PDF as photo, empty, broken — nothing saved for any of them, decompression bomb, failed DB write leaves no files, collections / ULID folders, the SafeUpload rule, private documents, delete after commit + rollback, the file route: guest / other customer / other owner / suspended / uploader / admin / public photo / unknown / missing file / JSON 404 / 429, the `.htaccess`). Tests that seed use tiny stand-ins of the design images (`Tests\Concerns\FakesDesignImages`), so the suite stays fast. Real seeding twice on a temporary SQLite database + storage folder (45 s, then nothing added); an isolated server: `/storage` link serves files, `/files/{uuid}`: guest → login / 401, other owner → 404, admin → the PDF byte for byte with `attachment`, `no-store`, `nosniff`; zero PHP warnings. Fixed during QA: a PHP 8.5 deprecation in `Media::url()` (null array key) and `MediaService::delete()` deleting files for a stale model whose row still existed.
3.4 Admin screens: Boats, Experiences, Add-ons, Harbors, Amenities, Captains, Users, Settings, Cancellation policies; dashboard skeleton. ✅ done 2026-11-23 (3.4.1–3.4.7).
  - **Sub-steps (each with its own QA gate and go-ahead, planned 2026-11-23):**
    - **3.4.1 Admin foundations + Harbors.**
      - Shared pieces:
        - The **Trash pattern** (`TrashService`): Trash / Restore / Delete permanently, each with its blockers listed before you try.
        - The real `x-admin.sensitive-form`: a reason + the password typed in the same dialog. The password is skipped if it was confirmed in the last 3 hours, the same window as `password.confirm`, and on success the session counts as confirmed.
        - Audit entries with before / after for every change.
        - Whitelisted list queries (`ListQuery`), flash messages, the Active / Trash tabs and pagination.
        - The `manage-fleet` and `manage-captains` gates, and the `admin` (60/min) and `admin-sensitive` (10/min) limiters.
      - One migration: soft deletes on harbors and add-ons, plus `boats.extensions_enabled`.
      - Then the **Harbors** screen: list, add, edit, image, show / hide, and Trash. Moving a harbor to the Trash is blocked while boats depart from it; deleting it permanently is blocked while any boat, even one in the Trash, uses it.
      - ✅ **Done 2026-11-23.**
        - **Built:**
          - `App\Models\Contracts\Trashable` (label, title, blockers, own files) and `TrashService` (trash / restore / forceDelete). Delete permanently works only from the Trash and removes the `media` rows and own files after commit. Audit actions are `<morph>.trashed / .restored / .deleted_permanently`.
          - `SensitiveActionRequest` (reason 10–500 characters, password in the dialog unless confirmed in the last 3 hours, a correct password refreshes the confirmation, error bag `sensitive`, hidden `dialog`) and `RecordLifecycleRequest` (Policy ability picked from the controller method).
          - `AuditService::changes()` (before / after of dirty fields, masked keys), `App\Support\Admin\ListQuery`, `App\Rules\PlainText` (HTML refused in plain-text fields).
          - Components: `x-admin.lifecycle`, `x-admin.flash`, `x-admin.list-tabs`, `x-admin.pagination`, `x-form.file`; `x-admin.modal :open` (reopens with errors).
          - Gates `manage-fleet` and `manage-captains`; limiters `admin` (60/min) and `admin-sensitive` (10/min).
          - Migration `add_trash_and_extension_columns`.
          - `HarborPolicy`, `HarborRequest` (the slug is made from the name, unique including the Trash, so a double submit can't create two), `HarborService` (WebP image via MediaService, the old file deleted after the save, a new file removed again if the save fails), `Admin\HarborController` + routes `admin.harbors.*`, and Fleet → Harbors in the sidebar.
          - The demo harbors card was removed from "Experiences & add-ons".
        - **QA:**
          - 424 PHP tests (2,512 assertions) + 39 JS tests; 17 new harbor tests covering guest / customer / owner, 404s, list search / sort whitelist (`%` is searched literally), add with a photo + audit, invalid input incl. HTML and a fake image, double submit, a slug taken by the Trash, an edit diff (no change → no entry), photo replace / remove, a refused photo leaving everything as it was, reason + password / wrong password / the 3-hour window, the boat blocker, the Trash read-only page + restore, permanent delete only from the Trash with its photo gone, a blocker from a trashed boat, and 429.
          - Migrations roll back.
          - Isolated server (temp SQLite + temp storage, `/storage` served from the temp disk): the screens at 390 / 768 / 1280 / 1440 had no overflow, console errors or CSP reports. The full flow ran in Chrome: HTML in the name refused → added with a photo made in the page → wrong password (the dialog reopens with the error) → Trash → restore (password not asked again) → Trash → delete permanently. The photo's delete job ran on the queue, and the audit trail was complete with reasons.
        - **Fixed during QA:** on phones the Trash card came before Save; it now sits under the Save buttons on every width.
        - **Fix (found by the user, 2026-11-23): harbor photos didn't show.**
          - Cause: the user opened the site on `127.0.0.1:8000` while `APP_URL` is `localhost:8000`. Upload URLs were absolute (built from `APP_URL`), so they pointed at another origin, and the CSP (`img-src 'self'`) blocked every photo.
          - Fix: the public disk URL is now root-relative (`'url' => '/storage'` in `config/filesystems.php`), so uploads always load from the host the page came from. This also covers www vs the bare domain in production.
          - Where an absolute URL is needed (emails, og:image, the sitemap), wrap it in `url()`.
          - Reproduced in Chrome and checked after the fix; a regression test was added (425 tests).
    - **3.4.2 Experiences + Add-ons:**
      - Experiences: image, durations, start times, weekdays, season, includes, package fee, SEO.
      - Add-ons: price, flat or per guest, maximum quantity.
      - Both get the Trash pattern.
      - The old combined "Experiences & add-ons" page is split. Promo codes keep their demo screen until 4.7.
    - **3.4.3 Boats (1):**
      - A list of house and owner boats with filters.
      - The house boat form: details, specs, location, pricing, booking rules, operation, display.
      - Publish / unpublish, the **Unavailable** switch (with a reason) and the **Allow trip extensions** switch.
      - The admin edits owner boats too.
      - The Trash pattern.
    - **3.4.4 Boats (2):**
      - Photos: upload several, reorder, choose the cover, alt text, delete.
      - Amenities and highlights.
      - The experiences the boat offers, with the package-fee override.
      - Weekly opening hours, and blocks / blackouts.
    - **3.4.5 Amenities + cancellation policies + Settings:**
      - Amenities: add, edit, delete when unused.
      - Cancellation policies: edit the hours / % rules and the text guests read.
      - Settings: a cached `SettingsService` covering every group, including the captain and extension keys of §6.7. Saving needs a reason + password, and the change is audited.
    - **3.4.6 Captains (profiles):**
      - Photo, bio, contact details, license (the number encrypted, the document on the private disk), passengers allowed, pay rate, active / public.
      - The Trash pattern.
      - Accounts and invites come in 8.1.
    - **3.4.7 Users + Audit log + dashboard skeleton:**
      - Users: real list and filters; detail with login history, repeated failed logins, suspend / ban / reactivate and reset 2FA (existing services); reason + password + audit.
      - Audit log: real, with filters.
      - Dashboard: the numbers that exist now (boats, listings, captains with expiring licenses); the booking and money cards stay demo until Phases 4–7.
  - ✅ **3.4.2–3.4.7 done 2026-11-23, all in one go (the user's request: a whole step per response).**
    - **Shared:**
      - `ManagedRecordPolicy` (one Gate per model; a record in the Trash can't be edited) with a policy for every admin model.
      - `RecordWriter`, which replaces HarborService: save + audit diff + image column + relation syncs + an optional reason.
      - `PreparesSlug`, `IconNames` (admin icon pickers; a test checks each one is registered in `icons.js`), `Money::toCents / toBasisPoints / input / percentInput`.
      - The `$records()` route helper in `routes/web.php`, and `x-admin.confirm-fields` (reason + password at the bottom of full-page forms).
      - Migration `add_ulids_to_boat_blocks_and_amenities`: existing rows get a ULID; checked with raw rows on a temporary database.
    - **3.4.2:**
      - Admin → Experiences: lengths, start times, days (none or all ticked = every day), season, "what's included" lines, package fee in dollars → cents, SEO, image.
      - Admin → Add-ons.
      - Both have the Trash pattern. A boat stops offering an experience in the Trash (SoftDeletes scope on the pivot) and offers it again after Restore.
      - The old "Experiences & add-ons" page became Promo codes (demo until 4.7).
      - The sidebar is now Fleet (Boats, Listing reviews, Harbors, Amenities) and Catalog (Experiences, Add-ons, Cancellation policies, Promo codes).
    - **3.4.3–3.4.4:** Admin → Boats (`BoatService`, `BoatPhotoService`).
      - List: house + owner boats with ownership / status / bookable filters, cover thumbnails, and the **Bookable switch** (JSON `PATCH …/bookable` through `http.js`, `BoatAvailabilityResource`).
      - House boat form, also used to edit owner boats: details, specs, location (harbor by slug), pricing (hourly, weekend markup or the site setting, minimum hours, lengths with an optional fixed price, captain & crew per hour, fuel & cleaning, crew), booking rules (policy, notice, gap, multiple bookings, "not allowed on board"), operation (the 32 ft renter rule), display, Unavailable + reason, **Allow trip extensions**, amenities (by ULID), offered experiences with a fee override, and weekly opening hours (stored HH:MM:SS so MySQL and SQLite match).
      - Never from the form: ownership, owner, status, published_at.
      - **Publish / Hide:** a house boat → `paused`; an owner boat → `suspended`, with a required reason for the owner. Boats still in the listing review wait for the queue (Phase 6).
      - **Photos:** up to 10 per upload, 30 per boat, 25 MB each, WebP + card / thumb, alt text, make cover, move up / down, delete; another boat's photo → 404.
      - **Blocks:** maintenance / blackout / weather, typed in Chicago time and stored in UTC, scoped to the boat, with a ULID.
      - **Trash:** delete permanently removes the photos (rows + files), pivots, hours and blocks.
    - **3.4.5:**
      - Admin → Amenities: four lists; a name is unique per list; deleting removes the amenity from the boats that had it, with a reason + password, and the audit entry keeps the boat count.
      - Admin → Cancellation policies: up to 6 refund rows; a 0-hour row is required; the refund can't grow closer to the trip; reason + password.
      - Admin → Settings is real: `SettingsService` with DEFAULTS by group (including the captain and extension keys of §6.7, which `CatalogSeeder` now adds), one cached array cleared on save, only changed keys written, one `settings.updated` audit entry with the reason; percents / dollars → basis points / cents.
    - **3.4.6:** Admin → Captains.
      - Profile, photo (min 300 px), email (unique, also against the Trash), languages, pay per hour.
      - License number: encrypted, shown only as "•••• 1902", audited as "(changed)", and kept when the field is left empty. OUPV allows at most 6 passengers.
      - License documents go on the private disk (opened through `/files/{uuid}`); another captain's document → 404.
      - The Trash pattern.
    - **3.4.7:**
      - Admin → Users: role / status / search filters; the detail page has login history, a warning at 5+ failed logins in 24 h (PLAN §8.5), the audit entries about the account, **suspend / ban / re-activate** and **reset two-factor** (UserAccountService, reason + password, never your own account).
      - Admin → Audit log is real: area / who / search filters, record names also for the Trash, "N changes" with before → after.
      - Dashboard: a real **Fleet & catalog** card (live, unavailable, hidden, in review, experiences, active captains, licenses expiring within 30 days) and real recent activity; trips / revenue / withdrawals are labelled demo.
    - **QA:**
      - 470 PHP tests (3,023 assertions) + 39 JS tests; new: AdminCatalogTest 7, AdminBoatsTest 14, AdminRulesTest 6, AdminPeopleTest 9.
      - Pint clean; the build is fine; migrations roll back, and the ULID backfill was checked with raw rows.
      - Isolated server with the full demo seed (temp SQLite + temp storage; `APP_URL` deliberately on another host name, to prove the root-relative upload URLs): 22 screens at 390 / 768 / 1280 / 1440 px with no overflow, broken images, console errors or CSP reports.
      - Flows in Chrome: the Bookable switch (toast; survives a reload); a photo upload made in the page, then made the cover; bad opening hours (the error is shown and the typed value kept); a settings save (reason + password; the password isn't asked again after that); a user suspended, then a wrong password (the dialog reopens with the error); the audit log listing every one of these.
    - **Fixed during QA:**
      - The seeded demo boats use the mockup's own categories ("Motor Yacht", "Superyacht"…), which aren't in the wizard's list. The category select came up empty and the browser silently refused to save the form. Now the boat's current category is always offered and accepted (`BoatOptions::categories($current)`, with a test).
      - The Active / Trash tabs wrapped onto two lines next to the boat filters at 1280 px; they no longer shrink.
    - **Order on an existing database:** run `php artisan migrate` before seeding again — the seeders write amenity ULIDs.
  - **Added 2026-11-23 (R17):** the boat form gets an **"Allow trip extensions"** switch (new column `boats.extensions_enabled`, default on; admin-only, owners can't change it). The Captains screen manages captain profiles only; captain accounts, applications and invites come in Phase 8.1.
  - **User requirements (2026-11-22):** the admin can edit / unpublish / mark Unavailable **any** boat (house or owner); the Unavailable tag is admin-only (owners block dates in their calendar). Owner boats appear on the site only after the admin approves them (status `published`); an owner's edits to a live boat wait for re-approval (`pending_changes`, Phase 6). **Delete = soft delete (Trash) with Restore; Delete permanently = removes the database row** (and its files and pivot rows) — blocked when the record has bookings / payments (financial records are kept, PLAN §8.5); then it can only stay archived. Every delete / restore / permanent delete needs a password confirmation, a reason and an audit entry. Applies to boats, captains, experiences, add-ons and harbors (add soft deletes to harbors and add-ons); cancellation policies and settings are edit-only.
3.5 Public pages read from the DB. **Unavailable** tag from `booking_enabled`. Set up the **client's own boat** (once its details arrive) and mark the other boats unavailable. ✅ done 2026-11-23.
  - **`App\Services\PublicCatalog`** (a scoped singleton, forgotten after every request) replaces DemoCatalog for the public site: home, fleet, boat page, experiences, pricing, about, contact, footer, the booking wizard, checkout and confirmation (`BookingSelection`, `CheckoutRequest`).
    - It returns the same Fluent shape the Phase 1 views and the booking JS were built on, so the markup didn't change.
    - Only what the admin made public is shown: `published` boats (Unavailable tag = `booking_enabled`), shown experiences / add-ons / harbors; SoftDeletes leaves out the Trash.
    - A boat offers an experience only while that experience is shown, not in the Trash, and on for that boat.
    - Images are "storage/…" paths for `asset()` (same host → CSP-safe). Fleet cards use the 800 px "card" copy. A record without a photo gets `images/placeholder-boat.svg`.
    - Prices and rules (tax, deposit, weekend rate, balance days, gratuity) come from **Settings**; promo codes and the editable content (stats, testimonials, FAQs, team) stay demo config until 4.7 / Phase 10.
  - The demo screens that are still demo (My trips, owner area, admin bookings / finance) keep DemoCatalog + `DemoQuote::fromConfig()` until Phases 4–7.
  - **Hardened for a catalog the admin changes:**
    - Home tiles of a hidden / trashed experience drop out; this crashed before.
    - Pricing: packages whose boat or experience is gone drop out; no live boat → no estimator.
    - Experiences: one that no boat offers shows "On request".
    - Contact: no harbor → no map.
    - Checkout / confirmation samples use whatever is bookable, or send the visitor to /book.
  - **The client's own boat:** the admin can now add it in Admin → Boats and switch the demo boats to Unavailable — no code step is needed once the details arrive.
  - Not yet: a boat's own weekend markup, fixed duration prices and per-boat package fees are applied by the QuoteService (4.1); until then the site's estimates use the hourly rate and the site weekend rate.
  - **Tests:**
    - The page tests seed the demo catalog through `Tests\Concerns\SeedsPublicCatalog`, which uses `InstantMediaService` (no image encoding: 491 s → 48 s).
    - New PublicCatalogTest (8): hidden / trashed / draft records left out (also from the wizard and deep links), the admin's edits and Unavailable switch on the site, same-host `/storage` images + the placeholder, Settings prices, an empty catalog, home tiles, the checkout sample.
    - 478 PHP tests (3,083 assertions) + 39 JS tests; Pint clean.
  - **Browser QA:**
    - Isolated server with the real demo seed (27 WebP photos; `APP_URL` on another host name): 14 public pages (home, fleet + filter, two boat pages, experiences + filter, pricing, about, contact, wizard + deep link, checkout, confirmation) at 390 / 768 / 1280 / 1440 px with no overflow, broken images, console errors or CSP reports. Every page loads its images from `/storage` on the page's own host.
    - Flow: the admin switched The Azure off → its fleet card showed Unavailable → the wizard no longer preselected it from a deep link.
  - **Fixed during QA:** the fleet hero said "Six hand-picked yachts" whatever the fleet was; it now follows the live count (and still reads "Six…" with the 6 demo boats).
3.6 Cache the public catalog; SEO meta + sitemap. ✅ done 2026-11-23.
  - **Cache:**
    - `PublicCatalog` keeps the whole catalog as one cache entry of plain arrays (`catalog.public`, at most a day), built on the first visit. Settings are cached on their own.
    - It is cleared **after the transaction commits** by:
      - the `saved` / `deleted` events of Boat, Harbor, Experience, Addon, Amenity and Media (restore saves; a permanent delete fires `deleted`);
      - `PublicCatalog::flushAfterCommit()` where nothing fires an event (the boat form's pivot syncs and hours, the photo order).
    - Once cached, a page runs no catalog queries.
    - Checked with the production store (database cache) on the isolated server: the row is created, cleared by the admin's switch and rebuilt; the cached /yachts was ~40% faster on the dev server.
  - **SEO:**
    - **`/sitemap.xml`** (`SeoController`, from the cached catalog): the public pages + every live boat with `lastmod`; valid XML. Hidden, draft and trashed boats and all private areas are left out.
    - **`/robots.txt`** (the static file was deleted): production blocks the private areas (`SeoController::PRIVATE_PATHS`) and names the sitemap; **any other environment blocks everything**, so a staging copy never gets indexed.
    - Checkout and confirmation are `noindex`. The head adds `og:locale` and `<link rel="sitemap">`; canonical URLs already drop the query string.
    - Structured data (schema.org) stays in 10.2.
  - **Tests:**
    - CatalogCacheSeoTest (6): no catalog queries once cached; the switch, the photo order, the Trash, an amenity delete and publish / hide all clear it; the boat form's relation-only change clears it; the sitemap; robots off / on production; noindex + canonical + head tags.
    - Tests that change catalog rows with mass updates call `PublicCatalog::flush()` (no model events).
    - 484 PHP tests (3,139 assertions) + 39 JS tests; Pint clean.
  - **Browser QA (isolated server):** sitemap and robots headers / content checked; the admin's switch → cache cleared → tag on the site; public pages at 390 / 1440 clean. One run logged a dropped connection to the Google Maps embed (external); two reruns were clean.
**QA:** admin CRUD + policy tests; public pages show admin changes; upload security tests (wrong mime type, oversized file, PHP disguised as an image); sort/filter whitelist tests.

3.7 **Flexible boat listings + the client's first boat** (added 2026-11-23 on the user's request, before Phase 4). ✅ done 2026-11-23.
  - **Why:** the client sent their boat — a 2006 Cruisers Yachts 280 CXi, ~31 ft express cruiser, twin Volvo Penta 4.3L, generator, cabin, galley, enclosed head with shower — and the boat form had no place for much of it. Boats can be of any kind, so the form had to be flexible.
  - **Research (2026-11-23):**
    - Chicago charters are mostly Sea Ray Sundancer-style express cruisers (30–45 ft), party pontoons / catamarans, sailboats and motor yachts.
    - Rates run about $200–$800 per hour; the captain fee is sometimes included, sometimes separate.
    - A captained charter without a Coast Guard COI may carry at most 6 passengers.
    - Competitors list galley, head / shower, swim platform, generator and similar features.
    - Model specs: twin 4.3 GXi 225 hp, ~10 ft beam, ~100 gal fuel, V-berth + mid-cabin.
  - **Built:**
    - Migration `add_listing_details_to_boats`: `boats.highlights` (json) + `engine_model`. Each boat's old shared "highlight" amenities are copied into its own highlights and those shared rows are removed; rollback restores them (checked forward and back on a temp DB).
    - Amenity groups **Cabin & sleeping, Galley, Bathroom, Deck & swim** (+ seeded items). The old groups are now labelled Comfort & tech / Water toys / Navigation & safety (`AmenityGroup::label()` / `forms()`).
    - **Boat form:**
      - a sticky section navigation;
      - a **highlights** box (one per line, up to 12);
      - the engine make & model;
      - every feature list has an **"add a missing item" box** (comma-separated; a case-insensitive match reuses an existing item, otherwise the item is created in that list);
      - a **listing checklist** (description, highlights, type / size / guests, harbor, price, policy, 4+ photos, experiences, hours) with a **Coast Guard 6-passenger warning**;
      - "How to add a boat" steps on the create page.
    - **Public:** a boat's Amenities list = its highlights, then its ticked features, without repeats. A boat with no reviews shows **"New"** instead of "0.0" (card, boat page, wizard).
    - **`ClientBoatSeeder`** (also in DatabaseSeeder): the Cruisers 280 CXi as a **draft** house boat — featured, first in order, 6 guests, specs, 8 highlights, 20 ticked features, opening hours, and a charter description (the "for sale" wording of the client's text is left out). Safe to run again (skips an existing slug).
  - **Photos:** not taken from the internet. No Chicago ad for this boat was found, and other 280 CXi photos online belong to other boats and their sellers (copyright, and misleading to guests). The client's own photos are needed; until then the boat stays a draft.
  - **Still needed from the client (to publish):**
    - the boat's name, if it has one;
    - the harbor;
    - the price per hour, captain fee, and fuel & cleaning fee;
    - the cancellation policy;
    - the experiences it offers;
    - photos;
    - the guest limit (6 unless it has a COI).
  - **QA:**
    - 488 PHP tests (3,190 assertions) + 39 JS tests; Pint clean.
    - Real bug fixed during the tests: an empty "add a missing item" box arrived as null and made every boat save fail.
    - BoatListingTest (4): highlights / engine / new items in any list (case-insensitive reuse) and the boat page order; bad input; checklist + COI warning; the seeder → draft not public → fill in + publish → first on the home page with "New".
    - Browser (isolated server): the create / edit / amenities screens at 4 widths clean. The full client flow in Chrome: checklist showed the 5 missing items → price / harbor / policy / experiences + a new deck item saved → 4 photos → checklist 9/9 → Publish → first card on the home page; the boat page lists 28 amenity lines.
  - **Follow-up (2026-11-23, found by the user while adding a boat):**
    - **Desktop (lg+): the right-hand column follows the page** (`<x-admin.form-aside>`, `below-nav` on the boat form).
      - Save / Cancel stay in that column, under "On the site", as before (`<x-admin.form-actions submit cancel>`).
      - The column sticks under the top bar (and the boat section chips) while the form scrolls.
      - A column taller than the screen scrolls on its own, and Save / Cancel stick to its bottom edge (the cards fade under them).
      - `ui/admin.js` (`fitFormAside`) keeps the column no taller than the screen space below its top, so Save is in view from the top of the page too, not only once the column sticks.
      - So the checklist and Save stay in view on the user's 1366 × 640 screen.
      - *A bottom-of-screen bar was tried first on desktop and dropped: the user wants the buttons to stay in the sidebar.*
    - **Phones / tablets (below lg): a Save bar at the bottom of the screen** (`<x-admin.form-bar submit cancel>`, the last element of the form).
      - The user found Save stranded mid-page on a phone (the sidebar stacks under the form), with the Photos section after it.
      - The bar sticks to the bottom while the form is in view and lands at the very end of the form, under the Trash card.
      - The sidebar's Save / Cancel are hidden below lg, so there is one Save per screen size.
      - On the boat form the bar also shows the checklist counter (a link to the checklist).
      - Toasts sit above the bar (app.css). The admin `<html>` has `scroll-pb-24 lg:scroll-pb-0`, so Tab never hides a field behind it.
    - These forms: boat, harbor, experience, add-on, captain and cancellation policy. Settings (one column) and the short amenity form keep their own buttons.
    - **One Save for everything on the boat form: photos go up with "Save changes" / "Add boat".**
      - The Photos card moved inside the boat form (the last card of the main column; the form is `multipart/form-data`). Its picker is part of the save, so there is no separate Upload button, and photos can be added on **Add a boat** too (the first one is the cover).
      - `BoatRequest` validates them as the upload did: at most 10 per save, `SafeUpload::image()` at 25 MB, at most 30 per boat (checked before anything is saved).
      - `BoatController::store` / `update` add them after the save with `BoatPhotoService::add` (audit `boat.photos_added`). The flash says "Changes saved. 2 photos added." / "… was added with 1 photo.".
      - A failed save can't refill a file input, so the request flashes `photos_dropped`, and the Photos card says "The photo(s) you chose … were not uploaded … choose them again".
      - Each photo's own buttons (description, cover, move, delete) still act at once. They post small forms rendered after the boat form, through `form="photo-{uuid}-…"`.
      - The `POST /admin/boats/{boat}/photos` endpoint stays (tested); the screen no longer uses it.
      - **Blocked times moved above Save** (user request): it is the card after Photos in the boat form's main column. Its add / remove controls post the `block-add` / `block-{ulid}-delete` forms rendered after the boat form (`form="…"`). So its required fields never block Save changes, and its errors don't count as unsaved boat edits. On phones the order is Photos → Blocked times → checklist → On the site → Trash → Save bar.
    - **Unsaved changes guard** (`ui/admin.js`): when a record form has edits that aren't saved, leaving asks first ("Leave site?"). That covers a link, Cancel, a photo button, a block, Publish, Trash, or closing the tab. Saving never asks. A form that came back with errors counts as unsaved. Fields of other forms (`form="…"`) don't count.
    - **Live listing checklist** (`resources/js/pages/admin-boat-form.js`):
      - `BoatController::checklist()` items are keyed (description, highlights, specs, harbor, price, policy, photos, experiences, hours), and the JS has the same rule for each key over the form's fields. Each item turns green, and the counters change, as the admin types, ticks or picks.
      - `photos` = the saved photos + the ones chosen in the Photos card.
      - The Coast Guard warning (more than 6 guests, no COI) shows and hides live.
      - Prices count only as plain numbers, like the server's `numeric` rule ("$300" stays amber).
      - After a failed save, the list follows what was typed (old input), not the saved boat.
      - **Add a boat** now shows the same checklist instead of the "How to add a boat" card.
    - **Jump to the first error:** a failed save reloads the page at the top. Now:
      - Every error message carries `data-field-error`: the x-form components and the custom groups (trip lengths, experiences, new items, hours, gratuity, refund rules, the boat status box).
      - On load, `resources/js/ui/admin.js`:
        - scrolls the first error to the middle of the screen;
        - puts the cursor in its field (via `aria-describedby`, else the first control of its group);
        - shows a toast ("Please fix the highlighted field." / "N fields need a fix.").
      - It is skipped when a dialog reopens with its own errors.
    - **QA:**
      - 489 PHP tests (3,197 assertions), all passing. BoatListingTest:
        - a failed save shows the tagged error, with Save in the sticky column after the checklist;
        - the checklist items carry their keys and states;
        - the COI warning is hidden or shown;
        - Add shows 0 / 9.
      - 42 JS tests (+3: line count, plain numbers, rule keys = checklist keys).
      - Pint clean; build OK.
      - Browser (isolated server):
        - 8 forms × 390 / 768 / 1280 / 1440, with no overflow.
        - On lg+, scrolled to 35 / 70 / 98 % of the form, the column stays in view and Save is on screen and clickable. At the end it doesn't cover the Photos card. This also holds at 1366 × 640 and 1024 × 700.
        - On Add a boat, typing short + description, then 3 highlights, then specs, harbor, price, policy and an experience turned the items green one by one (1 → 8 / 9; hours start ticked).
        - 10 guests without a COI shows the warning, and ticking the COI hides it.
        - An empty hourly rate → the page reopens at the Pricing card with the cursor in the field. A missing boat name → the cursor in the name, and the checklist shows the typed price + policy (6 / 9) rather than the saved 4 / 9.
      - **Phone round (after the user's mobile report):**
        - 491 PHP tests (3,218 assertions), all passing. BoatListingTest:
          - the page order: Photos inside the form → sidebar Save → phone bar → per-photo forms → Blocked times;
          - Save changes / Add boat upload the chosen photos;
          - a bad file, 11 photos, and over 30 per boat are refused, with nothing saved;
          - a failed save asks to choose the photos again.
        - 43 JS tests (+ the photos rule).
        - Browser:
          - 7 forms × 390 / 768 / 1280 / 1440: on phones and tablets the bar is shown and the sidebar Save hidden (the other way round on desktop). Save is on screen at 20 / 50 / 85 % of the form, and from the top of the page at 1280, 1440, 1366 × 640 and 1024 × 700.
          - On a phone: chose 2 photos on The Azure → Save in the bar → "Changes saved. 2 photos added.", 7 photos.
          - Live: 3 chosen photos keep "4+ photos" amber, 4 turn it green (both counters 4 → 5).
          - A photo's "move down" still acts at once.
          - The guard: a clean page leaves quietly, a photo description (its own form) leaves quietly, a boat field edit asks.
      - **Blocked times move:**
        - 491 PHP tests, all passing; the page-order test now includes Blocked times and `block-add` before Save.
        - At 356 / 390 / 768 / 1280 / 1440, with no overflow: the order is Photos → Blocked times → checklist → Trash → Save bar. At the page end the bar is last, with Blocked times above it. Save is on screen and clickable while Blocked times is in view.
        - Save changes works with the block fields empty.
        - A block was added and removed from the new place.
        - A backwards block jumps to its error, which doesn't count as unsaved boat edits.

### Phase 4 — Booking engine (payments taken from 4.5, user 2026-11-26)
> **Client request (2026-11-24 / 25): the renter flow works like Boatsetter's, for Chicago, in our own design (D21, D22).** The research (search, boat page, booking card, phone sheet, checkout, fees, policies, Chicago) and the agreed changes are in [docs/research/boatsetter-renter-flow.md](research/boatsetter-renter-flow.md). Answers B1–B9 are in its §11. **Phase 4 now has 8 steps** (4.1–4.2 done; 4.3–4.8 below replace the old 4.3–4.7).

4.1 `QuoteService` (from `CYB.quote`, in cents) + unit tests: weekend, promo, gratuity, add-ons per guest, duration prices, rounding, deposit vs full. ✅ done 2026-11-23.
  - **Built:**
    - `App\Services\QuoteService::quote(QuoteRequest): Quote` and `durations(Boat)` (an experience argument until the 4.4 fixes).
    - Value objects in `app/Support/Pricing`:
      - `QuoteRequest`: the choices only, with `localStart(date, time)` in Chicago time → UTC.
      - `PromoTerms`: percent in bp or fixed in cents, `applies_to`, min subtotal, max discount.
      - `QuoteLine`: shaped like a `booking_items` row (type, key, label, description, quantity, unit and amount in cents, payee).
      - `Quote`: lines + adjustments (discount / tax / gratuity), totals, plan, deposit, balance due date, `pricingSnapshot`, `items()`, `toArray()`.
    - Enums `BookingItemType`, `Payee`, `PaymentPlan`, `PromoType`, `PromoAppliesTo`.
    - `QuoteException` is a `ValidationException`, so a choice that doesn't fit gives a 422 on its field: hours, guests, experience, addons or gratuity.
    - No migration: the booking and promo tables come with the steps that write them.
  - **Rules settled (§7.1 as applied):**
    - **Weekend:** Fri–Sun is decided by the trip start in **Chicago time** (Thursday 11:30 PM is a weekday although it is Friday in UTC). The weekend hourly rate is rounded to whole dollars, as in the mockup. The **boat's own weekend multiplier** wins over the site setting.
    - **Fixed price:** a boat's fixed price for a length replaces rate × hours. In 4.1 it is the same on every day. **User decision (2026-11-24): keep it flexible.** The admin can choose to add the weekend markup to a boat's fixed prices too (a per-boat switch in the boat form, read by QuoteService). It will be built with 4.2.
    - **Lengths offered:** the experience's lengths, narrowed to the boat's own when they overlap (no overlap → the experience's), never below the boat's minimum.
    - **Package fee:** the boat's override (pivot; $0 = no package line), else the experience fee. An experience the boat doesn't offer (not attached, pivot off, experience hidden) is refused.
    - **Add-ons:** flat ones once, per-guest ones × guests; an add-on chosen twice counts once; hidden or trashed ones are refused.
    - **Zero lines:** a $0 captain or fuel & cleaning line is left out.
    - **Promo:** a % of the charter or the subtotal, or a fixed amount, never more than its base or its cap. Below its minimum subtotal it gives $0 and a reason ("SUNSET50 needs a booking of at least $2,000."). Tax is on the subtotal after the discount.
    - **Gratuity:** % of charter + captain only. It must be 0 or one of the Settings options.
    - **Deposit:** the Settings deposit % of the total; the balance is due `balance_due_days` (7) before the start. A trip starting sooner can only be paid in full (exactly 7 days ahead still allows a deposit). *Removed in 4.3: full payment only (D22).*
    - **Payee per line (§7.5):**
      - house boats: everything to the platform, the gratuity to the captain;
      - owner boats: charter + fuel & cleaning to the owner;
      - when the owner drives (`captain_source = owner`): the captain line and the gratuity to the owner;
      - package, add-ons, tax and the discount: the platform. **Confirmed by the user (2026-11-24): promo codes are given by the admin, so the platform pays for them (owner earnings are on the full charter).**
    - **`pricingSnapshot`:** the rates used (hourly, weekend flag + multiplier, rate, fixed price, captain rate, crew, package, cleaning, tax %, gratuity %, deposit %, balance days, promo terms, ownership, captain source). Bookings store it; extensions (8.5) price from it.
  - Still on `DemoQuote` until their steps: the wizard and checkout (4.3 / 4.4), the yacht / pricing / experience page examples (4.3), My trips (4.6), admin bookings (4.7).
  - **QA:**
    - 506 PHP tests, all passing. New: Phase4/QuoteParityTest (1 test, 301 assertions) + Phase4/QuoteServiceTest (14).
    - **Parity with the mockup on 20 cases:** `tests/Fixtures/mockup-quotes.json` holds the answers of the mockup's own `CYB.quote()` (its `app.js` + `data.js` run in Node), covering 6 boats, 8 weekend days, add-ons, both promos, gratuity 0 / 15 / 18 / 20, and deposit and full payment.
      - Every price line, the hourly rate and the subtotal match **to the cent**.
      - The discount, tax and gratuity match to the dollar, and the totals within $2: the mockup rounds those to whole dollars, while checkout keeps cents.
      - One case is a mockup float artifact: 1650 × 1.15 = 1897.4999… in JS, so the mockup shows $1,897/hr for an exact $1,897.50 that rounds to $1,898. That case is noted in the fixture and answered by the same mockup code without the float noise.
4.2 `AvailabilityService`: rules, blocks, bookings + buffer, advance notice, weekdays and season, guest limits. Built around one "busy intervals" method for boats (and later captains), so trip offers and extensions (Phase 8) reuse it (§7.2). ✅ done 2026-11-24.
  - **User decision (2026-11-24): start times instead of opening hours (Boatsetter's way).**
    - A boat ticks the half-hours it can **depart** at each weekday, on a grid from 5:00 AM to 10:00 PM (`App\Support\StartTimes`). Only the start must be ticked.
    - A new boat starts with every day on, 8 AM – 6 PM (Boatsetter's default).
    - Experience start times must sit on the same grid (ExperienceRequest), so the two always meet.
  - **Migrations:**
    - `create_bookings_tables`: bookings (every §6.5 column, including the Phase 8 captain fields), booking_items, booking_status_history.
      - Created now because availability reads the trips already on a boat.
      - Foreign keys for `promo_code_id` and `booking_extension_id` come with their tables.
    - `switch_boat_hours_to_start_times`:
      - Converts every opening-hours row into start times every half hour from opening up to closing minus the boat's minimum trip (08:00–23:00 with 2 h → 08:00 … 21:00); a short day keeps its first start.
      - Drops opens_at / closes_at. Rollback rebuilds them (first start → last start + minimum).
      - Adds `boats.weekend_fixed_prices`.
      - Checked forward, back and forward on a temp DB with real rows.
  - **Models:**
    - `Booking` (ulid in URLs, a `reference` like CYB-7K3P9Q without 0 / O / 1 / I / L, the `takingTime()` scope), `BookingItem`, `BookingStatusHistory`. Only the customer's own words are fillable.
    - Enums `BookingStatus` (hold, requested, confirmed, completed, cancelled, expired, declined, no_show; `requested` = request to book for owner boats) and `PaymentStatus`.
    - `BookingFactory` with `at()`, `hold()`, `requested()`, `cancelled()`.
    - `Boat::offering(Experience)` is shared by QuoteService and AvailabilityService.
  - **AvailabilityService:**
    - `slots(boat, date, hours, experience, guests)`: each start time, free or taken with a reason (notice / booked / blocked / one_a_day).
    - `calendar(boat, "YYYY-MM", …)`: past / unavailable / soldout / limited / available per day, in 4 queries for the whole month.
    - `problem()` / `ensureFree()`: one exact trip, giving a 422 `AvailabilityException` on time, guests, hours, experience or boat. For holds in 4.4, inside the boat-row lock; `ignore:` re-checks a trip's own hold.
    - `busyIntervals()`: trips widened by the boat's buffer, plus blocks (the boat's and fleet-wide). It is the one source of busy time.
    - What counts:
      - A trip takes the boat's time while confirmed / completed / no-show, or while a hold / request hasn't run out. Cancelled, expired and declined free it.
      - A boat that takes one trip a day is busy all day once a trip starts that day.
      - Notice and the past are checked on every slot.
      - Chicago time throughout, so DST moves the UTC time, not the local start.
  - **Admin:**
    - The boat form's **Start times** card (one line per weekday): an on / off toggle, a live summary ("8:00 AM – 6:00 PM", "Closed"), and "Edit times" opening the 35 half-hour boxes.
    - "Every day 8 AM – 6 PM" and "Copy …'s times to every day".
    - A saved boat with no row shows its days closed.
    - The live checklist item became "Start times".
    - The audit log keeps "08:00–18:00 (21)" per day.
    - Pricing has **"Add the weekend rate to fixed prices too"** (user decision 2026-11-24): QuoteService then puts the weekend multiplier on a fixed price (whole dollars) on Fri–Sun, with "· weekend rate" in its line and `weekend_fixed_prices` in the snapshot.
  - **Booking records protect what they point at** (PLAN §8.5):
    - a boat can't go to the Trash with upcoming trips;
    - boats, experiences, add-ons and captains with bookings or booking lines can't be deleted for good.
  - The client's boat seeder now gives 08:00–21:00 every day. Demo boats have no start times (closed; they carry the Unavailable tag).
  - **QA:**
    - 528 PHP tests, all passing. New:
      - Phase4/AvailabilityServiceTest (12): start times in Chicago time, DST, experience times / days / season, notice, buffer, holds / requests / cancelled, blocks, one a day, whole-day rules, exact check + 422, calendar statuses + query count, busy intervals;
      - BookingRecordsTest (4);
      - Unit/StartTimesTest (3);
      - a weekend-on-fixed-price quote test;
      - the start-time form test (Add defaults, client boat, closed days keep their times, save);
      - the experience grid test;
      - the 422 for an open day without times and for off-grid times.
    - 44 JS tests (+1 summary / labels); Pint clean; build OK.
    - Browser (isolated server):
      - Add / the client's boat / The Azure at 390 / 768 / 1280 / 1440, with a day's grid open: no overflow.
      - On a demo boat, all days closed → preset (checklist green) → tick 7:30 PM (summary follows) → copy to every day → all off (amber). A save gave "Changes saved." with Saturday 8 AM – 8 PM and the weekend switch on.
  - **User runs:** `php artisan migrate`. The client boat's 08:00–23:00 becomes start times 08:00–21:00.
*Replaced 2026-11-25 (D21): the old 4.3 "wizard ⇄ endpoints" and the 4-step `/book` wizard are dropped. Booking starts on the boat page, like Boatsetter.*

4.3 **Boat page booking card** (D21). ✅ done 2026-11-25 (built together with 4.4: two steps per response from now on, user request).
  - **User decisions while building (2026-11-25):**
    - **Experiences are free** (Boatsetter doesn't charge for them). Each keeps its own start times (sunset ≠ fireworks: the card only offers the times the experience *and* the boat share). The admin can add **paid extras** to an experience (décor, a photographer, any facility): add-ons from Admin → Add-ons, charged on every booking of that experience.
    - **Direct checkout only (D22):** no deposit and no request to book, owner boats too.
  - **Migrations:**
    - `make_experiences_free_with_paid_extras`: the `addon_experience` pivot (sort); drops `experiences.package_fee_cents` and `boat_experience.package_fee_override_cents`. Rollback brings the columns back empty.
    - `switch_bookings_to_full_payment_only`: drops `bookings.payment_plan`, `balance_due_cents`, `balance_due_at` and the `deposit_bp` / `balance_due_days` settings.
    - Checked forward, back and forward on a temp SQLite DB with an extra link and an old deposit setting.
  - **Pricing:**
    - `QuoteService`: no deposit / plan / balance (`Quote` and `QuoteRequest` lost them, the `PaymentPlan` enum is gone). The experience's paid extras are `package` lines keyed `extra-{slug}` ("With Sunset Skyline Cruise"; per guest × guests); hidden or trashed add-ons are skipped; an add-on the guest picks that the experience already includes counts once. The snapshot keeps `experience_extras`.
    - `BookingStatus` lost `requested` / `declined`; `PaymentStatus` lost `deposit_paid`.
    - Settings lost Deposit and Balance days.
    - The parity test sets the mockup's package fees up as one paid extra each, so all 20 cases still match line by line (deposit answers no longer compared).
  - **JSON endpoints** (§8.2, read-only GETs, `BookingCardController`, API Resources):
    - `GET /yachts/{slug}/availability` (`throttle:availability`, 120/min): `?month=YYYY-MM` → each day's status (past / unavailable / soldout / limited / available); `?date=YYYY-MM-DD` → that day's start times, each free or taken with a short reason (Booked, Too soon, Not available, Booked that day). Never another trip's details.
    - `GET /yachts/{slug}/quote` (`throttle:quote`, 60/min): every line, tax, total; without a date a weekday estimate; with a start time "Free cancellation until …" from the boat's policy (`CancellationPolicy::freeCancellationHours()`).
    - FormRequests `BookingCard\AvailabilityRequest` / `PriceRequest` (months whitelisted, dates today … +18 months, times on the start-time grid, add-ons `exists` + `distinct` + max 10). Choices that don't fit the boat → 422 on their field; a draft / hidden / trashed boat → 404.
    - `App\Services\BoatQuotes`: published boats by slug with what the two services read, once per request (injected per call, never into a controller constructor); `startingAt()`, `weekday()`, `freeCancellationUntil()`.
  - **The card** (our design; `resources/js/pages/yacht.js`):
    - Experience (optional, "Just the boat") with its days, start times and extras; Date / Duration rows and a Start time row opening panels: a month calendar (prev / next inside 18 months, booked days struck through, few-left dot), length chips, the day's start-time grid. Picking a date opens the start times next, as on Boatsetter.
    - Guests stepper, "Captained" line (the No-captain switch waits for owner boats that allow it, Phase 6), every price line + total from the quote endpoint (the first one rendered by the server), "Book now" (asks for a date / time first), "Free cancellation until …", "You won't be charged yet".
    - Phone: the bottom bar shows the live total and opens the card as a full-screen sheet (close button, Escape); `#book` opens it from links.
    - "Book now" goes to checkout (still the Phase 1 demo until 4.5, now experience-optional, full payment, no plan choice).
  - **Boat page:** quick-facts row (captain, instant confirmation, cancellation policy, harbor), "Things to know" (Included / On board from the boat's rules / its cancellation policy points), the price example from QuoteService. `?experience=&date=&hours=&guests=` preset the card (checked against the boat).
  - `/book` (old links, home search) redirects: with a boat to its page `…#book`, otherwise to the fleet with the same filters. The wizard view, `booking.js` and `demo-availability.js` are deleted; generic "Book" links point at the fleet; `/book` left the sitemap.
  - The pricing page's examples, "starting at" column and estimator use QuoteService (the estimator asks the quote endpoint); the experiences page "Trips from" too. Deposit copy removed everywhere (home, pricing, FAQ, terms, cancellation policy, checkout, confirmation).
  - Admin: Experiences have a **Paid extras** card (tick add-ons, audit-logged by name; the list shows them or "Free"); the boat form's per-experience fee box is gone.
4.4 **Fleet + Experiences** (D21). ✅ done 2026-11-25.
  - Fleet filter bar (`App\Support\FleetFilters`, every value whitelisted, never echoed): date, guests, experience, trip length, More filters (harbor, price range, boat length, "On board" amenities from the catalog cache), type chips, sort. Same rules in `resources/js/pages/yachts.js` for instant filtering; a date submits the form, and only boats with a free start that day show, with a "Free on Dec 7 · 23 start times" pill. Sticky from lg up.
  - Cards: lengths ("3–4 hrs · Captained"), "From $X/hr", the free-date pill; "Book" opens the boat page's card with the search (date, guests, experience, hours). No Instant-book badge or chip: every booking is instant (D22). No map.
  - Experiences page: Chicago themes by category; each shows whether it is free or which paid extras it comes with, the boats that offer it (each opens its card with the experience picked), "Trips from" (QuoteService), and "Book this experience" (straight to the boat when only one can take it, else the fleet filtered).
  - **QA:**
    - 543 PHP tests, all passing (16 new: Phase4/BookingCardTest 10 — card data, presets, redirects, month / day, prices with extras and add-ons, 422s, 404s, Unavailable, 429; Phase4/FleetAndExperiencesTest 6), plus updated Quote / parity / availability / admin / page tests. 45 JS tests (+ months). Pint clean, build OK.
    - Browser on an isolated server: no overflow on 8 pages at 390 / 768 / 1280 / 1440; desktop flow Book without a date → calendar → Dec 1 → 23 start times → 8 AM → price + "Free cancellation until …" → Sunset (4 times) → 5:30 PM → checkout with the choices; phone sheet open / pick / Escape; fleet instant filters, More filters, a date (2 free boats with pills); estimator weekday / weekend / add-on.
  - **User runs:** `php artisan migrate` (both 4.3 migrations; the seeded package fees go — experiences become free; add paid extras in Admin → Experiences if wanted).
  - **Fixes after the user's QA (2026-11-25, user decisions):**
    - **Bug:** an experience picked on the fleet (or reached through "Book this experience") was lost on the way to the boat page — the card's `['experience' => null] + $query` kept the null. Every card link (photo, name, arrow, Book) now carries the search, and the fleet's instant filters rewrite the links (`data-yacht-link`).
    - **No experience length:** `experiences.durations` dropped (migration `experiences_use_departure_windows_and_the_boats_lengths`); `QuoteService::durations(Boat)` = the boat's ticked lengths, or every standard length from its minimum up to 8 hours (`DEFAULT_MAX_HOURS`). The experiences page shows Departs / Available / "Trip length: your choice".
    - **Departure windows:** the admin sets First / Last departure (grid selects) instead of a list; `time_slots` keeps every half hour of it (the migration fills old gaps). Seeds: sunset 5:00–7:30 PM, Playpen 10 AM–3 PM, river 9 AM–4 PM, fireworks 8–9 PM Wed & Sat, birthdays 10 AM–8 PM, corporate 9 AM–6 PM, proposals 4:30–7:30 PM, private 8 AM–6 PM. The card's experience options show their window; picking one keeps a date / time that fits and otherwise clears it with a notice ("Sunset Skyline Cruise isn't available at 10:00 AM: it departs 5:00 PM – 7:30 PM…", "…runs on Wed & Sat only"); its start-time panel says which window it shows. `AvailabilityService::problem()` gives the same messages (`outsideWindow()`), and a boat whose start times never meet the window says so.
    - **Optional extras:** QuoteService no longer adds an experience's extras; they are add-ons the guest ticks. The demo checkout got a "Make it yours" card: every add-on as an optional tile, the experience's suggestions first ("Suggested for …"), none ticked; totals update as they are ticked. A pricing package / the estimator carry their add-ons (`?addons=`) through the card to checkout, where they can be unticked. Checkout without a fitting start time goes back to the boat's card with a message instead of a sample booking.
    - `http.js` sends an array param as `key[]` (the card's add-ons).
    - **Sticky panels fit the screen (user's QA 2026-11-26):** the booking card, taller than a laptop screen, scrolled the page instead of itself and its Book now was out of reach. `resources/js/ui/sticky-panels.js` (from the admin form-aside's fit, now shared): every `data-sticky-panel` gets a max height = the screen space below its top and scrolls on its own (the wheel over it scrolls it, then the page). Used by the boat card, checkout summary, trip side column, owner calendar day panel, legal contents, pricing estimator, admin form-aside; the home page's occasions block uses `release` (stops sticking when it doesn't fit). The card scrolls an opened panel into view.
    - **No visible inner scrollbars (user, 2026-11-26):** every inner scroller uses `no-scrollbar` (sticky panels, the phone booking sheet, the mobile menu, tables, chip rows); the page's own scrollbar stays.
    - The catalog cache key is versioned (`PublicCatalog::CACHE_KEY`, now `catalog.public.v2`): a deploy that changes the payload's shape never reads an old one (the card showed "departs null" until the cache expired). "1 hours" → "1 hour".
    - **User runs:** `php artisan migrate` (1 new migration).
4.5 **Hold + checkout, paid in full.** ✅ done 2026-11-26 (built with 4.6). (Request to book dropped, D22.) **User decision 2026-11-26: the payment is received at checkout now** — the full amount, no deposit; Stripe's core (5.1 / 5.2) came forward.
  - **Migration** `create_payments_and_promo_codes_tables`: `promo_codes` (value: percent in bp, fixed in cents; dates, total and per-guest limits, soft deletes), `promo_redemptions` (one per booking), `payments` (one per attempt: provider, intent id, amount, status, card brand + last 4 only, idempotency key), `webhook_events` (unique event id), `bookings.hold_key` (unique) + the `promo_code_id` foreign key. The mockup's WELCOME10 / SUNSET50 are seeded (CatalogSeeder). Checked forward, back, forward.
  - **Flow:** the boat card's "Book now" → signed in: `POST /checkout` holds the trip right away; a guest: `GET /checkout` (the trip + its QuoteService price, with **inline log in / create account** — Fortify's own endpoints; `RegisterResponse` now goes back to `url.intended`, so the guest returns to the trip; email verification can wait) → "Continue to payment" holds it → `GET /checkout/{booking}` → Pay → card → confirmation (4.6).
  - **Hold** (`HoldTrip`): inside a transaction that **locks the boat row** (the double-booking lock), AvailabilityService checks the trip again; the booking (reference, contact from the account, policy snapshot, QuoteService lines with the default gratuity) is held for Settings → `hold_minutes` (15). The form's idempotency key is the booking's `hold_key` (+ the `idempotent` middleware): a double click makes one hold. One hold per guest: a new one lets go of their older open holds. A taken time / wrong choice goes back to the boat card with the reason.
  - **Checkout page** (the mockup's design): lead guest (prefilled), trip details (occasion, how they heard, a note to the captain), **Make it yours** (every add-on optional, the experience's suggestions first, unticked), gratuity tiles (amounts from QuoteService), promo code (applied on the server, `throttle:promo`), the card, terms, the summary with every line, a hold timer counting to the server's expiry. The live price is `GET /checkout/{booking}/price` (QuoteService, read-only). An ended hold shows "This hold has ended" with a way back.
  - **Pay** (`PrepareCheckoutPayment`, `POST …/pay`, idempotent): locks the booking; the hold must be open; the trip is checked again; the final choices are priced and saved; one open payment per booking (re-used after a decline, its amount updated). Never a posted price.
  - **Payments** (`App\Services\Payments\PaymentGateway`): `StripeGateway` (PaymentIntent for the total with an idempotency key, Payment Element in the browser with a deferred intent, 3-D Secure by Stripe, card data only in Stripe's frames, CSP allows js.stripe.com / hooks.stripe.com / api.stripe.com with the Stripe driver only) and `FakeGateway` (local / tests without keys: a test card form sending only brand + last 4; 4000 0000 0000 0002 declines; refused in production). `config/payments.php`: Stripe when `STRIPE_SECRET` is set.
  - **Confirmation** (`ConfirmPaidBooking`, idempotent): from the signed **webhook** (`POST /stripe/webhook`, no CSRF, events stored once, a failed one retried) or the return page / status poll asking Stripe. Confirms the hold (paid in full, history line, promo redemption) and, after the commit, emails the guest (`BookingConfirmed`, .ics attached) and the business (`NewBookingReceived`). A payment that lands after the hold ran out confirms if the time is still free; otherwise the booking stays expired, marked paid, with an admin note to refund (refunds: Phase 5).
  - **Hold expiry:** `bookings:expire-holds` every minute (scheduler): expired + history + open intents cancelled.
  - The Phase 1 demo checkout (`BookingSelection`, `CheckoutRequest`, the promo demo endpoint, `/booking/confirmation`) is gone. DemoQuote stays for the screens still on demo data (My trips 4.7, admin bookings 4.8).
4.6 **Confirmation page + email + .ics.** ✅ done 2026-11-26.
  - `GET /bookings/{booking}/confirmation` (the guest's own, else 404; a hold goes back to its checkout): the mockup's page with the booking's data — reference (copy), date / time / guests / length, occasion and add-ons, every line, paid in full + card brand and last 4, what happens next, the meeting point map.
  - `GET /bookings/{booking}/calendar.ics` and the email attachment: `App\Support\Calendar\TripCalendar` (RFC 5545: UTC times, escaped text, lines folded at 75 octets, CRLF).
  - **QA (4.5 + 4.6):** see the report; tests in Phase4/CheckoutTest, StripeWebhookTest, TripCalendarTest.
  - **User runs:** `php artisan migrate` (1 migration) and `php artisan db:seed --class=CatalogSeeder` for the two promo codes (it skips what exists). For real payments: Stripe test keys in `.env` (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`; locally `stripe listen --forward-to localhost:8000/stripe/webhook`). Without keys the fake gateway's test card works locally.
4.7 **My trips + trip detail.** ✅ done 2026-11-26 (built with 4.8).
  - `Account\TripController` reads the guest's own bookings (BookingPolicy: anyone else's → 404). My trips: upcoming (confirmed, and a hold that can still be paid → "Finish checkout") soonest first, then past & cancelled; holds that ran out aren't trips. The Phase 1 design, real data.
  - Trip page: the trip, departure + map, captain (or "we're assigning one"), every price line, paid with card brand + last 4, refunds, Receipt (Phase 5.5), Add to calendar (the server's .ics), the cancellation policy **from the booking's snapshot** with the refund it gives now and when it drops next (`App\Support\Booking\RefundPreview`; the gratuity always comes back). Until online cancellation (Phase 5) and the problem form (Phase 7) exist, those boxes tell the guest to call / email with the reference (the demo dialog and form, which sent nothing, are gone). The report box shows during Settings → `complaint_window_hours` after the trip.
4.8 **Admin: Bookings + promo codes.** ✅ done 2026-11-26.
  - **Bookings list** (`Admin\BookingController`): search by reference, guest name / email or boat; whitelisted filters (status, upcoming / past, house / owner boat, captain or not) and sort (date, total, guests, reference, booked); pages. Expired holds only when asked for.
  - **Calendar** (`/admin/bookings/calendar?month=`): a month by Chicago day — held, confirmed and past trips; a 7-day grid on large screens, the days with trips as a list on smaller ones.
  - **Booking page** (by its reference): trip, the guest's occasion and note, contact (admin only) + their account, every price line with its payee, payments (provider, card, status, failure), the promo code, the status history, a red note when a payment came in after the time was taken (refund by hand until Phase 5).
  - **Notes** (`PUT …/notes`, `throttle:admin`): an internal note and a note for the captain (plain text), only what changed audit-logged.
  - **Status** (`POST …/status`, a reason + the password, `throttle:admin-sensitive`, audit log + history): only what `BookingService::adminTargets()` allows now — let a hold go (its open payment is cancelled), Completed once the trip ended, No-show once it started. Cancel / refund: Phase 5; captain assignment: Phase 8.
  - **Promo codes** (the 3.4 record screens, `manage-catalog`): list (state: Active / Scheduled / Ended / Used up / Hidden, uses / limit), add / edit (percent or dollars, charter or whole booking, minimum, cap for percentages, first / last Chicago day, total and per-guest limits, on / off), Trash (the code stops working at checkout), restore, delete permanently only when never used. The demo screen and `SystemController` are gone.
  - **QA:** see the report; tests in Phase4/MyTripsTest, AdminBookingsTest, AdminPromoCodesTest.
  - Still demo after Phase 4: the admin dashboard's numbers, listings, inbox, finance (payouts, payments, complaints), the owner area and saved cards — their phases replace them.
**QA:** price parity with the mockup on the remaining (full-payment) cases; concurrency test (two holds on one slot); hold expiry; IDOR tests; §8.2 endpoint test set; boat page / fleet at 390 / 768 / 1280 / 1440 with no overflow.

### Phase 5 — Payments + cancellations (Stripe test mode)
5.1 ~~Payment gateway + Stripe implementation; Payment Element (full amount only, D22); 3-D Secure.~~ *Done in 4.5 (user 2026-11-26).* Left for 5.1: a Stripe Customer per guest (needed for saved cards).
5.2 ~~Webhook endpoint + `webhook_events` + idempotent handlers.~~ *Done in 4.5.* Left: more event types (refunds, disputes) as 5.4 – 5.5 need them.
5.3 Saved cards page. *(No automatic balance charge: there is no deposit, D22.)*
5.4 `CancellationPolicyService` (Boatsetter-style, §7.4) with a refund preview; customer cancel; admin custom / weather / owner-cancel refunds; dispute records.
5.5 Receipt PDF; admin Payments and Refunds screens; nightly reconcile.
**QA:** Stripe test-card matrix (success, decline, 3-D Secure, insufficient funds); webhook replay and duplicates; refund math tests for every policy window.

### Phase 6 — Owners: List Your Boat (real)
> **Client request (2026-11-24): List Your Boat should work exactly like Boatsetter's.** The field-by-field map of Boatsetter's flow (start page with address + year / make / model, conditional location fields, start-time availability, per-duration pricing after listing, owner add-ons, manage tabs) and the comparison with our wizard are in [docs/research/boatsetter-list-your-boat.md](research/boatsetter-list-your-boat.md). Its §6 lists the client decisions needed before Phase 6.

**Answered 2026-11-24** (details in §6 of that file):
- **Like Boatsetter in fields and logic** (our own wording).
- **Per-duration package pricing** with a Suggested ⇄ Custom toggle, set after listing.
- **Buoy-style insurance questions** (the insurer is arranged later).
- **Owner add-ons per boat.**
- ~~Request to book, with the owner's approval.~~ *Changed 2026-11-25 (D22): every booking is a direct checkout, owner boats too.*
- **No paid APIs:**
  - a large make list + free-text model;
  - Chicago-only harbor / marina list instead of Google address autocomplete;
  - a rule-based earnings estimate.

Still open: **start-time slots vs opening hours** (affects 4.2). Steps 6.1–6.5 will be rewritten from these decisions when Phase 6 starts.

6.1 List Your Boat → auth → owner profile + role, set automatically.
6.2 Wizard JS ⇄ `PATCH` per step (FormRequest per step): photos (min 4), insurance + COI (private), **Pricing step**, the 32/33 ft rules, resume, save & exit, final re-validation.
6.3 Admin review queue (approve / request changes / reject) + emails; on approval the admin confirms pricing, commission and experiences → publish.
6.4 My boats + owner dashboard with real data; owner calendar (blocks); owner bookings.
6.5 Edits after publishing → re-approval; insurance-expiry job.
**QA:** validation tests per step; owner A/B isolation; step-skipping attack test; upload tests; full flow from draft to published to booked.

### Phase 7 — Owner finance (72 h, complaints, withdrawals)
7.1 Stripe Connect Express onboarding + `account.updated` webhook; payout status in the dashboard.
7.2 `LedgerService`: entries from booking events; commission (global + per owner); refund reversals.
7.3 `ReleaseEarnings` job (trip end + 72 h, no open complaint).
7.4 Complaints: customer form (72 h window), owner response, admin resolution (no action / deduction / refund), earnings hold and release.
7.5 Withdrawals: owner request (minimum, 2FA, password confirm); admin approve / reject / hold / **pause owner** / adjustments; Stripe transfer; history; emails.
7.6 Tax forms (1099) via the Stripe Express dashboard link.
**QA:** ledger invariants (balance = sum of entries; available never includes held money); 72 h boundary tests; complaint → deduction → negative balance recovery; permission tests; audit entries for every money action.

### Phase 8 — Captains: portal, trip offers, extensions (rewritten 2026-11-23, D19 + D20)
8.1 **Captain accounts + dashboard:** `captain` role; `captains.user_id` + application columns; apply form (Turnstile, license document on the private disk) and admin approval / rejection; admin invite (signed link → set password); `/captain` layout in the owner dashboard's design; profile (license changes go back for review); availability (days off → `captain_unavailability`); license-expiry alerts; the bell (database notifications).
8.2 **Trip offers + assignment (§7.8):** eligibility checks; "Request captains" (all eligible / selected, deadline) → `captain_trip_offers`; accept with the booking-row lock (**the first to accept gets the trip**), decline, filled, withdrawn, expired (`ExpireCaptainOffers`); direct assignment + **Acknowledge** + reminder (`RemindUnacknowledgedTrips`); release up to 48 h before; reassignment.
8.3 **Notifications:** dashboard + bell + email (+ .ics) for offers, assignments, changes, cancellations and extensions (reference only, no customer name in emails, R6); captain trip reminder T-24h; captain card on the customer trip page and in the reminder email; admin alerts and the action list (no captain, offers expired, not acknowledged, applications).
8.4 **Captain pay:** ledger (pay rate × hours + gratuity, extensions included), earnings page, captain payouts (manual).
8.5 **Trip extensions (§7.9):** "Extend your trip" card; options from `AvailabilityService` (boat + captain); extension hold + lock; Stripe payment (saved card one tap); webhook confirms → booking `ends_at`, extension lines, ledger; captain / owner / admin / customer told; admin refund only.
**QA:** offer race (two captains accept at the same moment → exactly one wins); eligibility tests (overlap + turnaround, expired license, passenger limit, unavailability, not approved, suspended); captain isolation (another captain's offer or trip → 404; a declined / lost / released trip → 404); no customer contact data in captain pages, JSON or emails; release cut-off; acknowledge reminder; extension tests: cut-off, whole hours only (a half hour is refused with 422), next booking + buffer, captain's next trip, closing time, longest trip, two customers on the same free time, hold expiry, webhook replay, ledger entries; the §8.2 endpoint test set for every new endpoint.

### Phase 9 — Communication
9.1 Contact form end to end: §8.2 rules + Turnstile + honeypot + throttle; stored; email to the business inbox + auto-reply; admin inbox with replies.
9.2 Newsletter (footer form): double opt-in, unsubscribe.
9.3 The full branded email set (§11), queued; in-app notifications (bell).
**QA:** email rendering and content tests; spam / abuse tests; queue failure and retry handling.

### Phase 10 — CMS, SEO and reports (minimal before launch, more after)
10.1 Admin-editable content: hero, home sections, FAQs, testimonials, gallery, legal pages, business info.
10.2 SEO: meta / Open Graph, schema.org (LocalBusiness, Product / Offer), sitemap, robots.
10.3 Dashboard reports + CSV exports + audit log viewer.

### Phase 11 — Hardening and launch on Namecheap
11.1 Security review (OWASP ASVS checklist), `composer audit` / `npm audit`, fixes.
11.2 Performance: N+1 checks, caching, image sizes; Lighthouse ≥ 90.
11.3 Backups: cPanel backups + a scheduled DB dump + a `storage/` archive, copied off-site; a restore drill.
11.4 Namecheap setup:
  - PHP version + extensions (GD/Imagick, exif, intl, bcmath, fileinfo, pdo_mysql, mbstring, openssl); `upload_max_filesize` ≥ 25M, `post_max_size` ≥ 30M, `memory_limit` ≥ 256M (§9).
  - Document root → `/public`; `storage:link` (if the host blocks PHP's `symlink()`, `ln -s` over SSH); check that `/storage/.htaccess` is served as 403; one cron job (`schedule:run`); free SSL.
  - Business mailbox SMTP + SPF / DKIM / DMARC; deploy steps (git or upload + `composer install --no-dev` + migrate + caches).
11.5 Go-live: Stripe live keys + webhook URL; legal pages reviewed; final UAT with the client.

---

## 15. QA strategy and definition of done

A step is **done** only when all of these are true:

1. Tests for the step exist and pass (`php artisan test`).
2. Laravel Pint passes.
3. There are no console errors or CSP violations, and no horizontal overflow at 390 / 768 / 1280 / 1440 px (headless-Chrome checks).
4. Every new form and endpoint passes the **§8.2 checklist**, including its test set.
5. Migrations roll back cleanly; seeders and factories are updated.
6. A manual test checklist has been run and noted.
7. The code has been reviewed and the result reported to you, and you approve before the next step.
8. This plan is updated if anything changed.

**Standing test suites**

- The route × role access matrix.
- IDOR tests (one user can never read or change another user's data).
- Money tests (quotes, refund windows, ledger invariants).
- A double-booking concurrency test.
- A faked Stripe for unit and feature tests; real Stripe test mode with `stripe listen` for integration checks.

---

## 16. Environments and hosting

| Env | Purpose | Notes |
|---|---|---|
| Local | Development | XAMPP MySQL, `php artisan serve`, log mail, Stripe CLI for webhooks |
| Staging | Client previews | A subdomain on Namecheap (e.g. `staging.`), Stripe **test** keys, password-protected |
| Production | Live | Namecheap, HTTPS, cron scheduler, daily backups |

**Namecheap (R1).** Source: Namecheap knowledge base, checked 2026-11-21.

| | Stellar | Stellar Plus | **Stellar Business (recommended)** | VPS (Pulsar / Quasar) |
|---|---|---|---|---|
| PHP versions | 5.2–8.5 | 5.2–8.5 | 7.0–8.5 (LiteSpeed, "PHP Tweaks") | Any (you install it) |
| Websites / subdomains | 3 / 30 | Unlimited | Unlimited | Unlimited |
| Storage | 20 GB SSD | Unmetered SSD | 50 GB SSD | 40 / 120 GB SSD |
| Mailboxes / DBs | 30 / 50 | Unlimited | Unlimited | Your setup |
| **Outgoing email per hour per domain** | **50** | **200** | **10,000** | Your mail server |
| Backups | Manual | AutoBackup | AutoBackup + cloud storage | You set them up |
| CPU | Base | Base | ~2× burst vs Plus | 2–4 dedicated cores |

- **Why Stellar Business:**
  - Booking confirmations, reminders, captain emails, contact replies and payout emails easily go past Stellar's 50/hour and can hit Plus's 200/hour on busy days.
  - It has more CPU, AutoBackup and a staging subdomain.
  - It runs on LiteSpeed, which works with Laravel's `.htaccess`.
- **Stellar Plus** is the budget alternative. It can work at the start if email volume stays low.
- **Stellar** is not suitable: 50 emails/hour and manual-only backups.
- **VPS** is the upgrade path when traffic grows. It gives full control (Supervisor, firewall), but we would have to manage OS updates and server security ourselves, and cPanel is an extra license.
- **PHP:** pin `config.platform.php` to **8.4.1** (the minimum Symfony 8.1 needs), so every package works on PHP 8.4.1+ and 8.5. In cPanel, select PHP 8.4 (or 8.5) and enable these extensions: gd (or imagick), intl, bcmath, fileinfo, mbstring, openssl, pdo_mysql, zip, exif, sodium, curl, dom/xml, tokenizer, ctype. Check them with `phpinfo()` after the purchase. Namecheap's module table only covers PHP ≤ 8.1.
- **Database:** MariaDB 11.4 on the server. Use Laravel's `mariadb` driver in production, and avoid MySQL-only SQL.
- **SSH:** available on request (jailshell) for `composer install` and `artisan`. Composer isn't pre-installed according to the docs, so we can upload `composer.phar`. Git support is unclear in the docs; we confirm it at deploy time.
- **Not needed on the server:** Node. Assets are built locally and `public/build` is uploaded.
- **Timezone:** the server timezone is America/New_York. This doesn't affect us, because the app stores UTC and shows Chicago time.

The static mockup can stay on Vercel for client demos until Phase 1 is done.

---

## 17. Scope summary

**Added beyond the brief (in v1):**

- **Accounts and access:**
  - One account model with simple roles.
  - 2FA for everyone (the admin's choice, R10) and required for owner money actions.
  - Policies + 404-on-foreign-record.
  - ULIDs in URLs.
  - An access-matrix test suite.
- **Security:** the §8.2 security standard for every form and JSON endpoint, plus a strict CSP, idempotency keys and rate limits.
- **Booking safety:** booking holds, a double-booking lock, snapshots and status history.
- **Payments:** webhook-driven payment confirmation, nightly reconciliation and dispute records.
- **Owner money:**
  - Ledger-based owner earnings with the 72-hour release.
  - **Customer complaints → owner deductions.**
  - Payout pause and hold.
  - Stripe Connect handles owner bank details, KYC and 1099s.
- **Refunds:** Boatsetter-style cancellation policies stored as editable rules, with a refund preview and an admin override.
- **Captains:** a captain portal (apply / invite + approval), trip offers where the first to accept gets the trip, acknowledgement of assignments, assignment emails (+ .ics), USCG passenger limits tied to the license type, and license and insurance expiry checks.
- **Trip extensions:** extra time bought during the trip, checked against the boat's and the captain's schedule.
- **Listings:** re-approval of risky edits on published owner boats.
- **Files:** private storage for documents, image re-encoding, and backups that include `storage/`.
- **Records:** an audit log, a login log, and account deletion with anonymization.

**Later (after v1):**

- Reviews and ratings. Until then, the admin sets the rating and review count shown on each boat.
- Liability waiver e-signature and security-deposit holds.
- Customer ↔ owner messaging, and SMS (also for captain offers and trip extensions).
- Gift cards and customer credits.
- Adding hours before the trip from the customer's side (the admin does it for now).
- Staff roles (manager, support) with permissions.
- Automatic scheduled payouts.
- Multi-language and analytics.
