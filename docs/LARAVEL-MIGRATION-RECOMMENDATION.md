# Laravel Migration Recommendation (Business-First)

This document is written for a business owner/operator perspective, not a programmer perspective.

Goal: move `artisan_den` to Laravel in a way that is stable now and ready for a future multi-tenant, multi-location POS.

---

## Your Rule #1 (locked)

If we do not know an answer, we explicitly say so and use other assets (docs, samples, production behavior, reports) before deciding.

How we apply this:
- Every major decision below has a confidence level.
- Any "Low confidence" item requires your decision or additional asset before implementation.

---

## Recommended Defaults (Best Practices)

These are my recommendations for your project right now.

### 1) Framework & Runtime
- **Use Laravel (latest stable)** with PHP 8.3+.
- **Use PostgreSQL** (you already use it).
- **Use Laravel migrations + seeders** as the only way to change schema/data setup.

Why this is best practice:
- Predictable upgrades and security patches.
- Easier team onboarding and less "custom framework" risk.

Confidence: **High**

### 2) Multi-Tenant Strategy (important)
- **Start with single database + `tenant_id` column strategy**.
- Keep `stores`/`locations` under each tenant.
- Add global tenant scoping in app code so data never crosses tenants.

Why this is best practice for your stage:
- Simpler operations and lower hosting complexity.
- Faster migration from your current single-org model.
- Easy to run analytics/reporting across many tenants later.

When to change later:
- Move to database-per-tenant only if a compliance/customer requirement forces strict physical separation.

Confidence: **High**

### 3) Multi-Location Model
- Keep **Tenant -> Locations (stores)** relationship.
- Every KPI, inventory row, order, shift, task must reference:
  - `tenant_id`
  - `location_id` (store)

Why this is best practice:
- Prevents data leaks.
- Makes per-location reporting and permissions straightforward.

Confidence: **High**

### 4) Authentication & Roles
- Use Laravel auth (session-based web auth).
- Use role/permission package pattern (manager/admin/employee permissions).
- Remove all dev fallback access before production.

Why this is best practice:
- Your current docs already call out RBAC hardening as a go-live requirement.
- Laravel policies/gates give reliable access control patterns.

Confidence: **High**

### 5) Money and Inventory Math
- Store money as integer cents (or strict decimal), never float.
- Keep inventory quantities in decimal where needed, with explicit precision.
- Add server-side validation for all write actions (not only UI validation).

Why this is best practice:
- Prevents subtle rounding and financial reporting errors.

Confidence: **High**

### 6) Domain Boundaries
- Keep integrations (POS sync, external APIs) outside core domain logic.
- Use service classes + queued jobs for imports/sync.
- Keep Launch 1 domain boundaries:
  - KPI
  - Inventory/Reorder
  - Time Clock

Why this is best practice:
- Your existing guardrail says "no direct integration logic in core workflows."
- Keeps operations stable even if integrations fail.

Confidence: **High**

### 7) UI Approach for a Non-Programmer-Friendly Team
- **Blade + Livewire** for admin/business UI flows.
- Keep JavaScript complexity low unless needed for charting or kiosk behavior.

Why this is best practice for you:
- Faster iteration for forms/tables/workflows.
- Less front-end framework overhead while still modern.

Confidence: **Medium** (can switch to Inertia later if needed)

### 8) Testing & Release Safety
- Add feature tests for critical workflows first:
  - KPI write permissions
  - Inventory update permissions
  - Time clock punch + manager actions
- Use staging environment before production cutover.
- Keep rollback plan for each release.

Why this is best practice:
- Reduces business downtime risk during migration.

Confidence: **High**

---

## Migration Plan (Recommended Sequence)

### Phase A: Foundation
1. Create Laravel app scaffold in this repo.
2. Connect PostgreSQL via environment settings.
3. Add auth + roles/permissions + tenant/location base models.
4. Add baseline migrations for existing core tables.

### Phase B: Lift-and-Shift Launch 1 Modules
1. KPI module parity.
2. Inventory/Reorder parity.
3. Time Clock parity.
4. Keep behavior same before optimizing UX.

### Phase C: Hardening
1. Tenant/location global scoping.
2. Policy enforcement on all write endpoints.
3. Audit logging and error monitoring.
4. Background jobs for integrations/sync.

### Phase D: POS Readiness (Future)
1. Introduce POS domain schema and services behind feature flags.
2. Add checkout/tenders/returns/receipts only when ready.

---

## Decisions You Need to Approve (Simple)

Please approve or change these defaults:

1. **Tenancy model:** single DB with `tenant_id` (recommended)
2. **UI stack:** Blade + Livewire (recommended)
3. **Auth:** Laravel built-in session auth + role permissions (recommended)
4. **Scope freeze:** Launch 1 stays KPI/Inventory/Time Clock until stable (recommended)

If you approve all 4, I can generate the implementation checklist and start scaffolding.

---

## Assets We May Need (Only if Unknowns Block Us)

Per Rule #1, if uncertain we should gather:
- A current production-like schema/data snapshot (sanitized is fine)
- Any "must not change" report outputs (examples)
- Any must-keep workflows from managers/employees

This keeps migration accurate and avoids guessing.

