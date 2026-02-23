# Git Workflow (Slice-Based)

This project uses a slice-based workflow so each feature can be reviewed and rolled back safely.

## Branch Strategy

- Keep `main` stable and deployable.
- Build replacement/new work on a long-running branch: `rebuild-v2`.
- Optional short-lived branches off `rebuild-v2` for bigger items:
  - `slice/timeclock-task-list`
  - `slice/timeclock-reminders`
  - `fix/kiosk-sync-timeout`

## Naming Convention

- `slice/<area>-<feature>` for planned feature slices
- `fix/<area>-<bug>` for defects
- `chore/<area>-<maintenance>` for non-feature updates

Examples:
- `slice/timeclock-task-list`
- `slice/timeclock-notifications`
- `fix/schedule-dragdrop-overlap`

## Commit Convention

Commit as soon as one slice is complete and sanity-tested.

Format:

`<type>(<scope>): <short intent>`

Types:
- `feat` new slice capability
- `fix` bug correction
- `refactor` structure-only code improvement
- `docs` documentation-only
- `chore` tooling or maintenance

Examples:
- `feat(timeclock): add day/shift task list with employee checkoff`
- `fix(timeclock): block task completion by unassigned employee`
- `docs(workflow): define slice-based branch and commit conventions`

## PR Cadence

- Commit small and often (one commit per slice is preferred).
- Push at least daily when actively developing.
- Open PR from `rebuild-v2` to `main` when:
  - critical slices are stable,
  - smoke checks pass,
  - and go-live blockers are tracked.

## Cutover Safety

Before merging replacement code into `main`:

1. Tag current `main` as backup (example: `pre-rebuild-backup-YYYY-MM-DD`).
2. Merge approved replacement branch.
3. Keep backup tag/branch available for rollback.
