# Comparing dev vs server environment

Your **dev** snapshot is saved in the project root:

- `dev-environment-report.txt` – full report (human-readable)
- `dev-environment-summary.txt` – one-line fingerprint

## On the server (Ubuntu)

1. Copy `check-environment.php` to the server (e.g. same path in your app).
2. From the project root on the server run:

   ```bash
   php check-environment.php > server-environment-report.txt
   php check-environment.php --summary > server-environment-summary.txt
   ```

3. Compare with dev:

   ```bash
   diff dev-environment-summary.txt server-environment-summary.txt
   diff dev-environment-report.txt server-environment-report.txt
   ```

If there is no output from `diff`, the two environments match. Any lines that differ (PHP version, PG version, missing extension, etc.) will be shown.

## Refresh the dev snapshot

After changing PHP, PostgreSQL, or extensions on your dev machine, run:

```bash
php check-environment.php > dev-environment-report.txt
php check-environment.php --summary > dev-environment-summary.txt
```

Then commit the updated files so you always have a current dev baseline to compare against the server.
