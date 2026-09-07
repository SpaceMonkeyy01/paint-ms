# Airtable → paint-ms order/BOM sync

Orders and their paint BOM come from the production Airtable base, not Odoo directly.
The BOM string in Airtable is what the legacy PMS sheet was pasting.

## Source

| | |
|---|---|
| Base | `appPajKbbSBracppe` |
| Table | `tblcL1JAl6iJP8Jrb` (orders / production board) |
| View | `viwhqbXW8UuDx8ElE` — "This Month" (~150 records) |
| Auth | Airtable PAT in `.env` as `AIRTABLE_TOKEN`; scope `data.records:read` on this base |

Field **IDs** are stable; names are not (the API connector doesn't even return them). Always
read/write by ID.

| Field ID | Type | Use in paint-ms |
|---|---|---|
| `fldxO7kzDnRPbOJ5z` | formula (trimmed order code) | `orders.code` — use this, not the raw primary field `fldXuftaH3B83Yhaq` |
| `fld71BWWdjayToa51` | text | Paint BOM string → `orders.bom_source`, parsed into `bom_lines` |
| `fld5DkTtohXfCCjCr` | number | width (in) |
| `fldwfoJjdBtXIlqct` | number | height (in) |
| `fldPk7fraqtNIrg89` | formula `w*h/144` | `orders.total_area` (sqft) |
| `fldmbyQLZDJH78QjD` | singleSelect | finish → `orders.finish` (normalise: Matte/Matt/matte → `Matt`; Glossy → `Gloss`; Satin* → `Satin`; else null) |
| `flddTw8GtJCCiIvbU` | multiline | paint colour note ("Black", "4278 C pantone", "cool gray 3 C") → `orders.colour_note`; prefill for colour batch |
| `fld9jhixbr09YeZuN` | multiSelect | production stage tags. `Paint needed`, `Repaint needed`, `Paint Compound` drive the paint queue; `Done`/`Shipped` close the order |
| `fldbwll1a6M4e9xfb` | date | order date |
| `flddMyUCQQNYLbMRx` | date | due date |
| `fld3xQnd4h7swLchi` | singleSelect | sign type (informational) |
| `fldmK6zVBHMKtMyXB` | singleSelect | 2D / 3D (informational) |

Legacy PMS also had a **Surface Area** column distinct from Total Area. Not yet mapped — confirm
which Airtable field holds it (or whether it's computed elsewhere) before relying on cost/sqft.

## Historical tables (verified 2026-09-07, read-only search)

The base holds one table per month (`January-2026` … `August-2026`, then `This Month`), all
~500 fields, same field *names* but different field/table IDs. Facts that matter for sync/backfill:

- The **`Paint`** column (BOM string) exists **only in `August-2026` and `This Month`** — it was
  introduced in August. Earlier months have `Paint Cost` (text, cost only), no BOM string.
- `order_id` (trimmed-code formula) exists in every monthly table + `Vinyl orders - 2026` —
  filter server-side with `filterByFormula` on it; never page whole tables (~500-field records).
- An order can appear in **more than one table/record** (e.g. `BS-SM-16247`: two stale August
  records tagged `Stop Production`/`duplicate order` with empty Paint, plus the live `Done`
  record in This Month). Rule: prefer the record with a non-empty `Paint`; treat
  `duplicate order` status as skippable.
- All 169 line-less legacy orders found there have Paint strings **identical** to our stored
  `orders.bom_source` — local raw strings are trustworthy for re-parsing/backfill.

## BOM string format

```
Paint Epoxy Primer 2186.7 Gram, Epoxy Thinner 461.98 Gram, Epoxy Hardner 431.18 Gram,
Paint Mixing 702.84 Gram, Paint Matt agent(Matt) 351.42 Gram, Paint Harnder 351.42 Gram, Paint Thinner 284.48 Gram,
Paint Mixing 0 Gram, Paint Binder(Gloss) 0 Gram, Paint Harnder 0 Gram, Paint Thinner 0 Gram,
Paint Miscellaneous cost 1 | Total: 19,592.12
```

Rules (encoded in `App\Services\BomStringParser`):

1. Split on ` | Total: ` → left = lines, right = BOM cost (strip commas) → `orders.bom_total_cost`.
2. Split lines on `, `. Each line is `<category> <qty> <uom>`; uom is `Gram` or absent
   (`Paint Miscellaneous cost 1` — no uom). Regex: `^(.*?)\s+([\d.]+)(?:\s+(Gram))?$`.
3. **The same category appears more than once** — the string is a matt block followed by a gloss
   block (and sometimes a third block). Sum quantities per category; never last-wins.
4. Some lines have no quantity at all (`Paint Mixing, Paint Binder(Gloss), ...`) — treat as 0.
5. Category names carry Odoo's spelling (`Harnder`, `Matt agent(Matt)`). Match against
   `bom_categories.name` exactly; unknown category → store the line with `issue_pool = null`
   and flag the order for admin review. Do not silently drop.
6. `Paint Miscellaneous cost` maps to an `ignored` category — kept as a line for audit, never issued.
7. `Paint Stain` is issued in **Sqft**, not grams (`Paint Stain 0.5 Sqft`); the parser keeps the uom
   per line and `bom_lines.uom` carries it. Never mix units when summing a pool.
8. Odoo drifts between `Epoxy Primer` and `Paint Epoxy Primer` for the same thing — the parser
   collapses aliases to the canonical `bom_categories.name` (`BomStringParser::ALIASES`).
9. A string where every qty is 0 (`Total: 0.00`) means "no paint BOM" — the order exists but has no
   paint allowance; the Issue screen shows it as such rather than empty.

## Sync behaviour

- Command: `php artisan airtable:sync-orders [--view=viwhqbXW8UuDx8ElE] [--all]`. Schedule every
  15 min; the `--all` flag walks the whole table for backfill.
- Upsert `orders` on `code`. Store the Airtable record id in `orders.airtable_record_id` (add column).
- Re-parse `bom_lines` only when `bom_source` changed (compare hash); BOM edits after issue has
  started are allowed but logged (`orders.bom_changed_after_issue_at`) and surfaced on the order.
- Never delete orders that disappear from the view — "This Month" is a rolling window.
- Keep the raw payload in `airtable_sync_log` (record id, fields json, action, synced_at).
  Implemented: logged on created/updated/bom_reparsed only — an unchanged record writes
  nothing, so the log stays an audit trail instead of 14k no-op rows a day.
- Write-back (later): when an order's paint is fully consumed, add tag on `fld9jhixbr09YeZuN`;
  needs `data.records:write` scope and a decision on which tag.

## Observations from the current view (Sep 2026)

- ~100 of 151 records have a BOM string; the rest are pre-production (no BOM yet). Sync them as
  orders with no lines.
- Order codes include suffixes with spaces and parentheses: `BS-US-2548 A`, `BS-SM-16458-B (Bulk)`,
  `BS-SM-15743 RE`, `Gift-55-A`. Treat as opaque strings.
- Only ~15 records have finish/colour filled. The station screen must work without them.
