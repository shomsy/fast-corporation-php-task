# ADR-0010 — Exact HTTP Request Targets and Safe Method Negotiation

Status: Accepted / C-22 (2026-10-09). Owner: Runtime/Http.

## Contract

- A request target must be origin-form: absolute path with an optional query. Reject malformed percent encodings, encoded controls, backslashes, URI fragments, scheme/authority forms, and overlong targets as **400 Bad Request**, before loading routes or touching business state. Route matching operates on validated raw paths only.
- Registered methods alone define a route. A registered GET additionally exposes an implicit HEAD, and recognized paths advertise OPTIONS. An absent path returns 404, a recognized path with an unsupported verb returns 405 and a deterministic Allow list.
- OPTIONS emits 204 with Allow and executes no business handler. HEAD uses the same central access guard as GET, emits an empty body, and deliberately avoids executing GET feature handlers because Page A/B GETs record visit events. The transport output boundary additionally suppresses HEAD bodies for errors.
- This intentionally conservative HEAD metadata response is not a content-length-identical execution of GET. If exact HEAD representation headers are required later, add owner-specific metadata handlers without audit writes rather than executing tracked GETs.
- All dynamic responses continue to receive the existing no-store/browser security policy; 400 is a client-input error, unexpected database/application failures remain redacted 500s.

Evidence: `tests/Runtime/HttpProtocolTest.php`, `tests/Runtime/RouterTest.php`, and actual `tests/Flow/live_smoke.py` HEAD/OPTIONS requests. No third-party HTTP framework is introduced.
