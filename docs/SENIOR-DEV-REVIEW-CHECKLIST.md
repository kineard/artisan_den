# Senior Dev Review Checklist

Use this checklist for high-signal review of risky or core project changes.

---

## 1) Scope and Intent

- [ ] Change is within approved scope (Launch 1: KPI, Inventory/Reorder, Time Clock), or has explicit approval.
- [ ] PR description explains business intent and expected outcome.
- [ ] Risk level is declared (`low` / `medium` / `high`).

---

## 2) Money and Financial Logic (Mandatory)

- [ ] No floating-point money math introduced.
- [ ] Money fields use safe type and precision.
- [ ] Sales/COGS/labor/overhead calculations are correct and traceable.
- [ ] Payroll calculations/exports are validated with sample data.
- [ ] Edge cases tested (zero values, negative prevention, rounding behavior).

---

## 3) Security, Auth, and Permissions (Mandatory)

- [ ] All write endpoints/actions enforce auth.
- [ ] Role/policy checks are explicit and correct.
- [ ] No dev-only permission fallback exists in production paths.
- [ ] Session and kiosk idle behavior is safe for shared terminals.

---

## 4) Tenant and Location Data Boundaries (Mandatory)

- [ ] Reads and writes are tenant-scoped.
- [ ] Reads and writes are location/store-scoped where required.
- [ ] No unscoped query can leak cross-tenant or cross-location data.
- [ ] Test evidence covers at least one cross-scope isolation case.

---

## 5) Inventory and Reorder Integrity (Mandatory for inventory changes)

- [ ] On-hand update logic is deterministic and auditable.
- [ ] Pending/received order transitions are correct.
- [ ] Reorder formulas match approved business rules.
- [ ] Lead-time and expected-delivery handling remains consistent.

---

## 6) Data Migration Safety (Mandatory for schema/data changes)

- [ ] Migration is reversible or has documented rollback strategy.
- [ ] Backfill/transformation logic is documented and tested.
- [ ] Data-loss risk assessed and mitigated.
- [ ] Production run order is documented (precheck -> migrate -> verify).

---

## 7) Testing and Verification

- [ ] Automated tests added/updated for changed behavior.
- [ ] Local smoke test steps included and reproducible.
- [ ] Test evidence attached (logs/screenshots/output snippets).
- [ ] Known limitations and follow-up tasks are documented.

---

## 8) Operations and Release Readiness

- [ ] Monitoring/logging impact considered.
- [ ] Feature flags or staged rollout used when risk is medium/high.
- [ ] Rollback steps are practical and time-bounded.
- [ ] Release notes include user-facing behavior changes.

---

## 9) Documentation and Maintainability

- [ ] `docs/` updated for behavior or decision changes.
- [ ] Non-obvious business logic has concise "why" comments.
- [ ] Commit messages are clear and meaningful for future maintainers.
- [ ] Technical debt introduced is explicitly tracked.

---

## Approval Gate

For changes touching money, auth, tenant scoping, or data migrations:

- [ ] Human senior-dev approved before merge.

