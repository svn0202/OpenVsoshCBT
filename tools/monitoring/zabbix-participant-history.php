<?php
// PostgreSQL monitoring: keep one snapshot and the original counting boundaries.
function tce_monitor_history_sql(string $p): string
{
    return <<<SQL
WITH limits AS (SELECT date_trunc('hour',localtimestamp)+floor(extract(minute from localtimestamp)/30)*interval '30 minutes' AS b), ticks AS (SELECT generate_series(b-interval '48 hours',b,interval '30 minutes') ts FROM limits), attempts AS MATERIALIZED (SELECT u.testuser_user_id uid,u.testuser_creation_time s, LEAST(u.testuser_creation_time+t.test_duration_time*interval '1 minute',t.test_end_time,CASE WHEN u.testuser_status>=4 AND COALESCE(u.testuser_close_reason,'')<>'timeout' THEN COALESCE(u.testuser_last_activity,u.testuser_creation_time+t.test_duration_time*interval '1 minute') ELSE u.testuser_creation_time+t.test_duration_time*interval '1 minute' END) e FROM {$p}tests_users u JOIN {$p}tests t ON t.test_id=u.testuser_test_id CROSS JOIN limits WHERE u.testuser_status>0 AND NOT COALESCE(u.testuser_pregenerated,false) AND u.testuser_creation_time<=b AND u.testuser_creation_time+t.test_duration_time*interval '1 minute'>=b-interval '49 hours'), events AS (
 SELECT date_trunc('hour',s)+floor(extract(minute from s)/30)*interval '30 minutes'+interval '30 minutes' ts,
        uid, true started
 FROM attempts
 UNION ALL
 SELECT point.ts, a.uid, false started
 FROM attempts a CROSS JOIN limits
 CROSS JOIN LATERAL generate_series(
   GREATEST(date_trunc('hour',a.s)+floor(extract(minute from a.s)/30)*interval '30 minutes',b-interval '48 hours'),
   LEAST(a.e,b), interval '30 minutes'
 ) point(ts)
 WHERE a.s<=point.ts AND a.e>point.ts
), counts AS (
 SELECT ts,count(distinct uid) FILTER (WHERE started) started_30m,
           count(distinct uid) FILTER (WHERE NOT started) in_progress
 FROM events GROUP BY ts
)
SELECT extract(epoch FROM ticks.ts AT TIME ZONE 'Asia/Yekaterinburg')::bigint clock,
       COALESCE(counts.started_30m,0::bigint) started_30m,
       COALESCE(counts.in_progress,0::bigint) in_progress
FROM ticks LEFT JOIN counts ON counts.ts=ticks.ts
ORDER BY ticks.ts
SQL;
}

// Integration tests load only the SQL builder, without application credentials.
if (defined('OPENVSOSH_MONITOR_SQL_ONLY')) {
    return;
}
// Same two series and attempt-end formula as OpenVsoshCBT/outputs/participant-history/README.md.
require '/var/www/html/shared/config/tce_db_config.php';
function qc($s){return "'".str_replace(["\\","'"],["\\\\","\\'"],(string)$s)."'";}
$c=@pg_connect('host='.qc(K_DATABASE_HOST).' port='.qc(K_DATABASE_PORT).' dbname='.qc(K_DATABASE_NAME).' user='.qc(K_DATABASE_USER_NAME).' password='.qc(K_DATABASE_USER_PASSWORD));if(!$c)exit(1);
pg_query($c,'BEGIN READ ONLY');pg_query($c,"SET LOCAL statement_timeout='10s'");pg_query($c,"SET LOCAL TIME ZONE 'Asia/Yekaterinburg'");
// Avoid compiling the short-lived series expansion on each collector run.
pg_query($c,"SET LOCAL jit=off");
$p=K_TABLE_PREFIX;
$sql=tce_monitor_history_sql($p);
$r=@pg_query($c,$sql);if(!$r)exit(2);$rows=pg_fetch_all($r);foreach($rows as &$row)foreach($row as &$v)$v=(int)$v;unset($row,$v);pg_query($c,'ROLLBACK');echo json_encode($rows),"\n";
