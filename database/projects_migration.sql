-- Run once on an existing database before opening admin/projects.php.
-- Existing projects stay inactive until an admin adds an image and activates them.
ALTER TABLE projects
    ADD COLUMN quote TEXT NULL AFTER description,
    ADD COLUMN image_path VARCHAR(255) NULL AFTER quote,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path;
