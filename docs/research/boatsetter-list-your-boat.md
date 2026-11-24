# Boatsetter "List your boat" — how it works (research, 2026-11-24)

**Why:** the client wants our List Your Boat to work like Boatsetter's. This is a field-by-field map of Boatsetter's flow, taken from the user's own Boatsetter account in a browser, plus what it means for our wizard. Phase 6 ("Owners: List Your Boat (real)") builds on it.

**How it was gathered:**
- Logged in with the user's account. Walked the wizard with a test boat (the client's 2006 Cruisers Yachts 280 CXi at DuSable Harbor). Read each page's fields, dropdown options and conditional fields, and the wizard's own JavaScript (step order and navigation rules).
- **Nothing was submitted**: no photos uploaded, and none of the insurance pages certified.
- One **incomplete draft** ("Cruisers Yachts 280 CXi", id `bsmwqls`) was created in the user's Boatsetter account by "Get started". The user can delete it under *My boats → Delete listing*.
- Screenshots are kept outside the repo.

**Use the structure, not their words.** The flow, fields and rules are what we copy. Write the wording, help texts and images in our own words before launch: Boatsetter's text is theirs (copyright). Some of our mockup copy is still close to theirs.

---

## 1. The flow

```
/a/list-boat (start)                     address · year · make · model · estimated monthly earnings
   └─ Get started → draft created → /a/list-boat/{id}/details (overview: "List your boat in 5 quick steps")
        ├─ international          (address outside the US)
        └─ insurance-type          recreational (p2p) │ commercial
              ├─ p2p ─────────────────┐
              └─ commercial → commercial (certify $1M commercial cover)
        location → title → cancellation-policy → rules → features → photos → specifications      ← "Boat details"
        availability ─ default ───────────────┐                                                  ← "Availability"
                     └ custom → start-times ──┴→ advance-notice → multi-booking
        duration (operators, durations, fuel)                                                    ← "Operators and durations"
           ├─ renter-operated (bareboat) + p2p        → insurance (Buoy intro) → buoy-insurance-owner → buoy-insurance-boat
           ├─ renter-operated (bareboat) + commercial → buoy-insurance-owner → buoy-insurance-boat
           └─ captain only                             → straight to review
        review (every section with "Edit"; Edit → Continue comes back to review) → SUBMIT
        → finished / listing-pending → (admin approval) → manage area /boats/{id}/edit
```

Notes:
- **Progress bar:** 5 steps with commercial insurance, 4 with recreational (the "Insurance information" step drops out). A "Percentage of completion" bar sits at the bottom.
- **Saving:** every page saves to the draft. "Save and exit" is in the header, and the draft is continued later from *My boats → Complete listing*.
- **No pricing in the wizard** today. The bundle still has old `rental-type` / `pricing` step names, but they are not routed. Prices are set **after** listing, in the manage area (§4). Submit still checks "Boat pricing information missing", so Smart pricing fills them by default.
- **Submit errors:**
  - "Select one cancellation policy option."
  - "You must upload at least 4 photos."
  - "Please select preference options"
  - "Please select boat features options"
  - "Boat insurance information missing"
  - "Boat pricing information missing"

---

## 2. Every page

### 2.0 Start — "Tell us a little about your boat" (`/a/list-boat`)

| Field | Type | Details |
|---|---|---|
| Boat address | Google Places autocomplete | Fills hidden fields: lat, lng, street_number, street_1, street_2, city, state, postal_code, country_code |
| Year | dropdown | 2026 → 1967 (60 years) |
| Make | dropdown, enabled after Year | **1,041 makes**: 5 popular first (Bayliner, Beneteau USA, Catalina, Sea Ray Boats, Yamaha), then A–Z, then **Other** |
| Model | dropdown, enabled after Make | Models for that year + make (2006 Cruisers Yachts: 320 Express Stern Drive, 340 Express Stern Drive, 385 Motoryacht, 477 Sport Sedan) + **Other** |
| Enter model | text, required | Only when Model = Other |
| Estimated monthly earnings | read-only | $0 → **$3,304** once year / make / model were set; also shown in the wizard header |

Button: **Get started** (creates the draft).

### 2.1 Overview (`/details`)
"Let's get started — You'll be done in less than 15 minutes."
- The 5 steps: Boat details (4 boat photos minimum) · Availability · Operators and durations · Insurance information if applicable · Review and submit.

### 2.2 Insurance type (`/insurance-type`)
"What type of insurance do you have?" (radio, required)
- **I have recreational boat insurance** — *Most common*, "Select this if you own a personal boat." → `p2p`
- **I have commercial charter insurance** — "Select this if you run a boat rental or charter business." → `commercial`

### 2.3 Commercial (`/commercial`, commercial only)
- Text: a current commercial policy insuring boat and passengers, at least **$1,000,000**. Boatsetter may ask for a copy at any time.
- Company website (optional).
- Checkbox: **I certify that I have commercial insurance.**
- No document upload.

### 2.4 Location — "Where is your boat?" (`/location`)
"We only show your boat's exact location to guests once you've confirmed their trip."

**Location type** (dropdown, 9). The fields it shows:

| Location type | Marina name | Slip number | Dock / Pier |
|---|---|---|---|
| Marina slip | ✓ | ✓ | ✓ |
| Marina mooring | ✓ | ✓ | – |
| Marina dry storage | ✓ | – | – |
| Marina rack storage | ✓ | – | – |
| Residence trailer · Residence slip · Residence mooring · Storage facility · Storage trailer | – | – | – |

Every type then shows the address:
- Country / Territory: 233 options, US first.
- Address: autocomplete, prefilled from the start page.
- Street address 2, City, State / Province, Zip code.
- Hidden map-pin fields: `owner_pin_latitude` / `owner_pin_longitude`.

### 2.5 Title & description — "Your boat listing" (`/title`)
- **Listing title**: max 70 characters, with a live "N characters remaining".
- **Description**: textarea, no minimum length.
- Guidance beside the description:
  - where the boat can go;
  - itinerary ideas;
  - extra perks / toys;
  - extra costs such as fuel;
  - delivery areas.
- Both are required; Continue does nothing while one is empty.

### 2.6 Cancellation policy (`/cancellation-policy`)
- Radio: **Flexible** (*Recommended*, default), **Moderate**, **Strict**.
- The texts are the same as our PLAN §7.4 tiers.

### 2.7 Rules — "What is allowed on your boat?" (`/rules`)
- 10 checkboxes, **all ticked by default** (= allowed): Shoes, Glass bottles, Fishing, Pets, Kids under 12, Smoking, Alcohol, Liveaboard, Swimming, Red wine.
- The owner unticks what is not allowed.

### 2.8 Features (`/features`)
Three groups of checkboxes (none ticked by default):
- **Features (16):** Air conditioning, Anchor, Bathroom, Bluetooth audio, Cooler / ice chest, Deck shower, Fish finder, Grill, Livewell / baitwell, Refrigerator, Rod holders, Shower, Stereo, Stereo AUX input, Swim ladder, WiFi.
- **Extras (14):** Children's life jackets, Fishing gear, Floating island, Floating mat, Inflatable toys, Jet ski, Kayaks, Paddleboards, Snorkeling gear, Tender, Tube, Wakeboard, Water scooter / seabob, Waterskis.
- **Navigation (6):** Bow thruster, Depth finder, GPS, Radar, Sonar, VHF radio.

### 2.9 Photos (`/photos`)
- Drag & drop or click. Slots: "Upload cover photo", Photo 2, 3, 4. **4 minimum**, enforced at submit; "Skip for now" is allowed during the wizard.
- Tips: phone camera is fine, no flash, tidy up, landscape, no extreme close-ups. Start with one photo of the whole boat, then details and interior.
- Rules: no company logos or phone numbers (they will be edited or removed). Width 960 px+ looks best. JPG / PNG / GIF / BMP, up to 245 MB.

### 2.10 Specifications (`/specifications`)
"Some are already populated based on information you provided earlier."

| Section | Field | Type / options | Rules |
|---|---|---|---|
| General | Boat registration number / HIN | text | Format checked: `IL1234AB` OK, `IL 1234 AB` → "Invalid registration number" |
| | Year | dropdown (prefilled) | |
| | Make | dropdown, 1,041 (prefilled) | If the model was "Other", it comes back as Other and must be chosen again |
| | Model | dropdown by year + make, + Other → **Enter model** text | |
| | Type | Power / Sail (Power default) | |
| | Length (ft) | number | |
| | Category | dropdown, depends on Type | **Power (43):** Airboat, Aluminum fishing, Angler, Bow rider, Bass boat, Catamaran, Center console, Classic, Commercial, Convertible, Cruiser, Cuddy cabin, Deck boat, Dive boat, Downeast, Dual console, Duck boat, Electric, Express cruiser, Fish and ski, Flats boat, Flybridge, Houseboat, Inflatable outboard, Jet boat, Jet ski / personal water craft, Jon boat, Mega yacht, Motor yacht, Offshore sport fishing, Performance, Performance fishing, Pilothouse, Pontoon, Rigid inflatable, Runabout, Saltwater fishing, Ski and wakeboard, Skiff, Trawler, Walkaround, Weekender, Other. **Sail (14):** Catamaran, Classic, Cruiser, Cruiser racer, Cutter, Daysailer & weekender, Ketch, Motorsailer, Racer, Sloop, Schooner, Trimaran, Yawl, Other |
| Engine | Number of engines | number | |
| | Total horsepower | number | |
| | Engine year | dropdown 2026 → 1967 | |
| | Engine type | Direct drive, Jet drive, Inboard, Inboard outboard, Outboard, V drive, Other | |
| Capacity | Passenger capacity | number | Recreational: **max 12** ("Capacity must be less than 13 to be accepted by Buoy") |

### 2.11 Availability (`/availability`, `/start-times`)
"Select the available days and start times."
- The times are **start (departure) times**. The return depends on the trip length. Start times may be limited for sunrise / sunset insurance rules.
- **Default availability**: Monday to Sunday, departures 8:00 AM – 6:00 PM.
- **Custom availability** opens a page (`/start-times`) with one accordion per weekday:
  - a checkbox turns the day on or off; when closed, the accordion shows a summary such as "8:00 AM - 6:00 PM";
  - inside are **start-time checkboxes every 30 minutes from 5:00 AM to 10:00 PM** (35), with 8 AM – 6 PM ticked;
  - Continue stays disabled until something is changed.

### 2.12 Advance notice (`/advance-notice`)
- Radio: **1 hour** (default), 3 hours, 6 hours, 12 hours, 1 day, 3 days, 7 days.
- "We recommend 6 hours or less to capture last-minute bookings."

### 2.13 Multiple bookings (`/multi-booking`)
- "Do you want to allow multiple bookings in one day?": **No** ("can only be booked once a day") / **Yes** (*Most common*).
- Yes → "How much time do you need in between bookings?": **30 min** (default), 1 hour, 2 hours, 3 hours.

### 2.14 Operators and durations (`/duration`)
- **Who can operate your boat?** One or both:
  - **Renter operates my boat** (*Most common*, ticked);
  - **USCG-certified captain operates my boat**.
  - Boatsetter provides a list of network captains to approve. Owners or their own captains can join the captain network.
- **Durations to offer:** 2 hours, 3 hours, 4 hours ("half day"), 6 hours, 8 hours ("all day"), all ticked. At least one is required.
- **Who pays for fuel?** Renter pays (default) / I pay.
- The data also has: **Is this a captained fishing charter?** (yes / no), **Will you use the Boatsetter Captain Network?**, **Who chooses the captain** (I choose / Renter chooses) and **Captain payment collection** (via Boatsetter / at the dock). They show in review and manage.

### 2.15 Insurance by Buoy (bareboat only)
- **Intro** (`/insurance`, recreational): Boatsetter's partner Buoy insures owners of personal boats; a small % of the listing price pays for it.
- **Owner** (`/buoy-insurance-owner`):
  - years operating a rental business (number);
  - company name (optional);
  - yes / no: claims over $50,000, 2+ losses or a total loss in 3 years; criminal convictions (10 years, felony ever); passengers above the USCG capacity plate; bankruptcy in 5 years; registered business entity; Markel policy;
  - "By clicking continue, I certify…".
- **Boat** (`/buoy-insurance-boat`):
  - boat value ($);
  - yes / no: customised / modified; keeps an underlying insurance policy; attachments (flyboard, parasail); seaworthy on all charters; racing / experimental; special classification (submersible, hovercraft, jet-ski…); USCG-licensed captain ("boats over 33 ft must be captained");
  - checkbox: agree to the policy terms;
  - certify.

### 2.16 Review (`/review`)
"Please review your boat listing before submitting."
- **Sections, each with Edit:** Specifications · Location · Title / Description · Cancellation policy · Preferences (allowed items) · Features · Photos · Availability (start times per day, advance notice, multi-booking + buffer) · Operators and durations (fishing charter?, renter operates, captain operates, captain network, durations, fuel) · Insurance (owner + boat answers).
- **Boatsetter standards:** keep the boat maintained and legal; clean and refuel it before every trip. "By clicking Submit you agree".
- **SUBMIT** → pending approval.

---

## 3. My boats (`/manage_boats`)
- Cards with a status: **PENDING** (Edit listing), **INCOMPLETE** (Complete listing · Delete listing).
- "List a new boat".

## 4. Manage a boat (`/boats/{id}/edit` → `/a/boats/{id}/manage/{section}`)
Header: boat name, "Listing is Pending Approval", Preview. Tabs:
- **Calendar**: a month view; per-date availability and **custom price** (legend: Unavailable · Discount · Price increase · Ready to approve · Approved); Export calendar.
- **Availability**: start times, advance notice, multi-booking + buffer, each with Edit.
- **Durations & pricing**:
  - booking options (renter / captain / captain network);
  - **Pricing type: Smart boat pricing ⇄ Custom pricing** (a toggle). Smart is "based on your local market, boat and other key details"; Custom is "set your prices";
  - a **price for each offered duration** (2 h $473, 3 h $680, 4 h $867, 6 h $1,241, 8 h $1,575 in the example). This is a package price per length, not an hourly rate;
  - a **captain price per duration** when the owner supplies the captain (not the network);
  - a payout breakdown;
  - the fuel policy;
  - an agreement checkbox ("Passenger for Hire & Charter Vessel Operations Agreement").
- **Add-ons** (new):
  - "NEW ADD-ON": the add-on (one of 16: Bring a pet, Cooler / ice chest, Drone video, Fish cleaning, Fishing gear, Floating island, Floating mat, Ice, Inflatable toy, Kayak, Paddleboard, Photography, Snacks, Snorkeling gear, Transportation, Tube);
  - price; **Per passenger / Per hour / Per booking**; max quantity 1–99; description;
  - priced by the owner, paid out separately.
- **Instant book**: off by default.
- **Boat details**, **Insurance**: status Active.

---

## 5. Our wizard compared (resources/js/pages/list-boat.js, config/listing.php)

Our Phase 1 wizard already follows this flow: insurance type → location → title → policy → rules → features → photos → specs → availability → advance notice → multi-booking → operators & durations → **our own Pricing step** → insurance details → review. To match Boatsetter:

| Area | Boatsetter | Ours now | Change |
|---|---|---|---|
| Start page | Address autocomplete + year → make → model + earnings estimate, before the wizard | The wizard starts at insurance type | Add the start page. It needs a **Google Places API key** (paid per use) and a make / model source |
| Make / model data | 1,041 makes; models by year + make | 26 makes, free-text model | Decision: a bigger curated make list + "Other" free text, or a licensed boat database (NADA / J.D. Power style). We can't take Boatsetter's data |
| Location types | 9 (adds Storage facility, Storage trailer) and fields by type | 8 (with "Other") | Same 9 types and the conditional marina / slip / dock fields; country list |
| Map pin | Hidden owner pin lat / lng | – | Optional "drag the pin" map |
| Rules | "What is **allowed**", all ticked | Admin form asks "Not allowed"; wizard similar | One meaning: allowed, all ticked by default |
| Extras / navigation | 14 extras, 6 navigation (with Sonar) | 8 extras, 8 navigation (Chartplotter, Autopilot, Compass) | Align the lists (amenity groups are admin-editable since 3.7) |
| Categories | 43 power / 14 sail, by Type | 29, one list | Two lists by Type |
| Specs | Registration (format checked), engine year, passenger capacity | Similar; HIN not checked | Add the format check, engine year; capacity rules (recreational ≤ 12; our COI ≤ 6 warning) |
| Availability | **Start times** (every 30 min, 5 AM – 10 PM, per day) | Opening hours per day (opens / closes) | Bigger change: move to start-time slots, or keep hours and derive slots. Affects AvailabilityService (4.2) |
| Durations | 2 / 3 / 4 / 6 / 8 h, at least one | Same set | – |
| **Pricing** | **Per-duration package prices** (Smart or Custom), after listing; captain price per duration | Hourly rate + weekend markup + optional fixed price per length, in the wizard | Decision: per-duration price as the main model. QuoteService already supports fixed prices per length (4.1). "Smart pricing" needs market data: a suggested-price table we set could stand in |
| Captain options | Renter / captain (both possible), captain network, who chooses, payment at dock / via platform, fishing charter | Captain source owner / platform, renter-operated flag | Map: renter-operated = bareboat; platform captain = "network"; at-dock payment probably not for us (we collect everything) |
| Insurance | Buoy p2p insurance questionnaire (owner + boat) or commercial self-certify | Our own insurance step + commercial details | We have no Buoy. Keep our step: commercial → certify (+ optional policy upload); recreational → the client decides (we likely require commercial cover or proof) |
| Add-ons | **Owner-defined add-ons** per boat (16 types, per passenger / hour / booking, max qty) | Platform add-ons only (admin) | Decision: allow owner add-ons per boat (payout to the owner) or keep platform add-ons |
| Calendar | Per-date custom price, discount, unavailable | Blocked times only | Phase 6/7: date overrides (price ±, closed) |
| Instant book | Per boat, off by default | – (every booking pays straight away) | Decision: request-to-book vs instant |
| Earnings estimate | On the start page + header | On our Pricing step | Show it earlier too (rule-based) |

## 6. Decisions (user / client, 2026-11-24)

1. **Pricing: like Boatsetter.**
   - Each offered duration has its own package price (+ a captain price per duration when the owner supplies the captain).
   - A "Suggested ⇄ Custom" pricing toggle. We have no market data, so "suggested" prices come from a rule table the admin keeps.
   - Prices are set in the boat's manage area after listing, as on Boatsetter. QuoteService already prices fixed per-length amounts (4.1).
2. **Insurance:**
   - The client will arrange Buoy, or find an alternative later.
   - We build the same questions (owner + boat, plus the commercial self-certification) and store the answers for the insurer.
3. **Availability:** start times (every 30 min) vs opening hours. Explained to the user; **answer pending**. It affects 4.2.
4. **Owner add-ons: like Boatsetter.**
   - Owners create add-ons per boat: a type from a list, a price per passenger / per hour / per booking, a max quantity and a description. They are paid out to the owner separately.
   - The admin's platform add-ons stay for house boats.
5. **Request to book:**
   - A booking request is approved by the owner first. Boatsetter's per-boat "Instant book" is off by default.
   - Built with the owner side (Phase 6). The booking statuses get room for it in 4.4 (a request waiting for the owner, then accepted / declined / expired).
6. **No paid APIs, Chicago only:**
   - Make: a large researched make list + "Other"; model is free text.
   - No Google Places. Location = a list of Chicago harbors / marinas we keep (+ "Other" with a typed address, Chicago, IL prefilled).
   - The earnings estimate is rule-based.
7. **Wording:** our own text, with the same fields and logic as Boatsetter.

## 6a. The original questions
1. **Pricing model:** a price per duration (Boatsetter) instead of hourly × hours? Smart pricing: offer suggested prices from our own table?
2. **Insurance:** without Buoy, what do recreational owners need (proof of their own policy, upload)? Is commercial cover required?
3. **Availability:** move from opening hours to start-time slots (every 30 min) like Boatsetter?
4. **Owner add-ons:** may owners create their own add-ons with their own prices?
5. **Instant book vs request to book** for owner boats?
6. **Make / model list:** a curated list + Other, or pay for a boat database? And a Google Places key for address autocomplete (paid per use)?
7. **Wording:** we keep the structure and write our own text (no copying of Boatsetter's copy).
