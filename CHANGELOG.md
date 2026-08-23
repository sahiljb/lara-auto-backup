# Changelog

All notable changes to `lara-auto-backup` are documented here. This project
follows [Semantic Versioning](https://semver.org/).

## v1.0.0 — Unreleased

Initial release.

- `backup:database` — dump a database and stream it to S3 / Cloudflare R2.
- `backup:list` — list the archives stored on a disk.
- `backup:clean` — prune archives outside the retention window.
- Self-registering schedule, configurable with a cron expression (hourly by default).
- MySQL, MariaDB, PostgreSQL and SQLite dumpers, swappable per driver.
- Multiple destination disks and multiple database connections per run.
- Table include/exclude filters and custom archive naming.
- Retention with a `keep_at_least` safety net.
- `BackupStarted`, `BackupCompleted` and `BackupFailed` events, plus optional webhook notifications.
- `Backup` facade for programmatic use.
