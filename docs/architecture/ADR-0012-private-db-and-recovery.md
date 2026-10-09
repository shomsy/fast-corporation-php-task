# ADR-0012 — Private SQLite storage and verified offline recovery

Status: Accepted for local C-25 acceptance, 2026-10-09  
Owner: Platform / Operations; Accounts and ActivityTracking own their data semantics  
Public production deployment: **NO-GO** until the target worker identity, recovery objectives and host failure drills are verified.

## Threat and storage boundary

The HTTP server must serve only `public/`. The session directory, `.env`, SQLite database, its WAL/SHM sidecars and backups contain session state, credentials or personal activity and must not be public. Production startup rejects non-owned or group/world-accessible session directories, database directory/files and an existing `.env`. Files require owner read/write, with no executable permission; private directories require owner access only. The trusted SQLite connection sets a restrictive process `umask 0077` before SQLite can lazily create WAL/SHM files; CLI backup likewise creates owner-private snapshots.

Prepare these paths under the *actual unprivileged PHP service account*, not root, with restrictive ownership and modes. For example, replacing paths and service identity to match the deployment:

```sh
install -d -m 0700 /private/app/sqlite /private/app/sessions /private/app/backups
chmod 0700 /private/app/sqlite /private/app/sessions /private/app/backups
chmod 0600 .env
# Run the following as the private PHP application owner:
php bin/migrate.php
php bin/backup.php /private/app/backups/snapshot-20261009.sqlite
```

Do not use `chmod -R` on application sources or expose `.env` to web access. The file owner checks use the effective UID when the POSIX extension is available; verify the real web worker identity and filesystem ACLs in the target environment before traffic. Directory ancestors and deployment mounts must not accidentally expose paths. Use a **local filesystem with real SQLite lock support**, not an untested distributed filesystem.

## Trusted create-only online backup

`php bin/backup.php /absolute/owner-private/backup-dir/new-name.sqlite` is CLI-only. The destination directory must already exist, be privately owned, writable and mode 0700. Existing destination files or symbolic links are refused; this is a create-only operation. It uses SQLite's online backup API while the source may have committed WAL data and active readers. It creates a unique private temporary snapshot, checkpoints it to a standalone database, checks `PRAGMA integrity_check = ok`, then publishes it without overwriting any previous snapshot. A failed run cleans up only its own unique temporary artifacts; it does not delete the source WAL/SHM files.

An ordinary `cp source.sqlite backup.sqlite` **while the WAL database is active is not a safe online backup**. Do not delete or copy only WAL/SHM as a shortcut. Backup retention, encryption, off-host replication, access controls and scheduling belong to the actual operator and are not claimed by this single-host demonstration.

## Restore rehearsal and response

1. Stop/disable new HTTP traffic and scheduled writers. Securely preserve the existing database **with its WAL and SHM sidecars together** for diagnosis; never unlink a live sidecar.
2. Copy the independently verified standalone backup into a *new*, empty private restore location. Apply mode 0600 and correct service ownership to the restored database, mode 0700 to its parent. Do not clobber the original.
3. Verify `PRAGMA integrity_check = ok`, expected schema migration count and representative business row totals. Compare counts/data/checksums for the recovery goal. Re-open using the normal application SQLite connector and run schema migrations; recorded migration source checksums must still match the trusted release.
4. Switch `DB_PATH` to the restored, validated database during a controlled maintenance window, then exercise real login, access policy, activity writing and reports. Explicitly decide how to handle sessions issued after the backup, and reconcile events omitted since the snapshot.
5. Resume traffic only after a positive readiness check and a documented decision that the amount of data lost (RPO) and time to service recovery (RTO) meet stakeholder objectives. If not, do **not** declare production recovery complete.

## Executable acceptance evidence

- `tests/Infrastructure/FilePrivilegesTest.php`: restrictive database/dir creation despite permissive caller umask; world-readable DB rejected in production.
- `tests/Infrastructure/DeploymentConfigTest.php`: production rejects insecure paths/settings.
- `python3 tests/Flow/sqlite_backup_restore.py`: prepares an isolated four-migration WAL database, makes a verified online backup while an active read snapshot and source WAL exist, restores 25 activity events, validates no-overwrite policy and backup directory permissions. Also injects `SQLITE_FULL` locally through `PRAGMA max_page_count`, checks rollback and SQLite integrity, and verifies the previous snapshot is unchanged. This **does not** fill the host disk.
- The observed local backup and verification duration is not a production p95; real RPO/RTO cannot be inferred from it.

Follow-up for C-26/C-47: concrete deployment service identity, writable volumes, `healthz`/`readyz`, monitored backups, restore drill on the target host, off-host retention/encryption, actual RPO/RTO and disk-exhaustion alerting.
