#!/usr/bin/env python3
"""Reproducible two-process SQLite reader/writer, WAL, contention and backup probe."""
import json
import sqlite3
import subprocess
import sys
import tempfile
import time
from pathlib import Path


def competing_writer(path: Path) -> int:
    conn = sqlite3.connect(path, timeout=0.15)
    conn.execute("PRAGMA busy_timeout = 150")
    started = time.monotonic()
    try:
        conn.execute("INSERT INTO events(note) VALUES ('accepted')")
        conn.commit()
        status = "committed"
        code = 0
    except sqlite3.OperationalError as error:
        if "locked" not in str(error).lower():
            raise
        conn.rollback()
        status = "busy"
        code = 2
    finally:
        conn.close()
    print(json.dumps({"result": status, "elapsed_ms": round((time.monotonic() - started) * 1000, 1)}))
    return code


def run_writer(path: Path) -> dict:
    result = subprocess.run(
        [sys.executable, __file__, "--writer", str(path)],
        capture_output=True, text=True, timeout=8, check=False,
    )
    if result.returncode not in (0, 2):
        raise AssertionError("Writer failed unexpectedly: " + result.stderr)
    observation = json.loads(result.stdout)
    assert (result.returncode == 0) == (observation["result"] == "committed")
    return observation


def verify_mode(path: Path, mode: str) -> dict:
    conn = sqlite3.connect(path, timeout=0.15)
    actual = conn.execute("PRAGMA journal_mode = " + mode).fetchone()[0]
    assert actual.lower() == mode.lower()
    conn.execute("CREATE TABLE events (id INTEGER PRIMARY KEY, note TEXT NOT NULL)")
    conn.commit()

    reader = sqlite3.connect(path)
    reader.execute("BEGIN")
    assert reader.execute("SELECT COUNT(*) FROM events").fetchone()[0] == 0
    during_read = run_writer(path)

    if mode == "DELETE":
        assert during_read["result"] == "busy", during_read
    else:
        assert during_read["result"] == "committed", during_read
        assert reader.execute("SELECT COUNT(*) FROM events").fetchone()[0] == 0, "Reader lost its snapshot"
    reader.rollback()
    reader.close()

    after_read = run_writer(path)
    assert after_read["result"] == "committed"

    writer_lock = sqlite3.connect(path)
    writer_lock.execute("BEGIN IMMEDIATE")
    competing_write = run_writer(path)
    assert competing_write["result"] == "busy", "Two writers cannot commit simultaneously"
    writer_lock.rollback()
    writer_lock.close()

    after_release = run_writer(path)
    assert after_release["result"] == "committed"
    checkpoint = None
    if mode == "WAL":
        checkpoint = conn.execute("PRAGMA wal_checkpoint(TRUNCATE)").fetchone()
        assert checkpoint[0] == 0, checkpoint

    backup_path = path.with_suffix(".backup.sqlite")
    backup = sqlite3.connect(backup_path)
    conn.backup(backup)
    backup.close()
    source_rows = conn.execute("SELECT COUNT(*) FROM events").fetchone()[0]
    with sqlite3.connect(backup_path) as restored:
        assert restored.execute("SELECT COUNT(*) FROM events").fetchone()[0] == source_rows
    conn.close()
    return {
        "journal": mode,
        "read_blocks_writer": during_read["result"] == "busy",
        "reader_hold_competing_writer_ms": during_read["elapsed_ms"],
        "write_blocks_write": competing_write["result"] == "busy",
        "writer_contention_ms": competing_write["elapsed_ms"],
        "after_release": after_release["result"],
        "backup_restore_rows": source_rows,
        "checkpoint": checkpoint,
    }


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="fast-sqlite-contention-") as directory:
        root = Path(directory)
        delete = verify_mode(root / "rollback.sqlite", "DELETE")
        wal = verify_mode(root / "wal.sqlite", "WAL")
    print(json.dumps({"rollback": delete, "wal": wal}, indent=2))
    print("SQLITE CONTENTION / WAL CHECKPOINT / CONSISTENT BACKUP: PASS")


if __name__ == "__main__":
    if len(sys.argv) == 3 and sys.argv[1] == "--writer":
        sys.exit(competing_writer(Path(sys.argv[2])))
    main()
