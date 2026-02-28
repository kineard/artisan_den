# Artisan Den - Single Brief for Second Opinion AI

This is the condensed project brief, decisions, rules, and review priorities.

---

## 1) Project Context

- Project: `artisan_den`
- Current state: legacy custom PHP app being migrated to Laravel.
- Business target: multi-tenant, multi-location Point of Sale system.
- Owner profile: non-programmer, technical business operator, wants best practices and low-risk execution.

---

## 2) Locked Scope (MVP / Launch 1)

Launch 1 modules only:
- KPI
- Inventory/Reorder
- Time Clock

Deferred (not in Launch 1):
- Full POS checkout (cart, tenders, returns, receipts)

Guardrails:
- Multi-store ready now, multi-tenant compatible later.
- No float money math.
- No direct integration logic in core domain workflows.

---

## 3) Hard Rules (Approved)

### Rule #1 (most important)
- If answer is unknown, do not guess.
- State what is unknown.
- Request supporting assets (schema snapshot, sample reports, production behavior, business rules).

### Anti-drift rules
- Stay within Launch 1 scope unless explicitly approved.
- Keep tenant/location data boundaries explicit.
- Enforce auth/role checks on all writes.
- Favor maintainable best-practice solutions over short-term hacks.

### Human review rule
- Any money-related, auth-related, tenant-scope-related, or migration-transforming change requires human senior-dev review before merge.

---

## 4) Approved Architecture Decisions

- Framework/runtime: Laravel + PostgreSQL
- Tenancy strategy: single database with `tenant_id` (for now)
- Data model: Tenant -> Locations (stores)
- Auth model: Laravel session auth + roles/permissions
- UI recommendation: Blade + Livewire (can change later if needed)

---

## 5) Migration Plan (Approved Direction)

Phase A - Foundation
1. Create Laravel scaffold
2. Configure PostgreSQL
3. Add auth + roles/permissions + tenant/location base models
4. Add baseline migrations

Phase B - Lift-and-shift (behavior parity)
1. KPI
2. Inventory/Reorder
3. Time Clock

Phase C - Hardening
1. Tenant/location scoping enforcement
2. Policy enforcement for write endpoints
3. Audit/error monitoring
4. Queue-based integration boundaries

Phase D - Future POS Readiness
1. Add POS domain skeleton behind feature flags
2. Build checkout/tenders/returns/receipts later

---

## 6) Current Environment Status (Linux machine after Windows move)

Quick checks found blockers:
- `php` not installed
- `psql` not installed
- `pg_isready` not installed
- `git` installed

Impact:
- App cannot currently run/validate on this machine until runtime tools are installed.

Immediate pre-migration prerequisite:
1. Install PHP + required extensions
2. Install PostgreSQL client/server tools
3. Configure credentials
4. Run connectivity tests and smoke tests

---

## 7) Senior Dev Priority Review Queue

Priority 0 (must review before production):
1. Money calculations and storage
2. Inventory on-hand/reorder formulas
3. RBAC/policy enforcement
4. Tenant/location data scoping
5. Data migration scripts and backfills

Priority 1:
1. Time clock punch/payroll paths
2. Integration sync boundaries/retries
3. Session/kiosk security behavior

Priority 2:
1. UI polish/accessibility
2. Refactor quality
3. Performance tuning

---

## 8) Code Quality and Process Standards

- Small, reviewable slices.
- One logical change per commit where possible.
- Commit messages explain intent and business impact.
- Risk label per change (`low`, `medium`, `high`).
- Add concise "why" comments for non-obvious business logic.
- Include rollback notes for risky changes.

---

## 9) Open Questions for Second Opinion AI

Please evaluate and challenge:
1. Is single-DB + `tenant_id` the best tenancy strategy for this project stage?
2. Is Blade + Livewire the best fit given business-owner-led product iteration?
3. Is the migration phase order correct for minimizing downtime/risk?
4. What additional controls should be mandatory before first production Laravel cutover?
5. What test strategy is minimally sufficient for safe financial + inventory + timeclock behavior?

---

## 10) Existing Reference Docs

- `docs/LARAVEL-MIGRATION-RECOMMENDATION.md`
- `docs/PROJECT-SUCCESS-MASTER-LIST.md`
- `docs/REPO-RULES.md`
- `docs/SENIOR-DEV-REVIEW-CHECKLIST.md`
- `docs/LAUNCH1-SCOPE.md`

