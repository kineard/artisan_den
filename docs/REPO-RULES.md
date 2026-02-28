# Repo Rules (Shared in Git)

These rules are the repo-tracked reference for how this project should be built and reviewed.

They mirror the always-on Cursor rules in `.cursor/rules/`.

---

## 1) Scope and Anti-Drift

- Launch 1 is locked to: KPI, Inventory/Reorder, Time Clock.
- Full POS checkout (cart, tenders, returns, receipts) is deferred until explicitly approved.
- Keep design multi-location now and multi-tenant ready.
- If work conflicts with scope, call it out and request explicit approval.

---

## 2) Rule #1: Unknowns

- If an answer is unknown, do not guess.
- State what is unknown.
- Request the needed asset (schema snapshot, sample report, production behavior, or business rule).
- Propose the fastest path to get that asset.

---

## 3) Security and Data Safety

- Enforce auth and role checks for all write actions.
- Keep tenant/location scoping explicit in reads and writes.
- Do not use floating-point arithmetic for money logic.

---

## 4) Mandatory Human Senior-Dev Review

Human review is required for changes touching:

- money calculations or money schema
- payroll logic and exports
- inventory reorder formulas
- auth/policy/permission logic
- tenant/location scoping logic
- data migrations that transform business data

---

## 5) Change Management Standards

- Ship in small, reviewable slices.
- Keep one logical change per commit when possible.
- Avoid mixing unrelated refactors with business logic changes.
- Use commit messages that explain intent and business impact.
- For risky changes, include test evidence and rollback notes.
- Add concise code comments where business logic is non-obvious ("why" over "what").

