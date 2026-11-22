# Chicago Yacht Booking — project rules

A Laravel 13 app for **chicagoyachtbooking.com**, a yacht charter marketplace. Customers book trips, owners list boats, and one admin runs everything.

- **Master plan:** [docs/PLAN.md](docs/PLAN.md). It holds the decisions, DB schema, business rules, the security standard (§8.2), the phases and the QA gate (§15). Read the relevant section before every step, and update the plan when a decision changes.
- **Design source:** `C:\Users\DETRIOTS\Desktop\Yacht-Web` (static HTML/Tailwind v3/JS mockup). Port it to Blade **1:1**. Never redesign anything without asking.

## How we work (the user's rules)

- Reply in **Roman Urdu + English** (technical terms in English). Code, comments and docs are in English.
- Plan first. Then build one small step at a time:
  1. Run the QA gate.
  2. Report the result.
  3. **Wait for the user's go-ahead** before the next step.
- All work happens directly on `main` (no phase branches — the user's decision, 2026-11-22). **Never commit or push unless the user asks.**
- **Ask before adding any composer or npm package.**
  - Approved: `laravel/fortify`, `stripe/stripe-php`, `intervention/image-laravel`, `barryvdh/laravel-dompdf`, `lucide` (npm), and `laravel/boost` (**dev only**, added before Phase 2 — PLAN D17).
  - **Not used, by the user's decision:** Filament, Livewire, Alpine.js, Spatie packages.
- **Laravel Boost:** use its MCP tools (version-correct docs search, DB schema, logs, browser logs). Its guidelines are the auto-generated `<laravel-boost-guidelines>` block at the end of this file. Where they differ from the rules above, **the rules above win** (e.g. we deploy to Namecheap, not Laravel Cloud; `docs/PLAN.md` is updated every step; `vendor/bin/pint --test` is part of the QA gate). Don't edit inside that block — `php artisan boost:update` regenerates it.
  - Prefer plain Laravel features (controllers, FormRequests, Policies/Gates, Blade, Mail, Queues) and small services we write ourselves.

## Stack

- **PHP:** 8.5 locally (XAMPP), **8.4 in production** (Namecheap). Composer is pinned with `config.platform.php = 8.4.1`.
  - Do not use PHP 8.5-only features: the pipe operator `|>`, `clone()` with properties, `array_first()`/`array_last()`, `#[\NoDiscard]`.
- **Database:**
  - Local: MySQL/MariaDB (XAMPP, database `yacht_db`).
  - Production: **MariaDB 11.4**.
  - Write standard SQL only. Use Eloquent and the query builder, with no DB-specific functions.
- **Frontend:**
  - Blade + Tailwind **v4**. The theme lives in `resources/css/app.css` `@theme`.
  - Vanilla JS ES modules live in `resources/js`.
  - **No inline scripts or styles**: the CSP will block them. The only exceptions are nonce'd tags added in Phase 2.
- **Icons:** `lucide` npm. Register every icon you use in `resources/js/lib/icons.js` (brand icons are custom there). Markup: `<i data-lucide="name"></i>`.
- **Fonts:** self-hosted through the `laravel-vite-plugin` fonts helper (`vite.config.js`), rendered with `@fonts` in the layout.
- **Queue:** the database driver. On shared hosting, one cron job runs `schedule:run`, and the scheduler starts `queue:work --stop-when-empty`.

## Conventions

- **Money** is integer cents (`*_cents`). **Rates** are basis points (`*_bp`; 2000 = 20%). Format only at the edge: `resources/js/lib/money.js` in JS, and a PHP Money helper in later phases.
- **Time:** store UTC. Display in `config('app.display_timezone')` (America/Chicago). Date-only values are `YYYY-MM-DD` strings (`resources/js/lib/dates.js`).
- **Public IDs:** use a `ulid` column in URLs. Never expose numeric IDs.
- **Code structure:**
  - Business logic lives in `app/Services` and `app/Actions`. Controllers stay thin.
  - Each model's status changes go through one service method, which also writes the history.
  - Use `app/Enums` for statuses, one FormRequest per form/endpoint, one Policy per model, and API Resources for every JSON response.
- **Phase 1 demo code** (delete when the real data arrives):
  - Demo data lives in `config/demo*.php`, read through `App\Support\Demo\*` (shaped like the future models, dates relative to "now").
  - `<form data-demo-form="message">` = a form whose endpoint doesn't exist yet: the browser validates it, then shows a toast. Remove the attribute when the endpoint is built.
- **Components with built-in classes take props, never a second conflicting class:** `x-button` → `size`/`variant`, `x-admin.pill` → `tone`, `x-admin.table` → `min`.
- **Files:**
  - Uploads go through `MediaService`. The DB stores **only the relative path**.
  - Private documents go on the `local` (private) disk and are served only through a controller that checks a Policy.
  - Design assets live in `public/images`, `public/video` and `public/favicon.svg`.

## Security standard — every form and JSON endpoint (summary of PLAN §8.2)

1. **Routes and requests:**
   - Endpoints live in `routes/web.php` (session + CSRF), never in a stateless `api.php`.
   - JS calls go only through `resources/js/lib/http.js`.
   - `GET` never changes data.
2. **Validation:**
   - A FormRequest with `authorize()` (Policy/Gate) and `rules()`.
   - Use `$request->validated()` only. Never `$request->all()`. Models have an explicit `$fillable`.
   - **Never accept from a request:** prices or totals, `role`, `status`, `owner_id`, `user_id`.
   - Strict rules: max length on every string, `Rule::enum`, scoped `exists`, integer ranges, `distinct` arrays with a `max` size.
3. **Output:**
   - API Resources whitelist the fields that go out.
   - Another user's record returns **404**.
   - No stack traces outside local.
4. **Rendering:**
   - JS renders with `textContent` or the auto-escaping `html` template from `resources/js/lib/dom.js`. Never assign untrusted data to `innerHTML`.
   - Blade uses `{{ }}`. Page data is embedded with `@js` / `Js::from`.
5. **Abuse protection:**
   - A named rate limiter on every endpoint.
   - An idempotency key + DB transaction for holds, payments, refunds and payouts.
   - Admin table sort and filter parameters are checked against a whitelist. Never put raw SQL together with user input.
6. **Sensitive admin actions:** `password.confirm`, a written reason and an audit-log entry.
7. **Tests for every endpoint:** guest, wrong user (404), wrong role, invalid input (422), valid input, rate limit (429), duplicate request.

## Porting mockup classes from Tailwind v3 to v4

- The shared CSS layer in `resources/css/app.css` restores the v3 defaults: gray-200 borders, a pointer cursor on buttons and gray-400 placeholders.
- The `container` matches the mockup: centered, padding 1rem / 1.5rem (sm) / 2rem (lg), max width 1320px.
  - Because of this, the `2xl` breakpoint is **1320px** (not 1536px). The mockup never uses `2xl:`.
- **Checked in Phase 0** by compiling all 813 mockup classes with our v4 theme. Every class produces CSS, but two keep the same name and render at a different size:
  - `shadow-sm` (used 7×) → write **`shadow-xs`**. In v4, `shadow-sm` is v3's larger `shadow`.
  - `backdrop-blur-sm` (used 1×) → write **`backdrop-blur-xs`**. In v4, `-sm` is 8px instead of 4px.
- These look identical in v4, so keep them as they are: bare `shadow`, `rounded`, `backdrop-blur`, and `bg-gradient-to-*` (still supported, same as `bg-linear-to-*`).
- The mockup doesn't use these, but follow the v4 names when writing new code: `rounded-xs`, `blur-xs`, `outline-hidden`, `ring-3` (a bare `ring` is 1px in v4).
- **Never put two conflicting utilities on one element** (e.g. `inline-flex` + `hidden`, `px-6` + `px-7`).
  - v4 orders utilities differently from v3, so a different class wins.
  - For `<x-button>`, change the size with the `size` prop. Pass responsive display classes (`hidden sm:inline-flex`); the component then drops its own `inline-flex`.
- Line heights are restored to v3's fixed rem values in `@theme`, so `text-[11px]` inside a `text-sm` parent keeps the mockup's spacing. Found in the Phase 1.1 QA: the promo bar was 4px shorter.
- **Responsive font sizes and line height (v3 behaviour).** In v3, a responsive size such as `md:text-5xl` also reset the line height, overriding `leading-tight`. To render like the mockup, write the v3 result explicitly:
  - `leading-tight md:text-5xl md:leading-none` for 5xl and up.
  - `text-base leading-relaxed sm:text-lg sm:leading-7` for text-lg.
  - `<x-section-heading>` already does this. Use `:tight="false"` for mockup headings that had no `leading-tight`.
- **Mockup buttons built from `CYB.cls.*` plus extra `px-`/`py-` classes:** the winner depended on the CDN's class order. Measure the rendered padding in the mockup and use the matching `<x-button size>`:
  - md = px-6 py-3
  - lg = px-7 py-3.5
  - xl = px-8 py-4
  - block = full width, px-6 py-4
- **Scroll containers:** an `overflow-x-auto` wrapper that holds `sr-only` or absolutely positioned elements must also be `relative`. Otherwise those elements escape the clip and widen the whole page on mobile. Found in the 1.2 QA: the fleet page became 671px wide at 390.
- **Fonts:** the Google provider (variable fonts) gives the same glyph widths as the mockup. Bunny's static files wrapped text differently.
- **Prices on marketing pages** (estimates, examples) show whole dollars (`money($c, 'never')`, `formatCents(c, {cents: 'never'})`). Checkout and receipts show exact cents.
- After porting a page, run the parity QA:
  - Compare section heights of the mockup and the Laravel page at 390 / 768 / 1280 / 1440 px.
  - Run an element-level diff for any mismatch.
  - In Git Bash, set `MSYS_NO_PATHCONV=1` before passing `/paths` to Node scripts. Otherwise `/about` becomes `C:/Program Files/Git/about`, and the "app" measurement silently measures the previous page.

## Commands (Windows, Git Bash or PowerShell)

| Command | Use |
|---|---|
| `php artisan test` | PHP tests |
| `npm run test:js` | JS unit tests (Node built-in runner, `tests/js`) |
| `vendor/bin/pint` | Code style (`--test` to check only) |
| `npm run build` / `npm run dev` | Vite build / dev server |
| `php artisan serve` | Local app at http://localhost:8000 (XAMPP MySQL must be running) |
| `php artisan optimize:clear` | Clear cached config/routes/views. **Never run `php artisan optimize` locally** (production only): with a cached config, `.env` changes are ignored, and tests can't switch to their in-memory SQLite database. |

## QA gate (definition of done, PLAN §15)

A step is done only when **all** of these are true:

1. Its tests pass.
2. Pint is clean.
3. There are no console errors or CSP violations, and no horizontal overflow at 390 / 768 / 1280 / 1440 px.
4. Every new form and endpoint passes the security standard above.
5. Migrations roll back.
6. The manual checklist has been run.
7. Results are reported to the user, and the user approves.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
