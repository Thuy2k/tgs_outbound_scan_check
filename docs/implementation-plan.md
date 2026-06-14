# TGS Outbound Scan Check - Implementation Plan

## Goal

Build a separate plugin for checking picked outbound ticket items with a barcode scanner before the related outbound/internal-transfer/export ticket is approved.

## Scope

- New plugin: `tgs_outbound_scan_check`.
- Schema remains in `tgs_shop_management/database/class-tgs-database.php` and a migration so activating `tgs_shop_management` creates local tables per site.
- The check flow can attach to any `wp_local_ledger.local_ledger_id`/`local_ledger_code`, but ticket detail will only show quick actions for configured supported ticket types.
- Check rows compare original ledger item quantity in the smallest unit against checked quantity in the smallest unit.

## Tables

- `wp_local_outbound_scan_check`
  - Header for each check session.
  - Parent ledger id/code/type, check code/title/note, status, result status, totals, meta, user/timestamps.
- `wp_local_outbound_scan_check_item`
  - Snapshot rows for each original ledger item plus extra rows for products not in the parent ticket.
  - SKU, cached product name, expected quantity, checked quantity, note, source local ledger item id, meta, user/timestamps.

## UX

- List page:
  - Search by check code or parent ledger code.
  - Filter by check status and final result.
  - Create new check by entering parent ticket code.
  - Permanently delete a check and its items.
- Scan page:
  - Compact parent ticket summary.
  - Product search by SKU/barcode/name with debounce and no Enter requirement.
  - Search result dropdown below search input, showing real stock.
  - Product quantity modal with unit conversion to smallest unit.
  - Confirm adds quantity to matching SKU rows top-down; if missing, allows adding an extra row marked over-picked.
  - Manual edit for checked quantity and per-row note.
  - Analyze button via AJAX for missing/short/enough/over/extra counts.
  - Save draft without leaving the page.
  - Reset checked quantities to zero.
  - JS activity log for scan actions.

## Integration

- Add dashboard routes through `tgs_shop_dashboard_routes`.
- Add menu entry through header/mega-nav hooks or existing shop menu hooks.
- Add a ticket-detail button/link block for supported ticket types.
- Default supported detail types:
  - `ticket-transfer-export-detail`
  - `ticket-internal-return-detail`
  - `ticket-export-v2-detail`

## Checklist

- [x] Add local table SQL builders to `class-tgs-database.php`.
- [x] Add migration for the two local tables.
- [x] Register table constants or plugin-side table helpers.
- [x] Scaffold plugin bootstrap, route filters, enqueue, AJAX init.
- [x] Implement helper for parent ticket loading, item snapshots, summary analysis, and product search.
- [x] Implement AJAX endpoints.
- [x] Build list page.
- [x] Build scan page.
- [x] Build CSS and JS.
- [x] Add ticket-detail button and existing check links.
- [x] Run PHP and JS syntax checks.
