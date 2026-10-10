#!/usr/bin/env python3
"""Verify every source and historical path has an individual final-review row."""
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
SOURCE = "851491d80bb48bbfddb9febabf9aa73f1596f6b0"
REVIEW = ROOT / "docs/reviews/final-interview-audit/FILE-BY-FILE-REVIEW.md"
MOVED = {
    "src/Accounts/AccessDenied.php",
    "src/Accounts/CurrentAccount.php",
    "src/Accounts/RequireAdmin.php",
    "src/Accounts/RequireSignedIn.php",
    "src/Accounts/SessionIdentity.php",
    "src/Accounts/SignedInAccount.php",
    "src/Accounts/AccountCredentials.php",
    "src/Accounts/BcryptPasswords.php",
    "src/Accounts/PasswordRejected.php",
    "src/Accounts/UsernameRejected.php",
}
REMOVED = {
    "src/CowOffer/.gitkeep",
    "src/Downloads/.gitkeep",
    ".php-cs-fixer.dist.php",
}

def git(*args: str) -> list[str]:
    return subprocess.check_output(["git", *args], cwd=ROOT, text=True).splitlines()

baseline = set(git("ls-tree", "-r", "--name-only", SOURCE))
current = set(git("ls-files", "--cached", "--others", "--exclude-standard"))
# PhpStorm XML exports and src.zip are explicitly preserved user-owned untracked files.
current = {
    path for path in current
    if path not in {"src.zip", ".xml"}
    and not (path.endswith(".xml") and "/" not in path)
    and (ROOT / path).exists()
}
expected = (baseline - MOVED - REMOVED) | MOVED | REMOVED | current
text = REVIEW.read_text(encoding="utf-8")
reviewed = set(re.findall(r"^\| `([^`]+)` \|", text, flags=re.MULTILINE))
missing = sorted(expected - reviewed)
extra = sorted(reviewed - expected)
duplicates = sorted(path for path in reviewed if text.count("| `" + path + "` |") != 1)
if missing or extra or duplicates:
    for label, paths in (("MISSING", missing), ("UNEXPECTED", extra), ("DUPLICATED", duplicates)):
        for path in paths:
            print(f"{label}: {path}")
    raise SystemExit(1)
required = [
    "REQUIREMENTS-MATRIX.md",
    "FILE-BY-FILE-REVIEW.md",
    "ARCHITECTURAL-DECISIONS-REVIEW.md",
    "FINDINGS-AND-FIXES.md",
    "FINAL-ACCEPTANCE.md",
]
for path in required:
    if not (ROOT / "docs/reviews/final-interview-audit" / path).is_file():
        raise SystemExit("Missing audit artifact: " + path)
print(f"FINAL AUDIT COVERAGE PASS: {len(reviewed)} unique file/path dispositions; {len(expected)} expected.")
