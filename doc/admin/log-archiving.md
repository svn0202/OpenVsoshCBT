# Daily HTTP log rotation and central archives

Use `tools/log-archive/` to move closed Apache logs to a mounted archive share.
Current logs stay on the application server. A separate collector transfers closed
archives, reads them back, verifies SHA-256 and gzip integrity, then requests removal
of the matching source. An interrupted transfer leaves the source in place and a
retryable `.partial` destination. Existing archives with different checksums stop
the job instead of being overwritten.

## Application server

Install `source.py` as `/usr/local/sbin/tcexam-log-archive-source`, owned by root,
mode 0755. It requires Python 3, gzip, nice, Linux `/proc` and the standard logrotate
state file `/var/lib/logrotate/logrotate.status`.

Review and install `httpd.logrotate` as `/etc/logrotate.d/httpd`. Avoid duplicate
rules for the same logs. Validate with `logrotate --debug /etc/logrotate.conf` and
ensure the distribution's daily logrotate timer is enabled. The template uses
`httpd.service`; adapt it for distributions with a different Apache unit or paths.

`rotate -1` preserves archives until the collector verifies their transfer. There
is no local age-based deletion. `delaycompress` protects recently rotated logs;
the source helper compresses them once no process has an open descriptor. The
helper locks the logrotate state file while listing, compressing, reading or
removing archives. A conflicting run fails and retries on the next timer event.

## Collector

Install `collector.py` as `/usr/local/sbin/tcexam-log-archive`, root-owned, mode
0750. Install a private configuration based on `config.example.json` at
`/etc/tcexam-log-archive/config.json`. Keep that directory root-owned, mode 0700.
Set the exact mount point, filesystem type, share source, archive subdirectory
and SSH target. The collector refuses a missing mount or a mismatched filesystem.

Provision the archive mount separately, with persistent system mount configuration.
For CIFS, use `cache=none` so verification reads go back to the server. This
workflow verifies data reads and requests fsync; it does not prove durability
through a storage power failure. Use a dedicated archive directory and retain
its `manifest.jsonl` alongside the archives.

Generate a dedicated SSH key at `/etc/tcexam-log-archive/id_ed25519`. Pin the
application server's verified host key in `known_hosts` in the same directory.
On the application server, restrict this public key in the SSH account's
`authorized_keys`, substituting the collector address and public key:

```text
from="COLLECTOR_ADDRESS",restrict,command="sudo -n /usr/local/sbin/tcexam-log-archive-source" ssh-ed25519 PUBLIC_KEY
```

The account needs passwordless sudo for this root-owned helper only. The forced
command accepts a JSON request on stdin and limits operations to closed rotated
logs under `/var/log/httpd`. Do not grant a new collector key unrestricted shell
access. Keep keys, deployment configuration and operational reports outside Git.

Install the service and timer templates in `/etc/systemd/system/`. Change
`RequiresMountsFor` to match the configured archive mount. Run `systemctl
daemon-reload`, start the service once and inspect its journal and destination
manifest. Confirm that transferred source archives disappeared and Apache still
writes current logs, then enable the timer with `systemctl enable --now
tcexam-log-archive.timer`. It collects every hour at minute 35; rotation itself
remains daily. A second manual run should succeed with zero pending archives.

## Operations and recovery

Monitor both the application disk and failed collector runs. If storage or SSH
is unavailable, archives accumulate locally rather than being discarded. Repair
the connection or mount and rerun the collector. Do not delete pending source
files merely because a destination `.partial` exists.

Central archive expiration is deliberately not configured; define it separately
according to the agreed retention policy. To stop collection, disable the timer
and let an active run finish. Preserve the destination manifest and archives.
Restore the previous logrotate configuration only after deciding how to handle
untransferred logs.
