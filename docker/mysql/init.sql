-- -----------------------------------------------------------------------------
-- ANCLA - MySQL Initialization Script for Local Development
-- -----------------------------------------------------------------------------
-- This script runs when the MySQL container is first created
-- Creates the testing database for PHPUnit
-- -----------------------------------------------------------------------------

-- Create testing database
CREATE DATABASE IF NOT EXISTS `ancla_testing` 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Grant permissions to ancla user
GRANT ALL PRIVILEGES ON `ancla_testing`.* TO 'ancla'@'%';

-- Apply privileges
FLUSH PRIVILEGES;

-- Log creation
SELECT 'ANCLA databases created successfully' AS status;
