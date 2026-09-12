# Deployment with two slots

Keep two independently managed application services on distinct loopback ports.
Keep environment files, credentials, deployment addresses, real session identifiers,
incident evidence and database extracts outside the public repository.

1. Acquire an exclusive deployment lock; record and back up the active proxy and units privately.
2. Build an immutable candidate. Check database/schema compatibility, shared session storage,
   cache compatibility and resource headroom. Configure shared SELinux labels without
   relabelling volumes used by the active container. Avoid setup/installer side effects.
3. Start only the inactive slot. Verify login, assets and saving using synthetic data.
   Also submit incorrect credentials: the expected warning must preserve a usable
   form without PHP header errors or password echo. Verify effective controller roles
   from the central role map, not only preserved installation constants.
4. Atomically change both proxy directions; run the proxy configuration check, then
   graceful reload. Keep the previous service running for in-flight requests.
5. Verify an already-open synthetic form and the public endpoint from an external host.
   Observe errors and latency for at least five minutes; prove old requests have drained.
   Compare application error logs as well as HTTP status and server stderr: a PHP
   warning rendered into an HTTP 200 response is still an acceptance failure.
6. Enable the active slot at boot; stop and disable the old slot only after drain.

For rollback, first start and verify the previous slot, then reverse the proxy change
with configtest and graceful reload. Drain the failed slot before stopping it.
Do not restore an old database backup over answers written after deployment.

For the CSRF format change, follow the [dual-reader rollout](../development/csrf-token-migration.md).
An old binary that cannot validate v2 is not a valid rollback target once v2 is issued.

Host-specific unit files, network topology, image inventories and execution reports
belong in private operational documentation. Reboot recovery must be tested separately.

For bounded log collection and answer persistence checks, see the
[log investigation procedure](error-log-collection.md) (Russian).

## CPU and memory expansion before deployment

The safe pause point is after the backup and candidate build, before schema changes,
starting the candidate or switching traffic. Record the completed steps privately.
Resume a deliberately paused deployment only when the operator requests it.

1. After adding resources in the hypervisor, verify what the guest has actually
   brought online: `lscpu`, `/sys/devices/system/cpu/online`,
   `/sys/devices/system/cpu/present`, `free -h` and `lsmem`.
   Hot-add support alone does not prove that newly added memory is online. Inspect
   `/sys/devices/system/memory/auto_online_blocks`; follow the guest OS policy for
   any offline blocks. If a reboot is required, treat it as a separate maintenance
   step and verify application and database recovery afterwards.
2. Inspect the active container's CPU quota, CPU set and memory limits with
   `podman inspect`. Check systemd unit and parent slice limits as well: `MemoryMax`,
   `MemoryHigh`, `CPUQuotaPerSecUSec` and `AllowedCPUs`. Zero container limits do not
   override restrictions imposed by a parent cgroup. Without such restrictions,
   the running container can use resources brought online by the guest without
   rebuilding the image or redeploying the application.
3. If explicit limits need adjustment, Podman supports live changes through
   `podman update --cpus <count> --memory <size> <container>` where supported by
   the host's cgroup configuration. Persist the same policy in the managed unit
   or container definition so it survives recreation. Verify the effective limits
   after changing them. Do not lower a memory limit below current usage.
4. Additional hardware does not automatically increase Apache concurrency. Inspect
   the enabled MPM and all included configuration overrides before tuning
   `MaxRequestWorkers` and `ServerLimit`. For prefork, estimate the worker budget
   from measured memory under representative requests, leaving room for the OS,
   database, caches and both slots during deployment. Also check database connection
   capacity and latency. Do not derive worker count from CPU count alone. PHP's
   `memory_limit` applies to individual script executions, not the whole service.
5. Apply changes requiring application restart through the inactive slot and the
   normal acceptance/switch/drain procedure above. Apache `ServerLimit` cannot be
   changed by a graceful restart; use a fresh process in the candidate slot. Database
   settings requiring restart need their own maintenance step: two application
   slots do not make a shared database restart seamless.
6. Record the effective resources and limits privately. Before resuming deployment,
   verify service health, database connectivity, backup freshness and enough headroom
   for both slots. After tuning, compare request latency, errors, worker saturation,
   memory pressure and database connections under representative load.

References: [Linux memory hotplug](https://kernel.org/doc/html/latest/admin-guide/mm/memory-hotplug.html),
[Podman live resource updates](https://docs.podman.io/en/latest/markdown/podman-update.1.html),
[Apache MPM limits](https://httpd.apache.org/docs/2.4/mod/mpm_common.html).

## Connection reuse between reverse proxies

Inspect the actual upstream `Keep-Alive` response before changing the proxy pool.
The proxy must retire idle connections earlier than the next hop. For an Apache
hop advertising `timeout=5, max=100`, use the following inside nginx's upstream:

```nginx
keepalive 64;
keepalive_timeout 1s;
keepalive_requests 90;
```

These values apply to the upstream pool, not the client keepalive or request/read
timeouts. Keep the idle timeout and request limit below the corresponding Apache
limits if those limits change. Validate with `nginx -t`, reload gracefully, and
compare immediate upstream-header closures and final HTTP 502 outcomes under live
traffic. This mitigates reuse of connections retired by the next hop; it does not
prove that all connection closures share that cause. Do not enable automatic
retry of non-idempotent POST requests to hide transport errors. Correlate saving
with request IDs and database versions before interpreting a failure as lost work.

Reference: [nginx upstream keepalive](https://nginx.org/en/docs/http/ngx_http_upstream_module.html#keepalive_timeout).

## Authentication and attempt-creation regression checks

Validate form CSRF before authentication can rotate a session or print a warning.
JSON endpoints validate their workflow-scoped token and return a distinct reason
for session loss, denied access or expired CSRF. An expired ordinary exam form
must not automatically navigate away; retain the submitted draft for recovery.
A rejected fingerprint must clear authenticated state and initialize a fresh
anonymous fingerprint, so a subsequent login can succeed.

Attempt creation must commit its parent, questions and answers together. Serialize
creation for one participant, recheck existing attempts after acquiring the lock,
and roll back incomplete variants. Status readers must not delete in-progress
attempts. Pregeneration must use the same lock while retaining ownership of its
transaction. Test concurrent tabs, failure halfway through generation, existing
answers, ordinary starts and pregenerated starts. The integration suite includes
a small concurrent cohort by default; a larger performance profile remains opt-in.
