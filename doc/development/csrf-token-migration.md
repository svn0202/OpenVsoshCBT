# CSRF CPU cost reduction: implementation and acceptance plan

Goal: remove password hashing from normal CSRF generation and verification while
preserving session isolation, script scoping, existing forms, and answer persistence.
Do not change password storage, authentication, authorization or cookie protections.
Production rollout follows successful CI and the acceptance checks below.

## Options and choice

- A random synchronizer token stored in the server session is fast and conventional,
  but adds session state and requires careful handling of concurrent initialization,
  session regeneration and several open forms.
- A versioned HMAC-SHA-256 token, signed with the configured installation secret and
  bound to the session, script and existing client fingerprint, avoids new database
  state. A random nonce per rendering prevents identical token values across pages.
  This is the preferred approach for this application's existing script-scoped API.
- Lower bcrypt cost still spends CPU on password-hardening work that a CSRF token
  does not need. SameSite/Origin checks alone would change the protection contract.
  Neither is the proposed replacement.

Use explicit length-delimited fields and a CSRF-specific signing context. Compare
MACs using hash_equals. Fail closed for missing/default signing secrets, missing
sessions, malformed/oversized tokens and unknown versions. Keep tokens out of logs,
URLs and browser persistent storage. Do not expose the signing key or session ID.

Sources: [OWASP CSRF prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html),
[PHP HMAC](https://www.php.net/manual/en/function.hash-hmac.php),
[constant-time comparison](https://www.php.net/manual/en/function.hash-equals.php),
[CSPRNG](https://www.php.net/manual/en/function.random-bytes.php).
This design uses OWASP's session-bound HMAC principle with the existing form field;
it does not introduce a double-submit cookie.

## Scope carried forward from the earlier proposal

The private proposal and production CPU profile dated 2026-09-11 were reviewed.
Their operational measurements remain private and are not a performance guarantee.
The first patch replaces CSRF only. A separate small patch removes eager password
hash creation during ordinary login: retain password verification, create a new hash
only in external-auth INSERT/UPDATE branches, and reuse it within that branch.
Test successful/failed local login, OTP, external-auth creation and synchronization.
Do not replace password hashing globally or change session_test_login. OpenVsoshCBT
session IDs already use random_bytes; no further session-ID change is needed here.
TMFCBT is a separate codebase with session-wide CSRF scope; this deployment must not
copy OpenVsoshCBT's script-scoped implementation into it.

Before claiming an application-wide gain, compare equivalent request rates and
concurrency, CPU and p50/p95 save latency, CSRF failures and actual persisted answers.
A PHP stack share is not a CPU percentage; database waits require separate analysis.
SQL tuning follows a fresh profile rather than being bundled with the token change.

## Required verification

1. Token round trip, nonce uniqueness, HTML-safe encoding, script/workflow scope and
   stable validation across repeated saves, two tabs, document versus fetch headers.
2. Rejection after changing session, script, fingerprint or installation secret;
   rejection of missing/default secrets and missing sessions. Verify long script and
   session prefixes cannot hide a different suffix; test unambiguous field framing.
3. Mutated version/nonce/MAC, truncation, padding, trailing bytes/newlines, NUL,
   non-hex data, empty and oversized input; malformed request field types at endpoints.
4. Legacy compatibility only during an explicit bounded rollout window. Reject
   unsupported password hash algorithms and excessive bcrypt cost before expensive
   verification. New-format failures must never fall back to password verification.
5. Anonymous login, successful login, failed login, logout/session replacement,
   stale login page, admin POST and participant answer save through real HTTP.
6. All five question types; save/reload, idempotent retry, stale-version conflict,
   CSRF refresh, heartbeat, focus, review flag, navigation and final save. Verify rejected writes leave
   database answers intact and the browser retains entered text.
7. Photo/PDF uploads with the per-test flag on/off, old-page submission after disabling,
   existing attachment download and archive, and preservation of the text answer.
8. Cross-slot compatibility: an old open form reaches the dual reader; a new token
   reaches another new reader and the rollback slot. No forced reload or logout.
9. Unit tests and static analysis on supported PHP versions; full MySQL/PostgreSQL
   integration suites. Benchmark generation, valid/invalid verification and repeated
   saves under representative concurrency. Record timing separately from correctness
   assertions; ensure normal HMAC operations never call password hashing functions.
10. Production: fresh verified backup, candidate health, synthetic authenticated
    save across switch, external HTTPS verification, five-minute error/latency
    observation, then drain. Keep deployment-specific results outside the repository.

## Rollout requirements

Install a reader supporting both formats before issuing the new format. A temporary
legacy-issuance mode allows the first slot switch to preserve compatibility with the
old binary. Once the old reader is drained, switch to HMAC issuance with a compatible
rollback slot. Legacy acceptance must have a fixed expiry, not a sliding window.
An expired legacy form may need the existing CSRF refresh flow or a page reload;
choose the window to cover ongoing exams and record it privately. Never roll HMAC
issuance back to a binary that cannot validate it.

Status: plan recorded; implementation and the acceptance matrix remain pending.
