# ADR-0013 — Fixed executable download artifact and bounded delivery

Status: Accepted for C-23 (2026-10-09). Owner: Downloads.  
Public-production deployment: **NO-GO** until the actual artifact's distributor/security policy and operational controls are approved.

## Threat and accepted artifact

The browser never selects a local path, artifact name, hash or URL. The `Downloads` owner receives an **absolute server-selected** path for `assets/sample.exe`, which remains outside `public/`. It is a harmless, 1,024-byte Windows PE sample used solely to demonstrate the interview download action. It is not executed by PHP, the web server or the user as part of acceptance. The repository tracks the file as mode **0644**, not a Linux-executable or group/world-writable program.

The release composition root explicitly pins the SHA-256 of the original reviewed bytes:

```
31a97b0f71277adbac9bfde08c935c3e97b0bfa61d59c807c0a900660080cb80  assets/sample.exe
```

On a deliberate replacement, review the new binary as a new release and update that pin only after confirming the required business behavior. Never accept the hash supplied by a browser or blindly trust a same-directory mutable manifest.

## Read and accounting contract

`DownloadFile::read()` validates that the file is a readable regular file and **not a symlink**, that the declared size is 1–2,097,152 bytes (2 MiB), and reads at most `maxBytes + 1` bytes from a binary stream. A changed, truncated, symlinked, oversized or hash-mismatched artifact throws `ArtifactUnavailable`, mapped by the transport adapter to a generic HTTP **503**, without a successful download event. The success event is recorded only **after** the bytes have passed the size and integrity checks. An activity-database failure is a real operational **500**, never mislabeled as a missing artifact.

Successful delivery remains `application/octet-stream`, `Content-Disposition: attachment; filename="sample.exe"`, exact Content-Length, no-cache/no-sniff and user authorization plus CSRF protection. The event means a response was **prepared**, not proof that the browser completed its transfer.

The fixed 2 MiB application budget prevents an unexpectedly replaced large file from requiring unbounded PHP memory. For larger artifacts, measure traffic and select a separate stream-first transport with bounded buffering, independently verified integrity and a carefully defined audit time (prepared vs completed). This tiny fixed artifact does **not** justify an abstract storage system or custom async file server.

## Acceptance evidence and limitations

- `tests/Downloads/DownloadFileTest.php`: normal fixed content, missing path, symlink, oversized budget, changed SHA-256, audit write failure.
- `python3 tests/Flow/live_smoke.py`: authenticated+CSRF download returns original PE `MZ` bytes as an attachment and keeps audit errors distinct.
- `composer quality`: checks PHP correctness, architecture and full suite; the original binary SHA must remain unchanged during the accepted release.

A compromised filesystem writer could swap the file between preliminary checks and opening; validating the bytes *actually loaded into the response* against the pinned SHA-256 prevents an unreviewed response body from being accepted. This does not make untrusted executables intrinsically safe. The downloaded content is still executable on Windows and should never be run for test acceptance.
