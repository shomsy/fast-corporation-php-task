# ADR-0016 — Make download side effects explicit without wrapper proliferation

Status: Accepted / C-30 (2026-10-09). Owner: Downloads. Rollback: revert isolated method rename and its verified callers.

## Change pressure, decision and proof

`DownloadFile::read(actorId, actorLabel)` was misleading: it performed an integrity-checked bounded binary read **and then recorded an accepted Download click** in SQLite. A method with a query-like name can be mistakenly invoked by a supposedly read-only preview, probe or cache, creating false history. Rename it to `prepareDownload(actorId, actorLabel)`; preserve both public named parameters and its result/exception contract. Its caller in `DownloadPages`, the owner tests and the memory probe now explicitly acknowledge this effect. No alias wrapper is retained: this is an internal monolith API with all call sites accounted for, not a versioned external library.

The effect contract remains: verify fixed artifact size, symlink and SHA-256 before audit, append the accepted click on successful preparation, return bounded bytes for the HTTP adapter to transmit. A prepared download **does not** certify client receipt or successful execution. The method retains the server-selected artifact, never a user path.

`AccountLifecycle` already provides a real application consistency boundary (registration + activity transaction, login rollback on audit error and mandatory logout revocation). `CurrentAccount`, `RequireSignedIn`, and `RequireAdmin` own explicit authoritative account checks and remain justified. Small `authorizedUser()` response helpers in two SSR adapters are local HTTP adaptations: extracting a generic shared authorization framework would introduce more coupling than it removes at present. Do not create `IAccountLifecycle` or equivalent one-implementation interfaces without a second implementation or tested boundary pressure.

## Executable evidence and compatibility

C-30 red test: six DownloadFile tests called the intended `prepareDownload()` before code change and failed with undefined method. After owner rename and adapter/test updates, those tests passed 6/25 and `php tests/Flow/download_memory_probe.php` remained 1024 bytes / 8 MiB PHP allocated peak. Check all `->read(` DownloadFile call sites when modifying this module; this public method rename is source-level compatibility change within this application only, not a change to HTTP routes or public payload.

The plan's broader API/LLD review is satisfied by a single proven fix plus documented deliberate non-changes. Avoid more renames for style alone; preserve current public named arguments and user MOI.
