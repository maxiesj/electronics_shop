-- System Trash / Soft Delete foundation
-- MariaDB / MySQL migration for recoverable catalog records.
-- Financial/audit ledgers are intentionally excluded from destructive deletion.

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER image,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER is_deleted,
    ADD COLUMN IF NOT EXISTS deleted_by INT NULL AFTER deleted_at;

ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS deleted_by INT NULL;

ALTER TABLE brands
    ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS deleted_by INT NULL;

CREATE INDEX IF NOT EXISTS idx_products_deleted ON products (is_deleted, deleted_at);
CREATE INDEX IF NOT EXISTS idx_categories_deleted ON categories (is_deleted, deleted_at);
CREATE INDEX IF NOT EXISTS idx_brands_deleted ON brands (is_deleted, deleted_at);

CREATE TABLE IF NOT EXISTS trash_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    entity_label VARCHAR(255) NOT NULL,
    deleted_by INT NULL,
    deleted_by_name VARCHAR(150) NULL,
    deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    restored_by INT NULL,
    restored_by_name VARCHAR(150) NULL,
    restored_at DATETIME NULL,
    status ENUM('trashed','restored','purged') NOT NULL DEFAULT 'trashed',
    metadata LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY idx_trash_status_deleted (status, deleted_at),
    KEY idx_trash_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification
SELECT 'products' AS entity, COUNT(*) AS trashed FROM products WHERE is_deleted = 1
UNION ALL
SELECT 'categories', COUNT(*) FROM categories WHERE is_deleted = 1
UNION ALL
SELECT 'brands', COUNT(*) FROM brands WHERE is_deleted = 1;
