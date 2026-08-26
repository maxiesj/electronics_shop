-- Permanent row-level audit for authorized historical cost estimates.
-- The actual backfill is executed transactionally after snapshotting every changed row here.
CREATE TABLE IF NOT EXISTS order_item_cost_backfill_audit (
    id BIGINT NOT NULL AUTO_INCREMENT,
    batch_key VARCHAR(64) NOT NULL,
    order_item_id INT NOT NULL,
    order_id INT NOT NULL,
    source_product_id INT NULL,
    product_name_snapshot VARCHAR(255) NOT NULL,
    sku_snapshot VARCHAR(100) NULL,
    quantity INT NOT NULL,
    old_unit_cost DECIMAL(10,2) NULL,
    new_unit_cost DECIMAL(10,2) NOT NULL,
    backfilled_by INT NULL,
    backfilled_by_name VARCHAR(100) NOT NULL,
    backfilled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cost_backfill_row (batch_key, order_item_id),
    KEY idx_cost_backfill_order_item (order_item_id),
    KEY idx_cost_backfill_order (order_id),
    KEY idx_cost_backfill_batch (batch_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
