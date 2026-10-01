<?php
// PostgreSQL monitoring: keep one snapshot and the original counting boundaries.
function tce_monitor_summary_sql(string $p): string
{
    return <<<SQL
WITH per_user AS (
 SELECT u.testuser_user_id,
 bool_or(u.testuser_last_activity>=localtimestamp-interval '5 minutes') active_5m,
 count(*) FILTER (WHERE u.testuser_status IN (1,2,3) AND COALESCE(u.testuser_close_reason,'')='' AND u.testuser_creation_time+t.test_duration_time*interval '1 minute'>localtimestamp AND t.test_end_time>localtimestamp) in_progress,
 count(*) FILTER (WHERE (u.testuser_close_reason='completed' OR (u.testuser_status IN (3,4) AND COALESCE(u.testuser_close_reason,'') NOT IN ('timeout','blocked')))) completed_total,
 count(*) FILTER (WHERE (u.testuser_close_reason='completed' OR (u.testuser_status IN (3,4) AND COALESCE(u.testuser_close_reason,'') NOT IN ('timeout','blocked'))) AND u.testuser_last_activity>=date_trunc('day',localtimestamp)) completed_today,
 count(*) FILTER (WHERE (u.testuser_close_reason='completed' OR (u.testuser_status IN (3,4) AND COALESCE(u.testuser_close_reason,'') NOT IN ('timeout','blocked'))) AND t.test_begin_time<=localtimestamp AND t.test_end_time>localtimestamp) completed_open
  FROM {$p}tests_users u JOIN {$p}tests t ON t.test_id=u.testuser_test_id WHERE NOT COALESCE(u.testuser_pregenerated,false) AND u.testuser_status BETWEEN 1 AND 4
 GROUP BY u.testuser_user_id
)
SELECT count(*) FILTER (WHERE testuser_user_id IS NOT NULL AND active_5m) active_5m,
 COALESCE(sum(in_progress),0)::bigint in_progress,
 COALESCE(sum(completed_total),0)::bigint completed_total,
 count(*) FILTER (WHERE testuser_user_id IS NOT NULL AND completed_total>0) completed_users_total,
 COALESCE(sum(completed_today),0)::bigint completed_today,
 COALESCE(sum(completed_open),0)::bigint completed_open,
 count(*) FILTER (WHERE testuser_user_id IS NOT NULL AND completed_open>0) completed_users_open
FROM per_user
SQL;
}

// Integration tests load only the SQL builder, without application credentials.
if (defined('OPENVSOSH_MONITOR_SQL_ONLY')) {
    return;
}
// Fixed aggregate queries; no participant data or session contents leave this process.
require '/var/www/html/shared/config/tce_db_config.php';
function quote_conn($s) { return "'".str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$s)."'"; }
$c=@pg_connect('host='.quote_conn(K_DATABASE_HOST).' port='.quote_conn(K_DATABASE_PORT).' dbname='.quote_conn(K_DATABASE_NAME).' user='.quote_conn(K_DATABASE_USER_NAME).' password='.quote_conn(K_DATABASE_USER_PASSWORD));
if (!$c) { exit(1); }
function query($sql) { global $c; $r=@pg_query($c,$sql); if (!$r) {fwrite(STDERR,"Monitoring query failed\n");exit(2);} return pg_fetch_all($r); }
query('BEGIN READ ONLY');query("SET LOCAL statement_timeout='8s'");query("SET LOCAL TIME ZONE 'Asia/Yekaterinburg'");
$p=K_TABLE_PREFIX;
$now='localtimestamp';
// Archived/reset attempts (status >=5) and pregenerated rows are excluded from current attempts.
$valid="NOT COALESCE(u.testuser_pregenerated,false) AND u.testuser_status BETWEEN 1 AND 4";
$completed="(u.testuser_close_reason='completed' OR (u.testuser_status IN (3,4) AND COALESCE(u.testuser_close_reason,'') NOT IN ('timeout','blocked')))";
$inprogress="u.testuser_status IN (1,2,3) AND COALESCE(u.testuser_close_reason,'')='' AND u.testuser_creation_time+t.test_duration_time*interval '1 minute'>localtimestamp AND t.test_end_time>localtimestamp";
$out=[];
$out['users_total']=(int)query("SELECT count(*) n FROM {$p}users WHERE user_level>0")[0]['n'];
$out['tests_open']=(int)query("SELECT count(*) n FROM {$p}tests WHERE test_begin_time<=localtimestamp AND test_end_time>localtimestamp")[0]['n'];
$r=query(tce_monitor_summary_sql($p))[0];
foreach($r as $k=>$v)$out[$k]=(int)$v;
// Summary counts are people, including timed-out completions, not attempts.
$r=query("SELECT count(distinct u.testuser_user_id) FILTER (WHERE u.testuser_last_activity BETWEEN localtimestamp-interval '1 minute' AND localtimestamp) active_1m, count(distinct u.testuser_user_id) FILTER (WHERE u.testuser_last_activity BETWEEN localtimestamp-interval '5 minutes' AND localtimestamp AND $inprogress) users_in_progress_5m FROM {$p}tests_users u JOIN {$p}tests t ON t.test_id=u.testuser_test_id WHERE $valid")[0];
foreach($r as $k=>$v)$out[$k]=(int)$v;
// An archived completed attempt still counts as a completion; blocked attempts do not.
$out['completed_users_today_including_timeout']=(int)query("SELECT count(distinct u.testuser_user_id) n FROM {$p}tests_users u JOIN {$p}tests t ON t.test_id=u.testuser_test_id WHERE NOT COALESCE(u.testuser_pregenerated,false) AND u.testuser_status>0 AND COALESCE(u.testuser_close_reason,'')<>'blocked' AND (u.testuser_close_reason IN ('completed','timeout') OR u.testuser_status>=4 OR (u.testuser_status IN (1,2,3) AND LEAST(u.testuser_creation_time+t.test_duration_time*interval '1 minute',t.test_end_time)<=localtimestamp)) AND (CASE WHEN u.testuser_close_reason='timeout' OR u.testuser_status IN (1,2,3) THEN LEAST(u.testuser_creation_time+t.test_duration_time*interval '1 minute',t.test_end_time) ELSE u.testuser_last_activity END) BETWEEN date_trunc('day',localtimestamp) AND localtimestamp")[0]['n'];
$tests=query("SELECT t.test_id::text id,t.test_name AS name, (t.test_begin_time<=localtimestamp AND t.test_end_time>localtimestamp)::int is_open, count(u.testuser_id) started, count(u.testuser_id) FILTER (WHERE $completed) completed, count(distinct u.testuser_user_id) FILTER (WHERE $completed) completed_users, count(u.testuser_id) FILTER (WHERE $inprogress) in_progress FROM {$p}tests t LEFT JOIN {$p}tests_users u ON u.testuser_test_id=t.test_id AND $valid WHERE t.test_end_time>=date_trunc('day',localtimestamp) AND t.test_begin_time<date_trunc('day',localtimestamp)+interval '1 day' GROUP BY t.test_id ORDER BY t.test_id");
foreach($tests as &$row)foreach(['is_open','started','completed','completed_users','in_progress'] as $k)$row[$k]=(int)$row[$k];unset($row);
$out['tests']=$tests;
$out['tests_summary']=implode("\n",array_map(fn($r)=>$r['name'].': завершили '.$r['completed_users'].', в процессе '.$r['in_progress'], $tests));
$clockStart=microtime(true);
$dbClock=query("SELECT extract(epoch FROM clock_timestamp()) epoch, clock_timestamp()::text local_time, current_setting('TimeZone') timezone")[0];
$clockEnd=microtime(true);
$out['clock']=['php_epoch'=>$clockEnd,'php_local'=>date('c'),'php_timezone'=>date_default_timezone_get(),'db_epoch'=>(float)$dbClock['epoch'],'db_local'=>$dbClock['local_time'],'db_timezone'=>$dbClock['timezone'],'db_minus_php_seconds'=>(float)$dbClock['epoch']-($clockStart+$clockEnd)/2,'measurement_seconds'=>$clockEnd-$clockStart];
query('ROLLBACK');echo json_encode($out,JSON_UNESCAPED_UNICODE),"\n";
