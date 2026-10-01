WITH fixture_tests(test_id,test_duration_time,test_begin_time,test_end_time) AS (
 VALUES (1::bigint,30, timestamp '2026-09-28', timestamp '2026-10-02'),
        (2::bigint,3000, timestamp '2026-09-28', timestamp '2026-09-30 11:30:00')
), fixture_tests_users(testuser_user_id,testuser_test_id,testuser_status,testuser_pregenerated,testuser_creation_time,testuser_last_activity,testuser_close_reason) AS (
 VALUES (1::bigint,1::bigint,1::smallint,false,timestamp '2026-09-30 11:30:00',timestamp '2026-09-30 11:59:00',NULL::text),
 (1,1,4,false,'2026-09-30 11:29:59.999999','2026-09-30 11:45:00','completed'),
 (2,1,4,false,'2026-09-30 11:00:00','2026-09-30 11:10:00','timeout'),
 (3,1,3,NULL,'2026-09-30 11:30:00',NULL,NULL),
 (4,1,3,false,'2026-09-30 11:30:00','2026-09-30 11:59:00','blocked'),
 (5,1,4,true,'2026-09-30 11:30:00','2026-09-30 11:59:00','completed'),
 (6,1,0,false,'2026-09-30 11:30:00','2026-09-30 11:59:00',NULL),
 (7,1,5,false,'2026-09-30 11:30:00','2026-09-30 11:40:00','completed'),
 (8,2,4,false,'2026-09-28 11:00:00','2026-09-30 11:40:00','timeout'),
 (9,1,1,false,'2026-09-30 12:00:00','2026-09-30 12:00:00',''),
 (10,999,4,false,'2026-09-30 11:30:00','2026-09-30 11:59:00','completed'),
 (NULL,1,4,false,'2026-09-30 11:30:00','2026-09-30 11:59:00','completed'),
 (11,1,2,false,'2026-09-30 11:00:00','2026-09-30 11:59:00',NULL)
)
