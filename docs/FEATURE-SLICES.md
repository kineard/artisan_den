# Feature & fix slices

Do one slice at a time; test the site between each.

---

## Slice 1: Product limit (Top 10 / 15 / 20 / All) — DONE
- Added an inventory table selector: "Show: Top 10 | Top 15 | Top 20 | All".
- Wired limit into inventory loading and applied in PHP after `getInventoryForStore(...)`.
- State is preserved through inventory actions/redirects.

---

## Slice 2: Inventory reorder list “not calculating” — DONE
- 7-day average now uses any available days (≥1) so ROP/Target/Suggested order calculate with partial sales data. Defensive defaults for reorder_point/target_max in template.

---

## Slice 3: Vendor edit more visible — DONE
- Added a clearer vendor edit control in the inventory header with explicit "Edit existing vendor" labeling.
- Updated vendor edit modal flow so `showVendorModal(id)` opens after vendor data loads, avoiding empty-state flash on edit.

---

## Slice 4: Add order from table (Qty Ordered cell) — DONE
- **Qty Ordered** cell: when no pending order and product has a vendor, "Add order" button opens order modal with product/vendor/cost/suggested qty pre-filled. When no vendor, shows "—" with tooltip to set vendor in Edit.

---

## Slice 5: Received = form to confirm/adjust quantity — DONE
- Clicking **Rcvd** opens a receive modal with default qty = full order qty and editable received date.
- Partial receives are supported: received qty is added to inventory, original order is marked received for that qty, and any remaining qty is auto-kept open as a new pending order.
- Added client and server validation so received qty cannot exceed ordered qty.

---

## Slice 6: Order date / “date ordered” visible — DONE
- Qty Ordered cell shows quantity plus short date, e.g. "50 (Jan 15)". Tooltip still has full "Ordered on YYYY-MM-DD". Received column already showed "Exp: M j" when expected_delivery_date is set.

---

**Order of work:** 1 → 2 → 3 → 4 → 5 → 6 (adjust 2 if calculation is urgent).
