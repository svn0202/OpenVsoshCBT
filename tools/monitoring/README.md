# PostgreSQL aggregate monitoring

Run these standalone PHP scripts inside the application container so they read its
existing `shared/config/tce_db_config.php`. They emit the same JSON fields as the
original collectors, use a read-only transaction and the `Asia/Yekaterinburg` zone.
No cached totals or participant records are returned.

The summary groups attempts by user once, retaining separate attempt counts and
unique-user counts. Sums are cast back to `bigint`; empty input still returns zero.
The history emits start events into the next half-hour bucket and progress events
into the ticks actually covered by each attempt. The original 49-hour eligibility
filter, end formula, strict end boundary, 97 ticks and distinct-user counts remain.
JIT is disabled only inside the history collector's transaction.

`test/integration/MonitoringQueryEquivalenceTest.php` compares the old queries and
these builders on read-only SQL fixtures, including NULLs, repeated users, excluded
statuses, pregeneration, timeouts, blocked attempts, empty input and tick boundaries.
Use `TCEXAM_DB_TYPE=POSTGRESQL` and the usual `TCEXAM_DB_*` connection variables to
run it with PHPUnit. The test also checks column names/types and explicit expected
counts. Production row comparisons and execution plans are private operational
artifacts, not repository fixtures.

Operational installation: preserve the existing host-specific collector, back up
the two scripts and atomically replace their PHP files only after comparison against
the current database. The scripts have no dependency on other new files.
