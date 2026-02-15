# Launch 1 Scope Lock

This project is locked to the following Launch 1 modules only:

- KPI
- Product Inventory/Reorder
- Time Clock

## Scope Freeze

The following is explicitly deferred to a later phase and should not be built as part of Launch 1:

- Full POS checkout (cart, tenders, returns, receipts)

## Guardrails

- Keep architecture multi-store ready from day 1 (single business/org with store scoping).
- Keep implementation compatible with future multi-tenant SaaS expansion.
- No float money math for financial fields.
- No direct integration logic in core domain workflows.

## Delivery Intent

Launch 1 implementation and refactors should prioritize KPI, Inventory/Reorder, and Time Clock workflows. Any work that expands into checkout POS flow should be treated as out-of-scope and tracked for post-launch planning.
