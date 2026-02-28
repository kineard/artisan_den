# Ubuntu Service Setup

Use this to install and start the local services needed for current app validation and Laravel migration work.

## One-command setup

From project root:

```bash
./scripts/setup-services-ubuntu.sh
```

The script installs:
- PHP 8.3 CLI + required extensions
- Composer
- PostgreSQL server/client
- Node + npm
- Redis

It also creates/updates:
- database: `artisan_den`
- user: `artisan_user`
- password: `artisan_pass_123` (default; override via env var)

## Optional flags

Import current legacy schema during setup:

```bash
IMPORT_LEGACY_SCHEMA=1 ./scripts/setup-services-ubuntu.sh
```

Override DB settings:

```bash
DB_NAME=artisan_den DB_USER=artisan_user DB_PASS=your_password ./scripts/setup-services-ubuntu.sh
```

## Verify after setup

```bash
php test-environment.php
php test-connection.php
```

If both pass, your Linux environment is ready for migration work.

