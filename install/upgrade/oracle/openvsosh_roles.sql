INSERT INTO tce_usrgroups (usrgrp_user_id, usrgrp_group_id)
SELECT u.user_id, g.group_id
FROM tce_users u
CROSS JOIN tce_user_groups g
WHERE u.user_level = 10
  AND g.group_name = 'default'
  AND NOT EXISTS (
    SELECT 1
    FROM tce_usrgroups ug
    WHERE ug.usrgrp_user_id = u.user_id
      AND ug.usrgrp_group_id = g.group_id
  );
