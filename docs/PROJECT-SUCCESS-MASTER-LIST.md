# Project Success Master List

Business-first checklist to keep the project on track, reduce drift, and make handoff easy for senior developers.

---

## 1) Immediate First Step: Linux Machine Readiness (Post-Windows Move)

Status from quick checks on this computer:
- `php` not installed
- `psql` and `pg_isready` not installed
- `git` is installed

This means we cannot run or validate the app yet on this machine.

### Install/verify checklist (in order)
1. Install PHP CLI and required extensions (including PostgreSQL extension).
2. Install PostgreSQL client/server tools.
3. Create/update project `.env` or `config.php` credentials.
4. Run DB connectivity test scripts.
5. Start local server and perform smoke test.

### Minimum smoke test after install
- Open dashboard
- Switch store/location
- Save KPI row
- Save inventory update
- Clock in/out test in time clock flow

Success condition: all five pass on Linux.

---

## 2) Anti-Drift Rules (Project Governance)

These rules should remain active through migration.

1. **Scope lock for MVP:** Launch 1 only = KPI, Inventory/Reorder, Time Clock.
2. **No side quests:** POS checkout/tenders/returns/receipts remain deferred until Launch 1 is stable.
3. **Tenant/location safety:** all business data must be tenant-scoped and location-scoped.
4. **Money safety:** no float math in money paths.
5. **Access safety:** all write actions require explicit auth/role checks.
6. **Integration isolation:** no direct third-party integration calls inside core transaction logic.
7. **Rule #1:** if answer is unknown, stop and request assets instead of guessing.

---

## 3) MVP vs Full Launch Mapping

### MVP (what "done" means first)
- KPI data entry/reporting works by location
- Inventory/reorder works by location with pending/received order handling
- Time clock works (employee + manager core actions)
- RBAC is strict (no dev fallback)
- Linux environment reproducible for development
- Basic test coverage for critical write flows

### Full Launch (later target)
- Multi-tenant onboarding and tenant isolation hardening
- POS checkout domains (cart, tenders, returns, receipts)
- Full observability, queue reliability, and operational dashboards
- Performance tuning and advanced analytics

---

## 4) Human Senior Dev Review Queue (Priority Order)

This is the "master list" for human review first.

### Priority 0 (must review before production)
1. **All money calculations and storage**
   - sales, COGS, labor, overhead, payroll, cash/bank/safe rollups
2. **Inventory quantity updates and reorder formulas**
3. **RBAC and policy enforcement**
4. **Tenant/location data scoping**
5. **Migration scripts that transform data**

### Priority 1 (review early)
1. Time clock punch logic and payroll export paths
2. Integration sync boundaries and retry/error handling
3. Session/kiosk security and idle timeout behavior

### Priority 2 (review after core stability)
1. UI consistency and accessibility polish
2. Refactors for maintainability
3. Performance optimization

---

## 5) Coding/Commit Notes Standards (for Future Team)

### Commit standards
- One meaningful change per commit (small slices).
- Commit message includes intent and business impact.
- For risky changes, include a rollback note in commit body.

### Code note standards
- Add brief comments where business logic is non-obvious.
- Add a short "why" note for complex formulas and edge cases.
- Do not add noise comments ("assign variable", etc.).

### PR/review checklist
- What changed
- Why it changed
- Risk level
- Test evidence
- Rollback plan

---

## 6) Required Human Review Gates

A pull request cannot be merged if any of these changed without human approval:
- Money math or money schema
- Payroll logic
- Reorder calculation logic
- Auth/authorization logic
- Tenant/location scoping logic
- Data migration scripts

---

## 7) What You Might Be Missing (High Value Additions)

1. **Definition of Done** per module (KPI, Inventory, Time Clock).
2. **Change risk labels** (`low`, `medium`, `high`) on every PR.
3. **Rollback playbook** for each release.
4. **Production readiness checklist** tied to go-live criteria.
5. **Decision log** for architecture decisions (so future devs know why choices were made).

---

## 8) Next Actions (Recommended)

1. Complete Linux environment install and smoke test first.
2. Approve tenant model and UI stack defaults in `docs/LARAVEL-MIGRATION-RECOMMENDATION.md`.
3. Create Laravel scaffold and baseline migrations.
4. Start module migration in this order: KPI -> Inventory/Reorder -> Time Clock.

