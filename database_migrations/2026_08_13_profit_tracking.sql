-- Profit tracking foundation.
-- Existing records intentionally remain NULL because their historical buying cost is unknown.
ALTER TABLE products
    ADD COLUMN cost_price DECIMAL(10,2) NULL AFTER price;

ALTER TABLE order_items
    ADD COLUMN unit_cost DECIMAL(10,2) NULL AFTER price;
