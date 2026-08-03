-- Create stored procedure for activity log cleanup
-- Run this manually after the main migration
-- This file uses DELIMITER which doesn't work in the migration runner

DROP PROCEDURE IF EXISTS cleanup_old_activity_logs;

DELIMITER $$

CREATE PROCEDURE cleanup_old_activity_logs()
BEGIN
    DELETE FROM activity_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    SELECT ROW_COUNT() AS deleted_count;
END$$

DELIMITER ;

-- Test the procedure (optional)
-- CALL cleanup_old_activity_logs();
