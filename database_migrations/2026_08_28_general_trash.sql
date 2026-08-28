-- Recoverable trash registry for catalog and account records.
CREATE TABLE IF NOT EXISTS trash_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    record_type ENUM('product','category','brand','customer','staff') NOT NULL,
    original_id INT UNSIGNED NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    record_snapshot LONGTEXT NOT NULL,
    deleted_by INT NULL,
    deleted_by_name VARCHAR(150) NOT NULL DEFAULT 'System operator',
    deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('trashed','restored') NOT NULL DEFAULT 'trashed',
    restored_by INT NULL,
    restored_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_active_record (record_type, original_id, status),
    KEY idx_trash_status_type (status, record_type, deleted_at),
    KEY idx_trash_deleted_by (deleted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Register the page for permission assignment. Super Admin already has master access.
INSERT INTO system_permissions (permission_key, display_name)
SELECT 'trash.php', 'Trash & Recovery'
WHERE NOT EXISTS (
    SELECT 1 FROM system_permissions WHERE permission_key = 'trash.php'
);
