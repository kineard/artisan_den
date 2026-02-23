# Go-Live Checklist

- Enforce strict RBAC from authenticated session roles (remove dev fallback behavior).
- Enable idle timeout/session reset for shared terminals (target: 60 seconds no activity in kiosk/shared-user flows).
- Verify privileged actions are manager/admin only: KPI writes, inventory writes, payroll export, and Time Clock manager workflows.
- Validate geofence behavior on real GPS-capable devices (not VPN/IP only):
  - Set final store coordinates and radius.
  - Confirm `warn` and/or `block` policy behavior matches operations.
  - Confirm expected handling when GPS is denied/unavailable.
- Confirm kiosk mode on target devices:
  - Idle reset timeout is correct.
  - PIN keypad and keyboard flows both work.
  - Offline queue status and online sync are verified.
- Confirm PWA installability:
  - Manifest loads correctly.
  - Service worker registers/updates.
  - Core shell loads without regressions.
