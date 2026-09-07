# Paint Management System (paint-ms)

Consolidated replacement for the two Google Sheets tools:
- **PMS** (sub-warehouse: item master, BOM per order, issue vs BOM, colour log, costing)
- **Paint Consumption Tool** (station: scale-weight consumption, g→L via density, wastage)

One item master, one order/BOM feed, one signed stock ledger.

## Slice 1 — schema + legacy import (this drop)

```
app/Enums/                 Role, TransactionType, IssueType
app/Models/                Item, Order, BomLine, BomCategory, Transaction, ColourBatch, ColourBatchComponent
app/Services/StockService  stock on hand, LOW/OUT list, per-order BOM vs issued vs consumed
database/migrations/       7 migrations
database/seeders/          LegacyImportSeeder + data/*.csv (already extracted from your two xlsx files)
tools/extract_seed_data.py re-run this if the sheets change before cut-over
```

### Setup

```bash
laravel new paint-ms --breeze --stack=react --database=mysql   # Laravel 11, Inertia + React + Tailwind
cd paint-ms
# copy app/, database/, tools/ from this drop over the fresh project
php artisan migrate
php artisan db:seed --class=LegacyImportSeeder
php artisan tinker --execute="dump(app(App\Services\StockService::class)->stockList()->where('status','!=','OK')->pluck('stock_on_hand','name'))"
```

Add `role` to the `User` model `$fillable` and cast it to `App\Enums\Role`.

### Ledger rules

- `transactions.qty` is **signed**: opening/receipt/+adjust add, issue/consumption/wastage/−adjust remove.
- Stock on hand is always `SUM(qty)` per item — never stored, never typed.
- `issue` = store hands material to an order. `consumption` = painter's scale reading (start − end).
  `wastage` = container gap. All three hang off the same `order_id`, so BOM → issued → consumed → wasted → costed is one chain.
- `rate` is snapshotted on every transaction; `value = |qty| × rate`. Cost reports sum `value`, so the
  legacy Order_Costing scaling bug can't recur.
- BOM categories map to issue pools via `bom_categories`; clubbed categories (Epoxy Set) reconcile at pool level.

### What the import verified

- 86 items, 498 orders, 2,193 BOM lines, 60 colour batches (200 components), 1,057 transactions
  (869 issue, 121 receipt, 28 adjust, 39 opening).
- Ledger-derived stock matches legacy *Stock In Hand* for **86/86** items.
- BOM_Allocation had the same order pasted more than once (132 dup rows; 6 orders with differing BOMs) — last paste wins.
- Receipts/adjustments whose "Order Code" wasn't an order (GRN numbers, free text) landed in `external_ref`.
- Densities came from the Consumption Tool inventory (29 items have one); rest are null until admin fills them.

## Next slices

2. Store keeper: Issue screen (order → BOM lines with remaining → items per pool → over-BOM needs reason + authoriser), stock list.
3. Painter: station consumption screen (slot template per finish, start/end weight, live g/L/wastage/variance).
4. Order costing + dashboard, then Odoo sync (orders + BOM lines in, consumption back).
