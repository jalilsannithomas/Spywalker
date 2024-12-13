-- Start transaction and disable foreign key checks
START TRANSACTION;
SET FOREIGN_KEY_CHECKS = 0;
SET @tables = NULL;

-- Get all table names in a single string
SELECT GROUP_CONCAT(table_schema, '.', table_name) 
INTO @tables
FROM information_schema.tables 
WHERE table_schema = 'spywalker';

-- Prepare and execute dynamic SQL to drop all tables
SET @tables = CONCAT('DROP TABLE IF EXISTS FORCE ', REPLACE(@tables, ',', ' DROP TABLE IF EXISTS FORCE '));
SET SESSION SQL_MODE = 'ALLOW_INVALID_DATES,TRADITIONAL,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION';
PREPARE stmt FROM @tables;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Re-enable foreign key checks and commit
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- Verify all tables are dropped
SELECT COUNT(*) as remaining_tables 
FROM information_schema.tables 
WHERE table_schema = 'spywalker';
 