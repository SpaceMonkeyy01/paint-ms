# paint-ms — Claude Code guide

Internal paint sub-warehouse + paint-station system for a sign manufacturer (BlueCascade / Signize).
Replaces two Google Sheets tools: **PMS** (store: item master, BOM per order, issue vs BOM, colour log,
costing) and **Paint Consumption Tool** (station: scale-weight consumption, g→L via density, wastage).

## Stack

- Laravel 12, Inertia + React + Tailwind (Breeze), MySQL. PWA later — every screen must work on a phone.
- Roles: `admin`, `store`, `painter` (`App\Enums\Role`, `users.role`). Gate every route by role.
- Tests: Pest. Every ledger-touching change needs a test that asserts stock on hand before/after.
- Never edit `database/seeders/data/*.csv` by hand — regenerate with `tools/extract_seed_data.py`.

## Domain rules — do not violate

1. **Stock is derived, never stored.** `stock_on_hand = SUM(transactions.qty)` per item over
   **stock-moving types** (`opening`/`receipt`/`issue`/`adjust` — `TransactionType::affectsStock()`).
   `consumption`/`wastage` are scale readings of material *already issued* to an order; counting them
   would deduct the same paint twice. No `stock` column on `items`, no cached totals. Use
   `App\Services\StockService`.
2. **`transactions.qty` is signed.** `opening`/`receipt`/`adjust(+)` add; `issue`/`consumption`/
   `wastage`/`adjust(−)` remove. `TransactionType::sign()` is the source of truth. Never store abs values
   for issues.
3. **One ledger for store and station.** `issue` = store hands material to an order. `consumption` =
   painter's scale reading (start_wt − end_wt) at a slot. `wastage` = container gap. All three carry
   `order_id`, so BOM → issued → consumed → wasted → costed is one chain per order.
4. **Snapshot rates.** Every transaction stores `rate` (Rs/gram at the time) and `value = |qty| × rate`.
   Cost reports sum `value`; never multiply current `items.rate_per_uom` by historical qty.
5. **BOM categories vs issue pools.** Odoo BOM lines are per *category* (`bom_lines.bom_category`);
   materials are issued per *pool* (`bom_categories.issue_pool`). Reconcile at pool level. Clubbed
   categories (Epoxy Primer + Epoxy Thinner + Epoxy Hardner → "Epoxy Set") are one pool. Categories
   with `ignored = true` ("Paint Miscellaneous cost") are never issued.
6. **Over-BOM issue needs a reason and an authoriser.** `issue_type = variance` requires `remarks` and
   `authorized_by`. `bom` issues cannot exceed remaining allowance for the pool.
7. **Grams are the unit.** Everything in the ledger is grams. Litres are display-only, computed as
   `grams / 1000 / density_kg_per_l`. If density is null, show grams and flag it — never guess density.
8. **Item identity is `items.code`** (internal/iFlow code, e.g. `2214`, `2245 / 2673`). Never match on
   name. `odoo_id` is the Odoo product id and may be null.
9. **Orders are identified by `orders.code`** (`BS-ET-16188`, `BS-US-2501 A`). Codes contain spaces
   and suffixes — treat as opaque strings, trim, case-sensitive.
10. **Keep raw BOM input.** `orders.bom_source` holds the original BOM string for audit. All parsing
    goes through `App\Services\BomStringParser` (repeated categories are **summed** — the string is a
    matt block + a gloss block; last-wins is a bug). Keep the raw Airtable payload per sync.

## Layout

```
app/Enums/          Role, TransactionType, IssueType
app/Models/         Item, Order, BomLine, BomCategory, Transaction, ColourBatch, ColourBatchComponent
app/Services/       StockService (stock list, LOW/OUT, per-order pool reconciliation),
                    BomStringParser (the only BOM string parser) — add services here, keep controllers thin
docs/               airtable-sync.md — source of truth for the order/BOM feed
database/seeders/   LegacyImportSeeder + data/ (real legacy data; idempotent on natural keys)
tools/              extract_seed_data.py — xlsx → CSV, prints stock reconciliation vs legacy
resources/js/Pages/ Inertia pages, grouped by role: Store/, Station/, Admin/
```

## Build order (one runnable slice at a time, commit each)

1. ✅ Schema + legacy import.
2. **Store — Issue screen.** Pick order → pool table (BOM, issued, remaining) → add lines (item within
   pool, grams) → submit writes `issue` transactions in one DB transaction. Stock list with OK/LOW/OUT.
   Receipts + adjustments form.
3. **Station — Consumption screen.** Slot template per finish (W = colour tints, P = primers,
   A = additives), start/end weight per slot, live g / L / wastage / variance vs BOM. Writes
   `consumption` (+ `wastage`) transactions. Colour batch entry (Pantone target + component grams).
4. **Admin — costing + dashboard.** Per-order BOM cost vs issued vs consumed vs repaint, variance %,
   cost per sqft. Stock health, orders pending issue, variance exceptions.
5. **Airtable sync.** Orders + BOM lines in from the production base — see `docs/airtable-sync.md`
   for base/table/field IDs and the BOM string parsing rules. Odoo feeds Airtable; we do not talk
   to Odoo directly. Status-tag write-back comes later.

## Conventions

- Controllers validate with Form Requests, call a service, return Inertia. No business logic in
  controllers or React.
- Ledger writes go through a single `LedgerService::record(...)` (create it in slice 2) that enforces
  rules 2, 4, 6. Nothing else creates `Transaction` rows.
- Money: `decimal`, 2 dp. Quantities: `decimal`, 3 dp. Never float columns.
- Timestamps in Asia/Karachi for display; store UTC.
- Screens for `store` and `painter` are single-purpose and touch-friendly: big inputs, numeric keypad,
  no modals for the primary flow.
- Before finishing any task: `php artisan test`, then `php artisan tinker --execute=` a stock spot-check
  on an item you touched.

## Known data quirks (from legacy import)

- 498 distinct orders; legacy dashboard said 630 because BOM_Allocation had duplicate pastes.
  Six orders had differing BOMs across pastes — last paste won.
- 29 of 86 items have density. Others need admin entry before the station screen can show litres.
- Receipts/adjustments whose "Order Code" was a GRN number or free text are in `external_ref`.
- Legacy `Order_Costing` under-reported issued cost ~100× (rate scaling bug). Rule 4 exists because of it.
