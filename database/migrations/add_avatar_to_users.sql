-- Add avatar column to users table
ALTER TABLE users
ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER address;

-- Create avatars directory if it doesn't exist
-- Note: This is a comment as directory creation should be handled by PHP
-- Directory path: ../assets/images/avatars/ 