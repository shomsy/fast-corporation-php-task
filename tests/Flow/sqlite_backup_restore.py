#!/usr/bin/env python3
"""Zero-dependency, unprivileged WAL backup/restore and negative-path acceptance probe."""
import hashlib
import os
import shutil
import sqlite3
import subprocess
import tempfile
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def php(args, database, input_text=None):
    env = os.environ.copy()
    env.update(DB_PATH=str(database), APP_ENV="test", APP_DEBUG="0")
    if Path("/usr/local/bin/php8.5-docker").exists():
        command = [
            "docker", "run", "--rm", "-i",
            "--user", f"{os.getuid()}:{os.getgid()}",
            "-v", f"{ROOT}:{ROOT}", "-w", str(ROOT),
            "-e", "DB_PATH=" + str(database), "-e", "APP_ENV=test",
            "php:8.5-cli", "php", *args,
        ]
    else:
        command = ["php", *args]
    return subprocess.run(command, cwd=ROOT, env=env, input=input_text, text=True, capture_output=True, timeout=35)


def must_run(args, database, input_text=None):
    result = php(args, database, input_text)
    assert result.returncode == 0, (args, result.returncode, result.stdout, result.stderr)
    return result.stdout


def main():
    with tempfile.TemporaryDirectory(prefix="fast-backup-accept-", dir=ROOT / "var") as temp:
        work = Path(temp)
        work.chmod(0o700)
        source_dir = work / "source"
        source_dir.mkdir(mode=0o700)
        backup_dir = work / "backups"
        backup_dir.mkdir(mode=0o700)
        source = source_dir / "app.sqlite"
        backup = backup_dir / "snapshot.sqlite"
        restored = source_dir / "restored.sqlite"

        must_run(["bin/migrate.php"], source)
        must_run(["bin/create-admin.php", "backup-operator", "--password-stdin"], source, "secure-backup-password\n")

        with sqlite3.connect(source) as db:
            assert db.execute("PRAGMA journal_mode").fetchone()[0] == "wal"
            uid = db.execute("SELECT id FROM accounts WHERE username='backup-operator'").fetchone()[0]
            for i in range(25):
                db.execute(
                    "INSERT INTO activity_events (actor_id, actor_label, action, target, occurred_at) VALUES (?,?,?,?,?)",
                    (uid, "backup-operator", "view-page", "page-a", "2026-10-09T03:00:00+00:00"),
                )
            db.commit()
            # Keep the source WAL and an active consistent read snapshot open during backup.
            db.execute("BEGIN")
            expected = db.execute("SELECT COUNT(*) FROM activity_events").fetchone()[0]
            start = time.monotonic()
            output = must_run(["bin/backup.php", str(backup)], source)
            backup_duration = time.monotonic() - start
            assert "SQLite backup integrity: PASS" in output
            assert backup.stat().st_mode & 0o077 == 0
            assert source.stat().st_mode & 0o077 == 0
            assert backup_dir.stat().st_mode & 0o077 == 0
            assert (Path(str(source) + "-wal")).exists(), "Expected committed WAL data during online backup"
            db.rollback()

        initial = hashlib.sha256(backup.read_bytes()).hexdigest()
        again = php(["bin/backup.php", str(backup)], source)
        assert again.returncode != 0, "Backups must never overwrite an earlier verified snapshot"
        assert hashlib.sha256(backup.read_bytes()).hexdigest() == initial

        backup_dir.chmod(0o755)
        try:
            rejected = php(["bin/backup.php", str(backup_dir / "insecure.sqlite")], source)
            assert rejected.returncode != 0, "Backup directory must be private"
            assert not (backup_dir / "insecure.sqlite").exists()
        finally:
            backup_dir.chmod(0o700)

        start = time.monotonic()
        shutil.copyfile(backup, restored)
        restored.chmod(0o600)
        with sqlite3.connect(restored) as db:
            assert db.execute("PRAGMA integrity_check").fetchone()[0] == "ok"
            actual = db.execute("SELECT COUNT(*) FROM activity_events").fetchone()[0]
            assert actual == expected, (actual, expected)
            assert db.execute("SELECT COUNT(*) FROM schema_migrations").fetchone()[0] == 4
        restore_duration = time.monotonic() - start

        # Bounded local SQLITE_FULL fault injection: never fill the host filesystem.
        exhausted = work / "capacity-limited.sqlite"
        with sqlite3.connect(exhausted) as db:
            db.execute("CREATE TABLE oversized (id INTEGER PRIMARY KEY, payload BLOB NOT NULL)")
            db.commit()
            before_pages = db.execute("PRAGMA page_count").fetchone()[0]
            db.execute(f"PRAGMA max_page_count = {before_pages + 1}")
            try:
                db.execute("BEGIN")
                db.execute("INSERT INTO oversized(payload) VALUES (?)", (b"x" * 262144,))
                db.commit()
                raise AssertionError("SQLite max_page_count should produce SQLITE_FULL")
            except sqlite3.OperationalError as error:
                assert "full" in str(error).lower(), str(error)
                db.rollback()
            assert db.execute("SELECT COUNT(*) FROM oversized").fetchone()[0] == 0
            assert db.execute("PRAGMA integrity_check").fetchone()[0] == "ok"
        assert backup.exists() and hashlib.sha256(backup.read_bytes()).hexdigest() == initial

        pending = list(backup_dir.glob("*.pending-*"))
        assert not pending, ["Incomplete backup temp: " + str(item.name) + " mode=" + oct(item.stat().st_mode & 0o777) for item in pending]
        print("SQLITE_FULL simulated rollback and snapshot integrity: PASS")
        print("WAL ONLINE BACKUP/RESTORE: PASS")
        print("UNPRIVILEGED FILE PERMISSIONS / NO OVERWRITE / INSECURE DIRECTORY: PASS")
        print("Rows restored:", actual, "| migrations:", 4)
        print("Local measured backup seconds:", round(backup_duration, 3))
        print("Local measured restore+integrity seconds:", round(restore_duration, 3))
        print("RPO/RTO are deployment objectives, not guaranteed by this one-run measurement.")


if __name__ == "__main__":
    main()
