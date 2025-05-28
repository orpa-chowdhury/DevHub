-- Add metadata column to files table for storing file type and size
ALTER TABLE files ADD COLUMN metadata TEXT AFTER content;

-- Update the database schema to support file operations
-- This ensures all file operations are properly tracked

-- Make sure commit_files table has proper columns
ALTER TABLE commit_files MODIFY COLUMN content_before LONGTEXT;
ALTER TABLE commit_files MODIFY COLUMN content_after LONGTEXT;

-- Add indexes for better performance
CREATE INDEX idx_files_repo_branch ON files(repository_id, branch);
CREATE INDEX idx_files_path ON files(path);
CREATE INDEX idx_commits_repo_branch ON commits(repository_id, branch);
