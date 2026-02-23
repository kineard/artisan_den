# Refactoring Plan – artisan_den

## Current size (approx.)

| File | Lines | Notes |
|------|-------|------|
| `index.php` | ~1,035 | Routing, 14+ POST handlers, HTML mixed in |
| `includes/inventory-functions.php` | ~793 | Queries, calculations, snapshots in one file |
| `js/main.js` | ~1,017 | All event handlers, API calls, chart logic |
| `css/style.css` | ~970 | All styles in one file |
| `includes/inventory-section.php` | ~253 | OK |
| `includes/modals.php` | ~313 | OK |

Yes, refactoring is worth considering. The code works but is verbose and will be harder to maintain as you add features. Below is a practical order to do it in.

---

## 1. Extract POST handlers from index.php (high impact)

**Problem:** index.php does routing, many POST actions, and output in one place.

**Idea:** Move POST handling into one or two include files.

- Create `includes/post-handlers.php`: all the `if (isset($_POST['save_product']))` etc. blocks. Each block parses input, calls a function from helpers/inventory-functions, then sets `$successMessage`/`$errorMessage` and redirect or JSON. index.php would do `require_once 'includes/post-handlers.php';` near the top (after loading config/helpers).
- Optionally split into `includes/post-handlers-kpi.php` and `includes/post-handlers-inventory.php` if you prefer smaller files.

**Result:** index.php shrinks by ~400–500 lines and reads as “load deps → run post handlers → load view”.

---

## 2. Split JavaScript into modules (high impact)

**Problem:** main.js is 1,000+ lines: KPI spreadsheet, inventory UI, daily sales, chart toggles, modals.

**Idea:** Split by feature (no build step required if you use multiple `<script>` tags in order).

- `js/api.js` – all `fetch()` calls and URL building (save_daily_sale, update_daily_on_hand, etc.).
- `js/inventory.js` – daily sale inputs, on-hand updates, order modal, “Order” button.
- `js/chart.js` – KPI chart init, inventory chart toggles, legend.
- `js/main.js` – DOMContentLoaded: attach listeners and call init functions from the other files.

Use a single global namespace if you don’t use a bundler, e.g. `window.ArtisanDen = { api: {}, inventory: {}, chart: {} };`.

**Result:** Easier to find and change behavior; main.js becomes a short “wire-up” file.

---

## 3. Split inventory-functions.php (medium impact)

**Problem:** One large file for all inventory-related DB and logic.

**Idea:** Split by responsibility:

- `includes/inventory-queries.php` – getInventoryForStore, getInventorySnapshotsMap, getSalesByProductDate, etc.
- `includes/inventory-calc.php` – calculateInventoryStatus, calculateSuggestedOrder, ROP/target/suggested order helpers.
- `includes/inventory-snapshots.php` – saveInventorySnapshot, recalcAndSaveOnHandForRange, saveProductDailySales, saveProductDailyPurchase.

Keep `inventory-functions.php` as a thin file that only does `require_once` of the three above (so existing `require_once 'includes/inventory-functions.php'` still works).

**Result:** Easier to test and change one area (e.g. snapshot logic) without scrolling a 800-line file.

---

## 4. Extract HTML sections from index.php (medium impact)

**Problem:** index.php still contains large blocks of HTML (dashboard grid, chart markup, inventory section include, etc.).

**Idea:** Move view fragments to includes.

- `includes/views/dashboard.php` – everything from the dashboard header through the KPI spreadsheet and chart.
- `includes/views/entry.php` – data entry view if different.
- Keep `includes/inventory-section.php` and `includes/daily-onhand-section.php` as they are; optionally move the “inventory chart” block into `includes/views/inventory-chart.php` and include it from index.

**Result:** index.php becomes: init/params → post handlers → pick view → include header + view + footer.

---

## 5. CSS structure (lower priority)

**Problem:** One large style.css.

**Idea:** Split when you touch styles anyway (e.g. when adding a new feature):

- `css/base.css` – reset, body, layout, typography.
- `css/components.css` – buttons, forms, modals, tables.
- `css/inventory.css` – inventory table, daily grid, status badges.
- `css/chart.css` – chart containers, legend, controls.

In header.php, link multiple stylesheets in that order. Optional: one “build” step that concatenates them for production.

**Result:** Easier to find and edit styles for one area.

---

## Suggested order

1. Do **§1 (POST handlers)** first – one clear change, big reduction in index.php size.
2. Then **§2 (JS split)** – improves maintainability without changing behavior.
3. Then **§3 (inventory-functions)** – makes future inventory changes safer.
4. **§4 (HTML views)** and **§5 (CSS)** when you’re adding or changing those parts of the UI.

Do refactors in small commits (e.g. one “Extract POST handlers” commit) so you can run the app and fix any missed references. If you want to start with §1, we can outline the exact moves (which blocks to cut from index.php and what to put in `includes/post-handlers.php`).
