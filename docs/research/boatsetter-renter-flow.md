# Boatsetter, renter side: search, boat page, booking. What we change.

> Researched 2026-11-24 / 25 on boatsetter.com, Chicago, desktop 1366 px and phone 390 px: first as a **guest**, then (2026-11-25) logged in to the user's test account to see checkout (§9). Nothing was submitted: no booking, card, message or payment.
> Companion file: [boatsetter-list-your-boat.md](boatsetter-list-your-boat.md) (the owner side).
> Client wish (2026-11-24 / 25): "a website with Boatsetter-like functionality" **for Chicago only**. **The flow should work like Boatsetter's. Our own design stays** (navy / brass, our fonts and sections) and is only adjusted where the flow needs it, matching Boatsetter slightly, not completely. Own brand and wording.
> **Decisions answered 2026-11-25:** see §11. The new Phase 4 order (§12) is in PLAN.

## 1. Site map (renter side)

| Area | Boatsetter | Notes |
|---|---|---|
| Header | Logo · **Experiences** (menu: All boats, Yachts, Fishing, Party boats, Pontoons, Sailboats, Jet Skis, Watersports, Luxury yachts) · Destinations (cities, lakes) · **List your boat** · Messages · Bookings · Sign up · Log in | Their "Experiences" are **boat categories / occasions**, not packages. |
| Home | Hero "Browse, book, boat", **one search box: location only** · Explore destinations (carousel) · Top boating categories (10 tiles) · "Adventure is calling" | |
| Location page | `/boat-rentals/chicago--il--united-states`: SEO page with boat rows (Best, Fishing, Yacht rentals, **Boats you can book instantly**), trust badges, reviews, FAQs, local guide (Lakefront, Playpen, River) | Static landing page for Google. |
| Category pages | `/party-boat-rentals`, `/yacht-rentals`, `/bachelorette-party-boat-rentals` … | Same layout as the location page. |
| Search | `/a/boat-rentals/search?near=…&trip_date=…` (142 boats in Chicago) | §2 |
| Boat page | `/boats/{public id}` | §3, §4 |
| Checkout | `/a/bookings/book_now?booking[boat_public_id]=…&booking[package_type]=captained&booking[passengers]=4&booking[trip_start]=2026-11-28&booking[duration]=two_hours&booking[trip_time]=13:00…` | Login wall first (§9). |
| Trips | `/bookings`, inbox `/a/inbox` | Login. |
| Trust | `/legal/boatsetter_promise`, `/legal/cancellation_policy`, `/legal/trip_assurance`, `/legal/fee_policy`, `/how-it-works` ("Search · Connect · Set sail") | §7 |

## 2. Search page

**Desktop**
- Top bar: location box + a **date pill** (Single day / Multi-day calendar).
- Activity chips: Watersports, Fishing, Sailing, Cruising, Celebrating.
- Filter pills: **Filters**, Price, Duration, Captain, Passengers. Map on / off with price pins, "Search as I move the map".
- **Filters panel:** price per hour (slider $1 – $1,000+), passengers (stepper), duration (2 / 3 / 4 / 6 / 8 h), captain (Captained / No captain), activities, **Instant Book** switch ("Book a boat without waiting for approval"), boat length (0' – 70'+), amenities (popular + View all), boat category (Catamaran, Center console, Cruiser, Jet Ski, Pontoon, Sailboat, Waterski / Wakeboard, Other). Buttons: Clear all · Show results.

**Card**
- Photo carousel (1 / 7) with a heart (save).
- Price pill on the photo: **"$156+/hour"**. Badge on the photo: **⚡ INSTANT BOOK**.
- City · ★ 5.0 (180 bookings).
- Title.
- **"2 - 8 hours rental • Captained"** (or Captain optional / No captain).

**Phone (390 px):** compact header (logo + "Chicago, IL · Sat, Nov 28" pill + menu); activity tiles and filter chips scroll sideways; "142 boats in Chicago, IL"; full-width cards; a floating **MAP VIEW** button.

## 3. Boat page

Top to bottom (desktop: the booking card is a sticky column on the right, overlapping the photo):

1. **Photo carousel**, full width, counter "1 / 21", Save and Share.
2. **Title**, ★ 4.9 (295 bookings), location.
3. **Quick facts row** (icons): 44 ft Boat length · Up to 12 Passengers · Captained / *Captain optional — rented with or without a captain* · 97% Owner's response rate.
4. **The boat:** description with Read more, "Owned by William ★ 5.0 (41 ratings)".
5. **Amenities:** 3-column check list (Bathroom, Stereo, Bluetooth audio, Floating mat, Cooler, Air conditioning, Swim ladder, Children's life jackets …).
6. **Specifications:** Year, Length, Make, Model, Capacity, See all.
7. **Booking options:** tabs **No captain | Captained**, then one price per length: 2 h $250 · 3 h $375 · 4 h $500 · 6 h $725. "Fuel is not included."
8. **Ratings & reviews** (See all 83).
9. **Boat location:** an area; the exact place is shown after booking.
10. **Your crew:** owner, response rate, average response time (< 5 hours), **MESSAGE OWNER**.
11. **Things to know:**
    - *Allowed on boat:* Swimming, Alcohol, Kids under 12, Fishing, Glass bottles, Liveaboard, Red wine (each allowed or not).
    - *Cancellation policy:* MODERATE + its three lines + See policy.
    - *Commercial owner* note (they bring their own insurance).
    - *Security deposit:* a card **hold** (not a charge) 48 h before the trip, released after it.
    - *Captain info:* who provides the captain for each charter type.
12. Similar boats · Explore more.

## 4. The booking card: how a trip is booked

This is the heart of Boatsetter's booking. **There is no multi-step wizard.** Everything is chosen on the boat page, then the button goes straight to checkout.

```
┌────────────────────────────────────┐
│ $450 / 2 hr (excl. fees)           │
├────────────────────────────────────┤
│ Date            Nov 29, 2026   📅  │  → calendar popover
│ Duration        2 hours        ⏱  │  → 2-column grid: 2 / 3 / 4 / 6 hours (no prices)
│ Start time      2:00 PM        🕑  │  → "Times shown reflect current availability."
│                                    │     2-column grid of half-hour times (10:00 AM … 10:00 PM)
│ [ Captained ]  [ No captain ]      │  ← appears after a start time is picked
│ [ – ]      4 passengers      [ + ] │
│ [ ⚡ INSTANT BOOK ]                │  or [ REQUEST TO BOOK ]
│      You won't be charged yet      │
│ Booking total            $450.00   │
│ Additional fees will show at checkout
│ Boat price               $250.00   │
│ Captain price            $200.00   │
│ 🛡 Trip assurance available        │
│   Cancel anytime before your trip for a full refund
│ Terms: Fuel is not included ·      │
│ Security deposit: $500 ·           │
│ Cancellation policy: Moderate ·    │
│ Charter type: Time Charter         │
└────────────────────────────────────┘
```

- The card opens with a date and the shortest length already filled in. The start time is empty until the renter picks one.
- **Captained vs No captain** (only on "Captain optional" boats): No captain = **Bareboat** charter, boat price only ($250). Captained = **Time Charter**, boat price + a separate **Captain price** line ($200 for 2 h, i.e. $100 / h). On a captained-only boat the "No captain" button is disabled.
- **Instant Book vs Request to book** is per boat (the owner's switch). Both buttons lead to the same checkout. Under Instant Book: "You won't be charged yet".
- The headline price follows the choices ("$678 / 2 hr" → "$450 / 2 hr" when the captain is added).
- **Fees are hidden until checkout** ("excl. fees", "Additional fees will show at checkout").
- Owners write their own rules in the description (e.g. "Instant booking start times are 10:30 am, 11 am, 3 pm …", "holidays are a 4-hour minimum"). Boatsetter's start-time list itself is just the boat's availability.

**Phone:** the card becomes a **fixed bottom bar**: "$250 · 2 hr (excl. fees)" + **⚡ INSTANT BOOK**. Tapping it opens a **full-screen sheet** titled "Request to book" → "Enter your booking details": the same Date / Duration / Start time fields, Captained / No captain, passengers, Booking total, Boat price, Trip assurance, and a sticky button with "You won't be charged yet".

## 5. After the button

As a guest, both buttons go to **Log in or sign up** (email + password, or Google / Facebook / Apple), with `redirect_to` the checkout URL. The trip choices travel in the URL, so nothing is lost. Checkout itself needs a login (§9).

The booking URL carries `boat_public_id`, `package_public_id` + `package_type` (`captained` / `bareboat`), `passengers`, `trip_start` (date), `duration` (`two_hours` …), `trip_time` (`14:00`), `trip_finish`, `tz=America/Chicago`.

## 6. Fees, deposit and when the card is charged (from their policy pages)

- **Service fee** (renter): added at checkout on the list price, before coupons; non-refundable. Owners earn 65 %–88.5 % of the list price.
- **When the money moves:** "Your primary card on file will be charged the full booking amount **once your trip is approved**." So for Request to book, the card is saved at checkout and charged when the owner accepts. There is no deposit + balance plan.
- **Security deposit:** at least $500 (the owner can set more), held on the card **48 h before** the trip and released 48–72 h after if there is no claim. If the hold fails, the trip can be cancelled.
- **Extra charges** (fuel, damage, extras): 3.0 % + $0.15 processing fee. **Late fee:** $30 + the prorated hourly rate. **Refuelling fee:** fuel + $30. Chargeback fee $15.
- **Trip Assurance** (optional, bought at checkout): cancel for any reason before the start and get a full refund minus the assurance fee. Not available when the booking is already in the no-refund window.

## 7. Cancellation policies and the Promise

| Policy | Full refund | 50 % | No refund |
|---|---|---|---|
| Flexible | ≥ 24 h | – | < 24 h |
| Moderate | ≥ 5 days | 2–5 days (the boat page says 3–5) | < 2 days |
| Strict | ≥ 30 days | 14–30 days | < 14 days |

Ours (PLAN §7.4) already match within a day (Moderate 3–5 / < 3 days, Strict 15–30 / < 15 days). **No change needed.**

The **Boatsetter Promise** page bundles: weather and owner no-show refunds, mechanical-issue rescheduling, captain no-show handling, a 100-point boat check, secure payments (Stripe), and insurance. Our version of this page is copy only.

## 8. Our site today vs Boatsetter

| Part | Our site now (mockup port) | Boatsetter | Gap |
|---|---|---|---|
| Main booking path | Yacht page card (Experience, Date, Duration, Guests, estimate) → **Check Availability** → `/book` **4-step wizard** (Experience → Yacht & Guests → Date & Time → Extras) → `/checkout` → confirmation | Everything on the **boat page card** → checkout | Big: no wizard on Boatsetter. |
| Start time | Picked in wizard step 3 | Start-time popover on the card, from live availability | Our `AvailabilityService::slots()` already gives this. |
| Experience / package | Required first choice | None. "Experiences" = categories. | Our services already allow **no experience** (`?Experience`). |
| Captain | Always included (captain fee / hr) | Captained / No captain per boat, separate price line | Owner boats (Phase 6) may offer both. |
| Instant vs request | Not shown | Badge on cards, filter, button text | Decided 2026-11-24: owner boats by request, house boats instant. |
| Price shown | Estimated total **with** package, fees and tax | Boat + captain, "excl. fees" | B4: we keep showing every line. |
| Fleet page | Type pills, guests / harbor / sort selects, "Compare at a glance" table | Date pill, filter chips + full Filters panel, carousel cards with "$X+/hour", ⚡ badge, rating, "2 - 8 hours • Captained" | Medium. |
| Phone booking | Bottom bar → link to `/book` | Bottom bar → full-screen sheet with the card | Small. |
| Login | Planned inline in checkout (4.4) | Login wall before checkout | Ours is friendlier; keep. |
| Payment | Deposit 50 % + balance 7 days before, or full | Full amount when approved; deposit **hold** 48 h before | B5: full payment only. |
| Trip Assurance | – | Optional paid add-on | B6: later. |
| Reviews, saved boats, messaging | Demo / later (D18) | Everywhere | Later phases. |
| Map | – | Map with price pins (Google, paid) | Skip, or free OpenStreetMap later (needs an npm package). |

## 9. Checkout (seen logged in, 2026-11-25)

Checkout is **one page** (plus an add-ons page first, only when the boat has add-ons). Desktop: the form on the left, a **sticky "Pricing information" column** on the right. Header: logo + "Powered by Stripe".

**Page 1 — Add-ons** (only if the owner set some)
- "Want more? Enhance your trip." A checkbox list: name, description, price **per booking** or **per passenger** (e.g. Cooler / Ice chest $1 per booking, Tube $1 per passenger).
- "Add-ons total", button **SKIP & CONTINUE** (it reads "Continue" when something is ticked).

**Page 2 — Checkout**
1. **Trip summary card:** boat title, Chicago IL, "Start: Nov 29, 2026 • 5:00 PM / End: … • 7:00 PM", "Captained • 4 passengers" (or "No captain • 4 passengers"), rating, a photo.
2. **Add trip assurance** (a choice is required): "Protect my trip — Recommended — cancel anytime before your trip and get 100 % back, $38.25" / "No thanks, I'll take the risk — refunds follow the listing's cancellation terms". The price was **8.5 % of the boat price** on both boats tried.
3. **Payment information:** name on card, card number, MM/YY, CVV, ZIP (Stripe). On a **request**: "*Your card will not be charged until the owner approves the booking.*" On **instant book** that line is missing: the card is charged at once.
4. **Have a promo code?** field + Apply.
5. **Introduce yourself to {owner first name}:** a message box, pre-filled ("Hi Robert, I am requesting to book your boat on Nov 29 at 5:…"), and **Reason for booking**: Bachelor/Bachelorette party, Family trip, Friends outing, Birthday, Fishing, Special event, Corporate event, Watersports, Rest and relaxation, Other.
6. A checkbox: "I have read, understand and agree to the Terms of Service & Charter Agreement".
7. Button: **CONFIRM REQUEST** (request) or **INSTANT BOOK** (instant).

**Pricing column:** Boat price · (Trip assurance) · **Service fee** · Booking total · Total, then: Fuel included / not included, Security deposit $500, Cancellation policy, Charter type. Observed service fee: $450 → $78.50 and $250 → $52.50, i.e. **13 % + $20**.

- The **No captain (bareboat)** checkout asks **no** boating-experience or licence questions. Same page, "No captain • 4 passengers", "Charter type: Bareboat".
- There is no guest-details step: the account holds the name, email and phone.
- **Account menu:** View profile, Owner dashboard, My boats, My account, **Favorites**, List your boat, Support, Log out.
- **Bookings page:** tabs **As owner / As renter**; empty state "You have no bookings" + Explore. **Messages:** All / As renter / As owner.
- Not seen: the trip page after a booking and a waiting request. We would have to make a real booking for that, which we won't. Our own versions are planned in 4.6 / 4.7 (§12).

## 9a. Chicago (what people book there)

Boatsetter's Chicago page and guide name the same things our catalog already has:
- **Lakefront cruising & skyline views** (Navy Pier) → our *Sunset Skyline Cruise*.
- **The Playpen**: anchoring, swimming, the summer social scene → our *Playpen Swim Party*.
- **Chicago River**: architecture and bridges → our *River Architecture Cruise*.
- **Events:** fireworks, boat parties (Black Yacht Weekend, Chicago Scene Boat Party) → our *Navy Pier Fireworks*, *Birthdays & Bachelorettes*.
- **Marinas:** Belmont (15–20 min to the Playpen), Burnham (10–15 min), **Montrose** (20–30 min), Navy Pier / DuSable (5–10 min). We have DuSable, Navy Pier, Belmont and Burnham. **Montrose Harbor** could be added.
- **Categories used for Chicago:** Yachts, Pontoons, Sailboats, Fishing boats, Party boats, Jet skis. Listings say "Captained" or "Captain optional"; captained trips dominate in the city.

So our eight experiences (Sunset Skyline, Playpen, River Architecture, Navy Pier Fireworks, Birthdays & Bachelorettes, Corporate, Proposals & Romance, Private Day Charter) **stay**. They become the Chicago "experiences" a renter browses, like Boatsetter's categories, and are no longer a required first step.

## 10. Agreed changes (2026-11-25)

### 10.1 Flow (like Boatsetter)

1. **The boat page becomes the booking page.** Its card holds Date → Duration → Start time (live grid from `AvailabilityService`) → Captain choice (only when the boat offers both) → Guests → the button. The button goes straight to `/checkout` with the choices in the URL (date, hours, time, guests, captain, optional experience).
2. **Experiences become optional**, not step 1:
   - (a) The Experiences page works like Boatsetter's categories. Each Chicago experience lists the boats that offer it, and its "Book" opens the boat page with that experience picked.
   - (b) On the boat page, an "Experience (optional)" row in the card, for boats that offer packages. A picked experience can narrow the times and lengths and add its package fee, as today in `QuoteService` / `AvailabilityService`.
3. **The 4-step `/book` wizard is retired.** `/book?yacht=…&experience=…` redirects to the boat page with the same choices, so old links keep working. Without a yacht it goes to the fleet.
4. **Button text per boat:** "Instant book" (house boats) or "Request to book" (owner boats, unless the owner turns Instant on). Under a request: "You won't be charged until the owner accepts."
5. **Checkout (one page, our current layout):**
   - inline login / register (friendlier than Boatsetter's login wall);
   - the trip summary card;
   - **add-ons** move here from the wizard's "Extras" step;
   - lead-guest details;
   - occasion (= Boatsetter's "Reason for booking") + a note to the crew;
   - crew gratuity;
   - promo code;
   - card;
   - terms;
   - the button reads "Confirm request" or "Book now".
6. **Full payment only (B5).** No deposit and no balance charge.
   - **Instant book:** the whole total is charged at checkout.
   - **Request to book:** the card is authorised at checkout and **captured when the owner accepts**. If the owner declines or the request expires, the authorisation is released.
7. **Fees shown in full (B4):** the card and checkout show every line (charter, captain, package, add-ons, cleaning, discount, tax, gratuity) and the total. No hidden fees.

### 10.2 Design: ours, adjusted for the flow

**Our design stays:** navy / brass colours, Playfair + Manrope, the hero, the section styles and the cards. Only the parts the new flow needs change, matching Boatsetter **slightly**:

1. **Boat page booking card:**
   - Today: a navy header + selects + "Check availability".
   - New, in our style: Date / Duration / Start time rows, each with an icon and its own popover (calendar, duration chips, start-time grid), Captain choice, a guests stepper, the price lines and total, the Instant / Request button, and a "Free cancellation until …" line from the policy.
   - The rest of the boat page keeps our sections. Two light additions:
     - a quick-facts row (length · guests · captained · harbor);
     - a "Things to know" block (cancellation policy, what's allowed on board, captain info).
2. **Phone:**
   - Our existing bottom bar stays, but opens a **full-screen booking sheet** with the same card instead of going to `/book`.
3. **Fleet page:**
   - Our cards stay. Small additions: a "from $X / hour" price, an "Instant book" badge, "2 – 6 hours · Captained".
   - The filter bar becomes Date + Guests + chips (Experience, Duration, Captain, Price, Instant book), with a "more filters" panel (length, category, amenities).
   - "Free on this date" uses the availability calendar. **No map** (B8).
4. **Home:** keeps its design. The hero search becomes Date + Guests (+ experience) → fleet, since we have one city.
5. **Checkout:** keeps its design. Its sections change as in 10.1 (5–7): the deposit / full choice is removed.

## 11. Decisions (answered by the user, 2026-11-25)

| # | Question | Answer |
|---|---|---|
| B1 | Retire the 4-step `/book` wizard and book from the boat page card? | **Yes.** |
| B2 | Experiences optional (card row + category-style Experiences page) instead of the first step? | **Yes.** Our Chicago experiences stay (§9a). |
| B3 | Captained / No captain choice? | **Yes.** The switch is built, but "No captain" shows only on boats that allow it (owner boats, Phase 6). House boats stay captained. |
| B4 | Show every fee line and the full total? | **Yes.** |
| B5 | Payment plan? | **Full payment only, no deposit.** Instant book is charged at checkout. A request is authorised at checkout and captured when the owner accepts. |
| B6 | Trip Assurance? | **Later** (after Phase 5), as an optional checkout add-on. |
| B7 | Security-deposit hold? | **Later** (D18). |
| B8 | Fleet filters and a map? | Filters **yes**, map **no**. |
| B9 | See Boatsetter's checkout logged in? | **Done** 2026-11-25 (§9). |
| Design | How close to Boatsetter? | **Our design stays.** Only adjusted for the flow, slightly like Boatsetter (§10.2). |

**Not adopted:** Boatsetter's renter service fee (13 % + $20). Our platform earns from the owner's share (PLAN §7.5), so no service fee line is added unless the client asks.

**Changed later on 2026-11-25 (user):**
- **Experiences are free**, as on Boatsetter. Each Chicago experience keeps its own start times (a sunset cruise and the fireworks run at different times). If the admin adds something to an experience (party décor, a photographer, any facility), the admin sets its price (PLAN D21, built in 4.3). *Refined after the user's QA the same day:* the experience has no trip length of its own (the boat is booked by the hour), it departs inside its own window (the guest picks a start time inside it and is told when a time or day doesn't fit), and its extras are optional add-ons suggested at checkout, charged only when ticked.
- **B5 tightened: direct checkout only.** No deposit and **no request to book** either, owner boats included: every booking is paid in full at checkout and confirmed (PLAN D22).

## 12. New Phase 4 order (agreed 2026-11-25)

Before: 4.3 wizard ⇄ endpoints · 4.4 hold + checkout · 4.5 confirmation · 4.6 My trips · 4.7 admin bookings.

Now (8 steps, two are done):

| Step | What |
|---|---|
| 4.1 ✅ | QuoteService |
| 4.2 ✅ | AvailabilityService + start times + booking tables |
| **4.3** | **Boat page booking card**: date / duration / start-time popovers, optional experience, captain switch, guests, live price lines. JSON endpoints `availability` (month calendar + a day's start times) and `quote`. Phone bottom bar → full-screen sheet. `/book` redirects to the boat page. **Full payment only:** `QuoteService` drops the deposit (B5). |
| **4.4** | **Fleet + Experiences**: fleet filter bar (date, guests, chips, more filters), card additions, "free on this date". Experiences page as Chicago categories, each listing its boats. |
| 4.5 | **Hold + checkout:** one page in our design (inline login / register, add-ons, details, occasion + note, gratuity, promo, terms), double-booking lock, hold expiry, promo redemptions. Book now → `hold` → pay in full (stubbed until Phase 5). *(No request to book since the later 2026-11-25 decision.)* |
| 4.6 | Confirmation page + email + .ics. |
| 4.7 | My trips + trip detail. |
| 4.8 | Admin: bookings (list, calendar, detail, status, notes) + promo codes. |

**B5 also changes Phase 5:**
- 5.1 takes the full amount at checkout (no request to book, so no manual-capture authorisation).
- 5.3 has no automatic balance charge.
- §7.4 refunds have no deposit-only cases.
- Settings lose deposit % and balance days.
