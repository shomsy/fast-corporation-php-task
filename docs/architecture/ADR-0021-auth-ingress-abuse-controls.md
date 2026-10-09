# ADR-0021 — Auth abuse enforcement at a single trusted Nginx ingress

**Decision:** Accepted as a **locally tested reference control** for C-05 on 2026-10-09. This is **not** evidence that a public deployment enforces it. **Owner:** deployment ingress/security, not PHP business identity.

## Invariant

Every POST to `/login`, `/register`, `/api/accounts/login` and `/api/accounts/register` that enters through this protected gateway is subject to **one shared, bounded per-connected-peer** budget before it reaches an application worker. The limiter returns HTTP **429** with a conservative **Retry-After: 60** and `Cache-Control: no-store`. It never changes an account password/role or locks an account; successful business auth, CSRF, audit and existing server-side authorization remain authoritative in PHP.

## Checked-in configuration

- `ops/nginx/http-auth-limits.conf` belongs inside the **http context**. It keys only the four POST paths by `$binary_remote_addr`, sets `rate=6r/m` and defines a sanitized access-log format.
- `ops/nginx/server-auth-errors.conf` belongs in the **application server context**. It selects 429, adds Retry-After, and uses a named internal error handler outside the throttled location.
- `ops/nginx/location-auth-limit.conf` belongs inside **every application proxy location that can receive those POSTs**; the reference placement applies to one catch-all location. `burst=2 nodelay` allows three immediate calls for an idle connected peer, then rejects the next. No unprotected alternate location may proxy the same auth POST path.
- The rate-limit map has an empty key for GETs, ordinary pages, downloads and unrelated POST routes; those do not consume the auth budget. `$uri` uses normalized URI without query to avoid route-alias bypass of the key.

A deployment engineer must integrate these snippets into the actual TLS/proxy/PHP-FPM configuration, test that includes are loaded, and use the sanitized `fc_auth_rate` log format with an explicit access/retention policy. Do not paste the snippets into unrelated Nginx contexts or expose a second unprotected backend port.

## Threat model and failure boundaries

1. **Client IP trust:** The default rate key is the TCP peer. Untrusted `X-Forwarded-For` does not change it. If a CDN or load balancer sits ahead of Nginx, all its clients may share one budget — this is safe against forged-XFF bypass but **not availability-safe** for legitimate clients. Reopen deployment only after inventorying the actual intermediary and configuring Nginx `real_ip` exclusively for explicitly trusted source CIDRs, with positive and forged-header tests.
2. **Multiple app workers versus ingress replicas:** The shared Nginx zone spans workers on one gateway **before both application upstreams**, which the test proves. Two independently exposed Nginx gateways have **independent counters**; the aggregate policy may multiply. Require one controlled shared ingress, or introduce an earned distributed edge limiter before multi-ingress deployment. Never claim per-IP global quota across gateways from this sample.
3. **Lockout DoS:** No per-account lockout is added (attackers cannot freeze a victim's account with bad passwords). The peer-wide policy can still slow legitimate people sharing a NAT; capacity/abuse owner must adjust the rate and monitor false positive 429s with real traffic.
4. **Logging and secrets:** The custom access log records the direct peer IP, method, normalized path, status and Nginx limit decision; it excludes body, credentials, cookies and query. Stock Nginx **error logs may include full request URIs and query strings**; never send credentials in query parameters, restrict access, define retention/deletion and test deployed log settings. Source IP is personal data in many settings.
5. **Fail-closed rollout:** Traffic must reach the protected ingress; no bypass via direct backend ports, other domain listeners or stale replicas. Test TLS and forwarded scheme at the actual deployment. A green local Nginx test is not public-production GO.

## Executable negative tests

`python3 tests/Flow/auth_ingress_rate_limit.py` launches an ephemeral, localhost-only Docker Nginx by immutable image digest (with automatic cleanup), two independent mock application upstreams, and the actual three checked-in snippets. It runs `nginx -t` first and verifies:

- GET sign-in unaffected; the first three mixed SSR/JSON authentication POSTs reach **both** upstreams;
- the fourth POST, even on a different auth endpoint with a forged `X-Forwarded-For`, returns 429 with `Retry-After: 60` and no-store;
- unrelated POST and GET still return 200, and another forged XFF does not reset the limiter;
- rejection is recorded (`limit=REJECTED`), and submitted test credentials do not appear in the logs;
- the container is cleaned up and no public service is changed.

**Local outcome:** PASS with pinned `nginx@sha256:65645c7bb6a0661892a8b03b89d0743208a18dd2f3f17a54ef4b76fb8e2f2a10`. GitHub Actions can run this isolated probe, but its remote completion must be checked separately.

**Source-backed method:** Tanya Janca, *Alice and Bob Learn Application Security* (First Edition), Chapter 8, printed pp. 177–179 / physical PDF pp. 205–207; original `/home/shomsy/Documents/Books/Software/Backend/Alice and Bob Learn Application Security — First Edition — Tanya Janca.pdf`. Those pages support security-as-code, negative abuse-test stories (including brute force) and TLS/operational accountability, **not these exact rate numbers or a global distributed guarantee**. Only these pages were inspected. Also applied Compass Security's negative abuse-path and sensitive logging guides.

## Release status

- **C-05 local ingress sample and negative acceptance:** GO (tested).
- **Production ingress configuration, public TLS and multi-gateway/real-IP semantics:** unverified. Public-production remains **NO-GO** under C-47 until the owner collects target-environment evidence, real repeated-attempt 429 traces, login success under correct forwarded identity, service permissions, RPO/RTO and capacity measurements.
