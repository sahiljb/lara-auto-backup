# Changelog

All notable changes to `lara-auto-backup` are documented here. This project
follows [Semantic Versioning](https://semver.org/).

## v1.0.0 — Unreleased

Initial release.

- `backup:database` — dump a database and stream it to S3 / Cloudflare R2.
- `backup:list` — list the archives stored on a disk.
- `backup:clean` — prune archives outside the retention window.
- `backup:restore` — download an archive from S3 / R2 and load it back into the
  database, with an interactive picker, a confirmation prompt and a production
  guard (`--force`).
- Self-registering schedule, configurable with a cron expression (hourly by default).
- MySQL, MariaDB, PostgreSQL and SQLite dumpers and restorers, swappable per driver.
- Multiple destination disks and multiple database connections per run.
- Table include/exclude filters and custom archive naming.
- Retention with a `keep_at_least` safety net.
- `BackupStarted`, `BackupCompleted`, `BackupFailed`, `RestoreStarted`,
  `RestoreCompleted` and `RestoreFailed` events, plus optional webhook notifications.
- `Backup` facade for programmatic use.
- Windows support: dumping and restoring stream through PHP's zlib rather than
  shell pipes, so `gzip`, `gunzip` and `cat` are not required.
