# Deployment with two slots

Keep two independently managed application services on distinct loopback ports.
Keep environment files, credentials, deployment addresses, real session identifiers,
incident evidence and database extracts outside the public repository.

1. Acquire an exclusive deployment lock; record and back up the active proxy and units privately.
2. Build an immutable candidate. Check database/schema compatibility, shared session storage,
   cache compatibility and resource headroom. Configure shared SELinux labels without
   relabelling volumes used by the active container. Avoid setup/installer side effects.
3. Start only the inactive slot. Verify login, assets and saving using synthetic data.
4. Atomically change both proxy directions; run the proxy configuration check, then
   graceful reload. Keep the previous service running for in-flight requests.
5. Verify an already-open synthetic form and the public endpoint from an external host.
   Observe errors and latency for at least five minutes; prove old requests have drained.
6. Enable the active slot at boot; stop and disable the old slot only after drain.

For rollback, first start and verify the previous slot, then reverse the proxy change
with configtest and graceful reload. Drain the failed slot before stopping it.
Do not restore an old database backup over answers written after deployment.

Host-specific unit files, network topology, image inventories and execution reports
belong in private operational documentation. Reboot recovery must be tested separately.
