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

## Implementation and local verification

The HMAC reader/issuer and lazy login hashing are implemented. Ordinary CSRF
operations are covered by spies that fail if password hashing is called; missing
entropy fails closed. Local unit, Chromium navigation/conflict/access-error checks
pass. Integration coverage includes all question types, token refresh, repeated
heartbeats, and disabling uploads while an old form remains open. The last case
must preserve the text answer and existing attachment while rejecting a new file.
MySQL's unchanged-row count must not be interpreted as a closed attempt.

Run unit tests with `php vendor/bin/phpunit --no-coverage --testsuite unit`.
Integration tests can use a dedicated native database via `TCEXAM_DB_*` and
`TCEXAM_APP_URL`; Docker is not required. Do not point destructive test fixtures
at production or the only retained production database copy. A built-in PHP server
does not enforce Apache's cache access rules: those checks require the configured
Apache server, including in CI. Do not weaken their assertions for a local shortcut.
Run `python3 test/answer_navigation_regression.py`,
`python3 test/answer_conflict_regression.py`, and
`python3 test/answer_access_regression.py` for browser regressions.
`php tools/benchmark_csrf.php` measures synthetic primitive CPU/wall time without
installation credentials or a database; it is not an application load test.

For phase one set `OPENVSOSH_CSRF_ISSUE_LEGACY=1` and
`OPENVSOSH_CSRF_LEGACY_UNTIL` to a fixed 10-digit Unix timestamp. After draining
the old binary, remove legacy issuance on the next slot while retaining the same
deadline. Legacy verification is off by default and after expiry. The rollback
slot must support v2. CI, cross-slot acceptance, migration and production observation
must be recorded as completed before declaring the rollout finished.

## Stable browser context (two-slot transition)

Language, compression and DNT are negotiated request headers. They must not cause
an authenticated browser's session or CSRF token to change. The stable context
uses a keyed, domain-separated digest of User-Agent; CSRF still binds the session,
script, random nonce and installation secret. A different User-Agent still requires
re-authentication. This fingerprint is supplementary; it is not an authentication credential.

1. Deploy the dual reader with `OPENVSOSH_STABLE_SESSION_CONTEXT` absent or `0`.
   It continues issuing the previous context for existing sessions, but can read
   stable sessions and tokens. Verify both slots, switch and drain the original binary.
2. Only after both runnable/rollback binaries contain the dual reader, deploy the
   same code with `OPENVSOSH_STABLE_SESSION_CONTEXT=1`. Validated old sessions are
   upgraded lazily; stable sessions are never downgraded on the other slot. A dual
   reader serving a migrated session also issues stable tokens when its flag is `0`.
3. Verify old forms, same-session cross-slot saves, language/compression/DNT changes,
   foreign-session/script/token rejection and User-Agent changes. Observe auth events.
   Existing v1 contexts that already fail fingerprint validation still require a
   fresh login; do not migrate an unvalidated session or replay a rejected POST.

Do not roll back to a binary without the dual reader after step 2. Bcrypt acceptance
remains subject to the original fixed deadline; this transition does not extend it.
HTTP integration tests use the enabled stable context; unit tests cover both issuers,
legacy verification and negotiation changes. No database schema migration is needed.
