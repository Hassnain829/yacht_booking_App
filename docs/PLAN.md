# Chicago Yacht Booking — Platform Plan

**Status:** v0.3 (2026-11-21), with the user's decisions applied. R2–R7 are confirmed; R1 has a recommendation (Stellar Business). Nothing has been built yet.
**Design source:** `C:\Users\DETRIOTS\Desktop\Yacht-Web`, a static HTML/Tailwind/JS mockup with 13 pages.
**Target:** this Laravel 13 app (`D:\Yacht_laravel\Yacht-Booking`).

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
| D2 | Role-based access: **customer**, **owner** and **admin**. One admin for now; staff/manager roles later. |
| D3 | **Custom admin dashboard**, built from scratch with Laravel defaults (no Filament). |
| D4 | Interactive flows use **vanilla JS (ES modules via Vite) + JSON endpoints**. No Livewire/Alpine. The endpoints must be hardened (§8.2). |
| D5 | Auth: **Laravel Fortify** with our own Blade screens. |
| D6 | **Checkout needs an account**, created inline on the checkout page. |
| D7 | **Owners set their own prices** in the wizard (a new Pricing step); the admin can adjust them before approval. |
| D8 | **Platform commission** on owner boats is configurable: a global % plus a per-owner override. Placeholder 20%; the client gives the final figure. |
| D9 | **Owner payouts:** earnings become withdrawable **72 hours after the trip ends**. The owner requests a withdrawal → the admin approves → Stripe Connect transfer. If a customer complains about the boat, the admin can **deduct** from the owner's earnings. The admin can also pause an owner's payouts or hold one payment. |
| D10 | **Captains:** the customer sees a fixed "Captain & crew" fee at checkout. The admin assigns the captain later, and **the captain gets an email**. The captain's email (provided by the captain) is stored on the captain profile and shown in the assignment form. **No captain login for now.** |
| D11 | The admin adds, edits and offers experiences. Boats the admin adds can go live directly; owner boats need approval. |
| D12 | Boats that can't be booked show an **Unavailable** tag. |
| D13 | **Refunds follow Boatsetter-style policies** (Flexible / Moderate / Strict), based on hours before the trip. The rules can be edited, and the admin can always issue a custom refund (§7.4). |
| D14 | The contact form stores each message and sends emails from the **business email on Namecheap**. |
| D15 | Files are stored on disk in the project (`storage/`). **The DB stores only the path.** |
| D16 | **Hosting: Namecheap.** The plan type is still open (R1). |
| D17 | **Laravel Boost (dev-only) is added just before Phase 2** (decided 2026-11-21) for version-correct docs search, DB schema/query, logs and browser-logs tools. Its guidelines live only inside the `<laravel-boost-guidelines>` block; our `CLAUDE.md` rules outside that block stay the source of truth. Phase 0–1 run without it. |
| D18 | Optional features (reviews, waiver, deposit hold, messaging, SMS, gift cards, captain portal, staff roles) come **later**. |

### 2.2 Small confirmations

| # | Question | Answer |
|---|---|---|
| R1 | Which Namecheap plan, and which PHP version? | **Recommended: Stellar Business** (shared, PHP 8.0–8.5 available). Pin Composer to **PHP 8.4** (§16). The user is deciding. |
| R2 | How are the Moderate/Strict windows counted? | ✅ In whole days, converted to hours. Moderate: ≥120h 100%, 72–120h 50%, <72h 0%. Strict: ≥720h 100%, 360–720h 50%, <360h 0% (§7.4). |
| R3 | Minimum withdrawal amount? | ✅ $50 (stored as a setting). |
| R4 | How long after the trip can a customer complain? | ✅ 72 hours, the same as the payout delay. |
| R5 | What happens when an owner cancels a confirmed trip? | ✅ The customer gets a full refund, and **the admin can deduct a penalty** from the owner. Nothing is deducted automatically. |
| R6 | Does the captain's email include customer details? | ✅ **No.** The captain's email contains **no customer information** (§7.8). |
| R7 | Commission %? | ✅ 20% for now (stored as a setting). |
| R8 | Emails during development? | ✅ `MAIL_MAILER=log` locally (emails go to `storage/logs/laravel.log`). Real SMTP (Namecheap business mailbox) in Phase 9/11. (2026-11-22) |
| R9 | Must customers verify their email? | ✅ Yes, **before paying** (checkout requires `verified`, Phase 4). Owners verify before listing a boat (Phase 2.3). (2026-11-22) |

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
- **The admin never signs up publicly.** The account is created with `php artisan app:create-admin`. 2FA is mandatory, and after login the admin goes to `/admin`.
- **The admin dashboard shows everyone:**
  - A Users screen with tabs for Customers and Owners.
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

**Redirect after login**

- Admin → `/admin`.
- Owner with listings → `/owner`.
- Everyone else → the page they were on, or `/account/trips`.

### 4.3 Roles

| Role | Who | Notes |
|---|---|---|
| `customer` | Anyone who registers | Default. |
| `owner` | Users who list a boat | Set by the system when the first listing starts. Includes all customer abilities. |
| `admin` | The client / you (one account) | Full rights. Only created with the artisan command. 2FA is mandatory. |
| *(later)* `manager`, `support` | Staff | Added later. |

How it works in code:

- The `users.role` column is **never mass-assignable**. Only system actions change it: registration sets `customer`, the first listing sets `owner`, and the artisan command creates `admin`.
- Every permission is a **named Gate**, for example `Gate::define('approve-payouts', fn ($u) => $u->isAdmin())`. Controllers and views only check these names (`can('approve-payouts')`).
- Staff roles can be added later by changing the Gate definitions. Controllers don't change.
- **Ownership** is checked by **Policies** (`BoatPolicy`, `BookingPolicy`, `PayoutPolicy`, `ComplaintPolicy`).

### 4.4 Access matrix

| Area | Guest | Customer | Owner | Admin |
|---|---|---|---|---|
| Public site, build a booking | ✅ | ✅ | ✅ | ✅ |
| Checkout / pay | login | ✅ | ✅ | – |
| My trips, complaints (own only) | – | ✅ | ✅ | – |
| List Your Boat, My boats (own only) | login | ✅ (becomes owner) | ✅ | – |
| Earnings / withdrawals (own only) | – | – | ✅ + 2FA | – |
| `/admin` dashboard | – | – | – | ✅ + 2FA |
| Refunds, payouts, deductions, settings | – | – | – | ✅ + password re-confirmation |

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
| Admin | `/admin/*` | `auth`, `active`, `role:admin`, `2fa`, `admin.idle` (30 min); money actions also need `password.confirm` |
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
4. **Captains:** profiles (with email), licenses, documents, availability, assignment + email, captain pay.
5. **Booking engine:** quote, availability, holds, bookings, line items, status history, promo codes.
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
  - **role** (customer|owner|admin, *not fillable*), status (active|suspended|banned), status_reason.
  - two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at.
  - stripe_customer_id, marketing_opt_in, last_login_at, last_login_ip, timestamps, deleted_at.
- **sessions, password_reset_tokens, cache, jobs, failed_jobs:** already exist.
- **login_events:** user_id?, email, event (login|failed|logout|lockout|2fa_failed|password_changed), ip, user_agent, created_at.
- **audit_logs:** id, user_id?, action (e.g. `payout.approved`, `boat.price_changed`), subject_type, subject_id, before (json), after (json), reason, ip, user_agent, created_at.
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
  - **Description:** title, short, description.
  - **Specs:** type (power|sail), category, make, model, year, length_ft, registration_no, uscg_inspected, max_guests, staterooms, sleeps, beds, heads, engine_count, engine_type, horsepower, engine_year.
  - **Location:** harbor_id?, location_type, street, city, state, zip, country, lat, lng.
  - **Rules and booking settings:** cancellation_policy_id, rules (json), advance_notice_minutes, allow_multiple_bookings, buffer_minutes.
  - **Operation:** renter_operated_allowed, captain_source (owner|platform), fishing_charter, fuel_policy.
  - **Pricing:** hourly_rate_cents, weekend_multiplier_bp?, min_hours, durations (json), duration_prices (json, optional fixed price per duration), cleaning_fee_cents, captain_rate_cents (customer-facing, per hour), crew_count.
  - **Display:** badge, is_featured, sort, rating_avg, reviews_count. These are entered by the admin until reviews are added.
  - **Listing workflow:** wizard_step, submitted_at, reviewed_at, review_notes, pending_changes (json), published_at, timestamps, deleted_at.
- **amenities:** id, name, group (highlight|feature|extra|navigation), icon. Pivot **amenity_boat**.
- **boat_availability_rules:** boat_id, weekday (0–6), is_open, opens_at, closes_at.
- **boat_blocks:** boat_id? (null = all boats), starts_at, ends_at, type (owner_block|maintenance|blackout|weather), reason, created_by.
- **boat_insurances:**
  - boat_id, insurance_type (recreational|commercial), owner_answers (json), boat_answers (json), boat_value_cents.
  - carrier, policy_number (encrypted), expires_on, liability_limit, coi_media_id (private file).
  - status (pending|verified|expired).

### 6.3 Catalog

- **experiences:**
  - id, slug, name, category, icon, tagline, description, includes (json).
  - durations (json), time_slots (json), allowed_weekdays (json?), season_starts_on?, season_ends_on?.
  - package_fee_cents, is_active, is_featured, sort, seo_title, seo_description.
- **boat_experience:** boat_id, experience_id, package_fee_override_cents?, is_active.
- **addons:** id, slug, name, description, icon, price_cents, unit (flat|per_guest), max_quantity, is_active, sort.
- **cancellation_policies:** id, slug (flexible|moderate|strict), name, badge, points (json, display text), **rules** (json, editable by the admin, e.g. `[{"min_hours_before":120,"refund_pct":100},{"min_hours_before":72,"refund_pct":50},{"min_hours_before":0,"refund_pct":0}]`), is_active.

### 6.4 Captains

- **captains:**
  - id, ulid, name, slug, photo_path, bio, **email (required, captain-provided; used for assignment emails)**, phone.
  - license_type (oupv|master|other), license_number (encrypted), license_expires_on, max_passengers_licensed.
  - years_experience, languages (json), pay_rate_cents (internal, per hour).
  - is_active, is_public, sort, deleted_at.
- **captain_unavailability:** captain_id, starts_at, ends_at, reason.
- License documents are stored in `media`, on the private disk.

### 6.5 Booking engine

- **bookings:**
  - **Identity:** id, ulid, reference (unique, e.g. `CYB-7K3P9Q`), customer_id, boat_id, experience_id?.
  - **Captain:** captain_id?, captain_assigned_at, **captain_notified_at**, captain_notes (written by the admin, sent to the captain; never customer personal data).
  - **Status:** `status` (§7.3), `payment_status` (unpaid|deposit_paid|paid|partially_refunded|refunded|failed).
  - **Trip:** starts_at, ends_at, hours, guests, occasion, customer_notes, admin_notes.
  - **Contact snapshot:** contact_first_name, contact_last_name, contact_email, contact_phone, heard_from.
  - **Money:** payment_plan (deposit|full), subtotal_cents, discount_cents, tax_cents, gratuity_bp, gratuity_cents, total_cents, amount_paid_cents, amount_refunded_cents, balance_due_cents, balance_due_at.
  - **Snapshots:** promo_code_id?, policy_snapshot (json), pricing_snapshot (json).
  - **Lifecycle:** hold_expires_at, confirmed_at, completed_at, cancelled_at, cancelled_by_role (customer|owner|admin|system), cancellation_reason, source (web|admin|phone), timestamps.
- **booking_items:** booking_id, type (charter|captain|package|addon|cleaning|discount|tax|gratuity|fee), addon_id?, label, description, quantity, unit_amount_cents, amount_cents, **payee** (platform|owner|captain), sort.
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
  - kind (deposit|balance|full|extra), amount_cents.
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
  - Booking and pricing: tax_rate_bp, deposit_bp, balance_due_days, weekend_multiplier_bp, gratuity options, hold_minutes, season dates.
  - Owner money: commission_bp, **payout_delay_hours = 72**, **complaint_window_hours = 72**, **min_withdrawal_cents = 5000**.
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
package   = experience package fee (or the boat's override)
addons    = flat, or price × guests
cleaning  = fuel & cleaning fee
discount  = promo (% of charter, or a fixed amount, capped)
tax       = tax_rate × (subtotal − discount)          (9% placeholder)
gratuity  = gratuity % × (charter + captain)          (optional: 0/15/18/20)
total     = subtotal − discount + tax + gratuity
deposit   = 50% now; the balance is charged automatically 7 days before the trip
trip < 7 days away → full payment only
```

The JS only *shows* prices it gets back from the server. The server ignores any price sent by the browser and recalculates at hold time and again at payment.

### 7.2 Availability (`AvailabilityService`)

A slot is free only if all of these hold:

- The boat is `published` and `booking_enabled`.
- The slot is inside the boat's weekly hours.
- It doesn't overlap a block, a blackout or the end of the season.
- The experience allows that weekday and season.
- It is at least `advance_notice` ahead of now.
- It doesn't overlap any `hold` or `confirmed` booking of that boat (+ buffer).
- Guests ≤ max_guests.

**No double booking:** the hold is created in a DB transaction that locks the boat row (`lockForUpdate`) and re-checks the overlap before inserting.

### 7.3 Booking lifecycle

```
hold (15 min) ──payment ok (webhook)──▶ confirmed ──trip ends──▶ completed
   └─timeout─▶ expired                     ├─▶ cancelled (customer | owner | admin | weather) ─▶ refund (§7.4)
                                           └─▶ no_show
```

Payment status and captain assignment are tracked separately. Every change is written to `booking_status_history` + `audit_logs`.

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
any unpaid balance is cancelled and never charged
```

Example: total $1,000 (no gratuity), deposit paid $500.
- Cancel in the 50% window → the customer owes $500, has paid $500 → refund $0, and the balance is not charged.
- Cancel in the 100% window → refund $500.

**Special cases**

- **Weather or safety cancellation** (by the admin): 100% refund, or a reschedule if the customer prefers.
- **Owner cancels a confirmed trip:** 100% refund to the customer. The admin can deduct a penalty from the owner's earnings, as a `deduction` ledger entry with a reason (R5 ✅).
- **Admin override:** the admin can always issue a custom full or partial refund with a written reason. This needs password re-confirmation and is audited.

**Every refund automatically reverses the owner's share** of that booking (a `refund_reversal` ledger entry).

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

### 7.8 Captain assignment + email

- The assignment form in the admin booking page shows the selected captain's **email and phone**, both from the captain's profile and provided by the captain.
- **Checks before saving** (every one must pass):
  1. The captain is active.
  2. Their license is valid on the trip date.
  3. The license type allows the guest count (an OUPV "six-pack" captain can take at most 6 passengers).
  4. The captain has no overlapping trip and no unavailability at that time.
- **On save:**
  - A queued **email goes to the captain**. It includes the date and time (Chicago time), harbor and map link, boat, guest count and experience. It also includes the admin's **"Notes for captain"**, if any, the admin's contact details for coordination, and an `.ics` file.
  - **No customer information** goes in the email (R6): no name, phone, email or customer notes. The admin passes on anything the captain needs through "Notes for captain".
  - `captain_notified_at` is set, and a "Resend email" button is available.
- **Reassignment:** the old captain gets an "unassigned" email and the new captain gets the assignment email.
- **Trip changes:** if the booking is cancelled or rescheduled, the captain is emailed.
- The customer sees the captain's name, photo and bio on the trip page and in the reminder email.

---

## 8. Security plan

### 8.1 Authentication, sessions, authorization

- **Fortify:** register, login, logout, email verification, password reset, password confirmation, **2FA (TOTP + recovery codes)**.
  - 2FA is mandatory for the admin and required for owner money actions.
- **Passwords:** `Password::min(12)->mixedCase()->numbers()->uncompromised()`, hashed with bcrypt (12 rounds).
- **Throttling:**
  - Login: 5/min per email + IP, with lockout.
  - Password reset, 2FA and registration are throttled too.
- **No account enumeration:**
  - Login and reset errors are generic.
  - Reset links are single-use and expire after 60 minutes.
- **Account changes:**
  - Changing the email needs re-verification.
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
    - hold creation, e.g. 10/min;
    - promo code checks, e.g. 10/min (stops code guessing);
    - uploads;
    - contact and newsletter, e.g. 5/hour per IP;
    - login, register and password reset.
12. **Idempotency:** hold creation, payments, refunds, payouts and withdrawals require a client idempotency key, stored with a unique index. A double-click or retry can never create two of anything. Each runs inside a **DB transaction**.
13. **Bots:** Turnstile + a honeypot field on every public, unauthenticated form (register, contact, newsletter) and on checkout.
14. **Uploads:**
    - Separate endpoints with mime, size and dimension validation, re-encoding, and UUID filenames (§9).
    - PHP `upload_max_filesize` / `post_max_size` set to match.
15. **Sensitive actions** (refunds, payouts, deductions, settings, payout account changes, role changes) need **password re-confirmation** and a written reason, and are written to the audit log.

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
- **Confirmation by webhook only:** a booking is `confirmed` only when the **webhook** reports success; the browser redirect alone never confirms it.
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

- The DB stores **only the path relative to its disk**, in `media.path`, e.g. `boats/01JD…/photos/9f1c….webp`. Variant paths go in `media.variants`.
- **Upload pipeline** (`MediaService`):
  1. Check the real mime type (finfo) and extension, max 10 MB, and minimum dimensions.
  2. **Re-encode images to WebP.** This strips EXIF/GPS and hidden payloads.
  3. Create sizes: thumbnail 400px, card 800px, large 1600px.
  4. Save under a **random UUID filename**. The user's filename is never used on disk.
  5. Never store uploads where PHP could execute them.
  - PDFs are allowed only for documents, and only on the private disk.
- Deleting a record → a queued job deletes its files. A weekly command removes orphaned files.
- Design assets (logo, favicon, hero video and the mockup's images) are built with Vite into `public/`; they are not in `media`.
- Because files live on disk, **backups must include `storage/`**. Moving to S3 later is a config change only.

---

## 10. Code architecture

```
app/
  Enums/          Role, BoatStatus, BookingStatus, PaymentStatus, PayoutStatus, LedgerType, ComplaintStatus…
  Models/         User, OwnerProfile, Boat, Harbor, Experience, Addon, Captain, Booking, BookingItem,
                  BookingComplaint, Payment, Refund, LedgerEntry, Payout, PromoCode, ContactMessage,
                  Media, Setting, AuditLog…
  Policies/       one per model (ownership + Gates)
  Http/
    Controllers/  Public/, Account/, Owner/, Admin/, Webhooks/   (thin; HTML or JSON)
    Requests/     one FormRequest per form / endpoint / wizard step
    Resources/    API Resources = the JSON whitelist
    Middleware/   EnsureAccountIsActive, EnsureUserHasRole, RequireTwoFactor, AdminIdleTimeout, SecurityHeaders
  Services/       QuoteService, AvailabilityService, BookingService, PaymentService (Stripe, behind an
                  interface so tests can fake it), RefundService, CancellationPolicyService, LedgerService,
                  PayoutService, ComplaintService, CaptainAssignmentService, MediaService, SettingsService,
                  AuditService, TurnstileService
  Actions/        CreateBookingHold, ConfirmBooking, CancelBooking, AssignCaptain, SubmitListing,
                  ApproveListing, RequestWithdrawal, ApproveWithdrawal, ResolveComplaint…
  Events/ Listeners/  e.g. BookingConfirmed → emails, ledger entries, notifications
  Jobs/           ChargeBalance, ExpireHolds, ReleaseEarnings, SendTripReminders, ProcessStripeWebhook,
                  InsuranceExpiryCheck, CleanupOrphanFiles
  Mail/ Notifications/
  Support/        Money, ChicagoTime
resources/
  views/
    layouts/      app (public), wizard (minimal header), account, owner, admin, mail
    partials/     header, footer, mobile-menu, admin-sidebar, admin-topbar
    components/   public: button, badge, yacht-card, experience-card, section-heading, input, select,
                  radio-card, accordion, lightbox, toast, stepper, price-summary
                  admin:  data-table, filters, stat-card, status-badge, form-section, modal, confirm-dialog,
                          empty-state, tabs, pagination
    pages/ booking/ checkout/ account/ owner/ admin/ auth/ errors/
  js/
    lib/          http.js (CSRF fetch), dom.js (escape/render), money.js, dates.js, toast.js
    pages/        home.js, yachts.js, yacht.js, booking/*.js, checkout.js, contact.js
    owner/        wizard/*.js, boats.js, payouts.js
    admin/        tables.js, booking-calendar.js, assign-captain.js, uploads.js
routes/  web.php (public + JSON) · account.php · owner.php · admin.php · webhooks.php · console.php (scheduler)
```

- **Rule:** business logic lives in Services and Actions, never in controllers, Blade or JS. Status changes go through one method per model (e.g. `BookingService::transition()`), which checks the allowed moves and writes the history.
- **Scheduler** (one cron job every minute on Namecheap):

  | Job | Runs |
  |---|---|
  | queue worker | every minute |
  | ExpireHolds | every minute |
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
| Customer | `/account` (profile, security, sessions, cards) · `/account/trips` · `/account/trips/{ulid}` (cancel, receipt, complaint) |
| Owner | `/list-your-boat` → `/owner/boats/{ulid}/wizard/{step}` (JSON `PATCH` per step, `POST` photos) · `/owner` · `/owner/boats` · `/owner/bookings` · `/owner/calendar` · `/owner/earnings` · `/owner/payouts` · `/owner/complaints` |
| Admin | `/admin` · `/admin/bookings` (+ calendar) · `/admin/boats` · `/admin/listings` · `/admin/experiences` · `/admin/addons` · `/admin/captains` · `/admin/harbors` · `/admin/users` · `/admin/payouts` · `/admin/complaints` · `/admin/payments` · `/admin/promo-codes` · `/admin/inbox` · `/admin/content` · `/admin/settings` · `/admin/audit-log` |
| Webhooks | `/webhooks/stripe` |

---

## 11. Emails and notifications (queued, branded, plain-text version included)

| Audience | Emails |
|---|---|
| Account | Verify email, password reset, password changed, new-device login, 2FA on/off |
| Customer | Booking confirmation (+ .ics + PDF receipt), payment receipt, balance reminder (T-10 days), balance charged / failed, trip reminder T-24h (harbor + captain), captain assigned, cancellation + refund, complaint received / resolved |
| Owner | Listing submitted / approved / changes requested / rejected, new booking, booking cancelled, complaint opened (please respond) / resolved, deduction applied, withdrawal requested / approved / paid / rejected, payouts paused, insurance expiring |
| **Captain** | **Trip assigned (+ .ics)**, unassigned, trip changed / cancelled. Trip details and admin notes only, **no customer information** |
| Admin | New booking, unassigned trip within 7 days, failed balance charge, listing to review, withdrawal to approve, new complaint, new contact message, dispute opened |
| Contact form | Auto-reply to the sender; notification to the business inbox (Reply-To = sender); admin replies are sent from the dashboard |

There are also in-app notifications (bell icon) for customers, owners and the admin.

---

## 12. Admin dashboard (custom, D3)

| Section | Contents |
|---|---|
| **Home** | Today's and upcoming trips; revenue (gross / net / commission); bookings by status; **action list** (listings to review, withdrawals to approve, open complaints, trips without a captain, failed charges, new messages) |
| **Bookings** | List with search, filters, sort and pagination + **calendar**; detail (line items, payments, history, notes); change status; **assign captain + email**; cancel / refund; resend emails; manual booking (phone orders) |
| **Fleet** | Boats (house + owner: photos, pricing, availability, blocks, experiences offered, **Unavailable toggle**); **Listings review queue**; harbors; amenities |
| **Catalog** | Experiences (slots, durations, weekdays, season, fees, images); add-ons; cancellation policies (edit hours and %); promo codes |
| **Captains** | Profiles (email, phone, license + expiry, documents, pay rate), availability, trips, earnings |
| **Finance** | Payments, refunds, disputes, owner ledgers, **withdrawals (approve / reject / hold)**, **pause owner payouts**, deductions and adjustments, captain payouts, CSV export |
| **Complaints** | Queue, detail (customer text and photos, owner response, booking earnings), resolve (no action / deduction / refund / reject) |
| **Users** | Customers and owners: detail, suspend / ban, reset 2FA, notes |
| **Inbox** | Contact messages (status, reply by email, spam); newsletter subscribers |
| **Content** | Business info, hero, FAQs, testimonials, gallery, legal pages, SEO |
| **Settings** | Tax, deposit, balance days, weekend multiplier, gratuity options, commission, payout delay (72 h), complaint window, minimum withdrawal, hold minutes, season |
| **System** | Audit log, login log, failed jobs |

---

## 13. Pages

**From the mockup (ported 1:1):** home, yachts, yacht detail, experiences, pricing, about, contact, booking (4 steps), checkout, confirmation, the list-your-boat wizard, my boats, owner dashboard.

**New pages, in the same design:**

- **Auth:** login, register, forgot / reset password, verify email, 2FA challenge, confirm password.
- **Customer:** My trips; trip detail (cancel with a refund preview, receipt, calendar, complaint form, captain card); profile & security (password, 2FA, sessions); saved cards.
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
2.2 User columns (`role`, `status`, …), the Role enum, Gates, Policies skeleton, `app:create-admin`.
2.3 Middleware: active account, role, 2FA required, admin idle timeout, `SecurityHeaders` + CSP with nonces, HTTPS / proxies. Also set `'serve' => false` on the private `local` disk (`config/filesystems.php`). That removes Laravel's built-in signed `GET`/`PUT /storage/{path}` routes (found in Phase 0), because private files are served only by our own Policy-checked controller. (The `.js` class is already done in Phase 1.1: it is rendered server-side, with `public/css/noscript.css` for visitors without JS, so no inline script is needed.)
2.4 2FA + recovery codes; password confirmation; active sessions + logout other devices; `login_events`; `audit_logs` + `AuditService`; security emails.
2.5 Role-based redirects, header account menu (UI built in Phase 1: `partials/account-menu`, `HeaderComposer` — switch it to `$request->user()`), account pages (profile, email change, password, delete account).
2.6 JSON endpoint foundation: error format, named rate limiters, API Resource base, idempotency-key middleware.
**QA:** route × role access-matrix test; throttle tests; no-enumeration tests; session regeneration and cookie-flag tests; CSP check (no console violations).

### Phase 3 — Catalog database + admin v1
3.1 Migrations, models, enums, factories: harbors, boats, amenities, availability rules, blocks, experiences, boat_experience, addons, cancellation policies (Boatsetter rules), captains, settings, media.
3.2 Seeders from the mockup's `data.js` (6 boats, 8 experiences, 7 add-ons, 4 harbors, 3 policies, settings) + images.
3.3 `MediaService` (upload pipeline, variants, private disk) + `storage:link`.
3.4 Admin screens: Boats, Experiences, Add-ons, Harbors, Amenities, Captains, Users, Settings, Cancellation policies; dashboard skeleton.
3.5 Public pages read from the DB. **Unavailable** tag from `booking_enabled`. Set up the **client's own boat** (once its details arrive) and mark the other boats unavailable.
3.6 Cache the public catalog; SEO meta + sitemap.
**QA:** admin CRUD + policy tests; public pages show admin changes; upload security tests (wrong mime type, oversized file, PHP disguised as an image); sort/filter whitelist tests.

### Phase 4 — Booking engine (payment stubbed)
4.1 `QuoteService` (from `CYB.quote`, in cents) + unit tests: weekend, promo, gratuity, add-ons per guest, duration prices, rounding, deposit vs full.
4.2 `AvailabilityService`: rules, blocks, bookings + buffer, advance notice, weekdays and season, guest limits.
4.3 Booking wizard JS ⇄ JSON endpoints (`availability`, `quote`, `hold`); deep links (`?yacht=`, `?experience=`, `?date=`).
4.4 Hold + checkout: bookings, line items, status history, hold expiry, **double-booking lock**, inline login/register, promo codes + redemptions.
4.5 Confirmation page + email + .ics.
4.6 My trips + trip detail.
4.7 Admin: Bookings (list, calendar, detail, status, notes) + promo codes.
**QA:** price parity with the mockup on 20 cases; concurrency test (two holds on one slot); hold expiry; IDOR tests; §8.2 endpoint test set.

### Phase 5 — Payments + cancellations (Stripe test mode)
5.1 `PaymentService` interface + Stripe implementation; Stripe Customer; Payment Element (deposit / full); 3-D Secure.
5.2 Webhook endpoint + `webhook_events` + idempotent handlers; the webhook confirms the booking.
5.3 Saved cards page; automatic balance charge with retries and a failure flow.
5.4 `CancellationPolicyService` (Boatsetter-style, §7.4) with a refund preview; customer cancel; admin custom / weather / owner-cancel refunds; dispute records.
5.5 Receipt PDF; admin Payments and Refunds screens; nightly reconcile.
**QA:** Stripe test-card matrix (success, decline, 3-D Secure, insufficient funds); webhook replay and duplicates; refund math tests for every policy window (including deposit-only cases).

### Phase 6 — Owners: List Your Boat (real)
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

### Phase 8 — Captain operations
8.1 Captain documents + license-expiry alerts; captain unavailability.
8.2 Assign / reassign on the booking page (checks from §7.8) → **email to the captain** (+ .ics), resend button, unassigned email.
8.3 Captain card on the customer trip page and in the reminder email; "trips without a captain" on the admin home.
8.4 Captain pay ledger (pay rate × hours + gratuity) and captain payouts (manual).
**QA:** conflict tests (overlap, expired license, passenger limit); email content tests; reassignment flow.

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
  - PHP version + extensions (GD/Imagick, intl, bcmath, fileinfo, pdo_mysql, mbstring, openssl).
  - Document root → `/public`; `storage:link`; one cron job (`schedule:run`); free SSL.
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
  - 2FA for the admin and for owner money actions.
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
- **Captains:** a captain assignment email (+ .ics), USCG passenger limits tied to the license type, and license and insurance expiry checks.
- **Listings:** re-approval of risky edits on published owner boats.
- **Files:** private storage for documents, image re-encoding, and backups that include `storage/`.
- **Records:** an audit log, a login log, and account deletion with anonymization.

**Later (after v1):**

- Reviews and ratings. Until then, the admin sets the rating and review count shown on each boat.
- Liability waiver e-signature and security-deposit holds.
- Customer ↔ owner messaging, and SMS.
- Gift cards and customer credits.
- A captain portal login.
- Staff roles (manager, support) with permissions.
- Automatic scheduled payouts.
- Multi-language and analytics.
