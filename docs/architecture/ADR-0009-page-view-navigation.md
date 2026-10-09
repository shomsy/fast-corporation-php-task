# ADR-0009 — Accepted Page Views, Prefetch and SPA Navigation

Status: Accepted / C-19, 2026-10-09. Owners: CowOffer/Downloads on the server, ActivityTracking audit vocabulary, future SPA feature coordinators.

## Counting policy

A Page A/B view is a **successful, authorized, deliberately activated navigation**, not a claim that all pixels rendered or a browser tab received user attention. Normal SSR `GET /page-a` and `GET /page-b` count one accepted `view-page` per request; refresh and user-initiated back/forward revisits count again. Unauthorized requests count zero. A page must not create an extra event for a Buy Cow POST or binary download POST (those are `button-click` events).

Speculative HTTP requests with recognized `Sec-Purpose: prefetch`, `Purpose: prefetch` or Firefox `X-Moz: prefetch` markers return empty 204 after authorization and record **no** Page A/B view. Recognition is a UX/statistics rule only, **never** an authorization bypass: these request headers are untrusted and may be spoofed. Ordinary accepted SSR navigations without markers remain counted; browsers and proxies not sending recognized markers can still cause counted requests. This is not a bot detection system or exact human-attention analytics.

## Same-origin Vanilla JS SPA contract (future C-37..C-46)

The proposed API uses explicit signed-in, CSRF-protected `POST /api/cow-offer/view` and `POST /api/downloads/page/view` only when a feature screen becomes active. Do **not** produce page-view side effects from JSON/data GET, speculative preload, fetch-prefetch, rendering individual components or revalidating session metadata. A successful intentional SPA navigation (including back/forward activating that screen again) creates one accepted event; abort-before-activation creates none. Avoid duplicate instrumentation from both shell and feature owner. Use server-owned identity and permission checks; no actor supplied by JS. During migration, never mount the SPA and trigger the old SSR tracked GET for the same logical navigation; cut over one slice with parity tests.

Proof: `tests/Flow/HttpFlowTest.php::testPageNavigationEventsExcludePrefetchButIncludeReturnVisits` covers unauthorized prefetch, three recognized prefetch forms (no audit), two authorized Page A and Page B revisits (two events each). Existing end-to-end tests verify no unauthorized activity and accepted navigations. Revisit client-side page activation timing and unknown-outcome retries at C-40..C-45 before declaring SPA parity.
