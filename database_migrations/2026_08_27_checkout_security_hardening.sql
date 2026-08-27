-- Checkout Database Security Hardening
-- MariaDB/XAMPP compatible
-- Apply only after checkout preflight/cleanup has been completed.
-- This migration hardens orders, payments, and order_items without altering historical rows.

ALTER TABLE payments
    ADD COLUMN normalized_transaction_code VARCHAR(100)
        AS (
            CASE
                WHEN transaction_code IS NULL OR TRIM(transaction_code) = '' THEN NULL
                ELSE UPPER(TRIM(transaction_code))
            END
        ) PERSISTENT,
    ADD UNIQUE KEY uq_payments_transaction_code (normalized_transaction_code),
    ADD KEY idx_payments_status_created (payment_status, created_at);

ALTER TABLE orders
    ADD KEY idx_orders_status_created (order_status, created_at);

ALTER TABLE order_items
    ADD KEY idx_order_items_order_product (order_id, product_id);

DROP TRIGGER IF EXISTS trg_orders_insert_guard;
DROP TRIGGER IF EXISTS trg_orders_update_guard;
DROP TRIGGER IF EXISTS trg_payments_insert_guard;
DROP TRIGGER IF EXISTS trg_payments_update_guard;
DROP TRIGGER IF EXISTS trg_order_items_insert_guard;
DROP TRIGGER IF EXISTS trg_order_items_update_guard;

DELIMITER $$

CREATE TRIGGER trg_orders_insert_guard
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    IF NEW.total_amount IS NULL OR NEW.total_amount <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order total must be greater than zero.';
    END IF;
    IF NEW.net_amount IS NULL OR NEW.net_amount < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order net amount cannot be negative.';
    END IF;
    IF NEW.vat_amount IS NULL OR NEW.vat_amount < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order VAT amount cannot be negative.';
    END IF;
    IF NEW.applied_tax_rate IS NULL OR NEW.applied_tax_rate < 0 OR NEW.applied_tax_rate > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order tax rate is invalid.';
    END IF;
    IF ABS(ROUND(NEW.net_amount + NEW.vat_amount, 2) - ROUND(NEW.total_amount, 2)) > 0.01 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order net amount plus VAT must equal total amount.';
    END IF;
END$$

CREATE TRIGGER trg_orders_update_guard
BEFORE UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.total_amount IS NULL OR NEW.total_amount <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order total must be greater than zero.';
    END IF;
    IF NEW.net_amount IS NULL OR NEW.net_amount < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order net amount cannot be negative.';
    END IF;
    IF NEW.vat_amount IS NULL OR NEW.vat_amount < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order VAT amount cannot be negative.';
    END IF;
    IF NEW.applied_tax_rate IS NULL OR NEW.applied_tax_rate < 0 OR NEW.applied_tax_rate > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order tax rate is invalid.';
    END IF;
    IF ABS(ROUND(NEW.net_amount + NEW.vat_amount, 2) - ROUND(NEW.total_amount, 2)) > 0.01 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order net amount plus VAT must equal total amount.';
    END IF;
END$$

CREATE TRIGGER trg_payments_insert_guard
BEFORE INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE ref_norm VARCHAR(100);
    SET ref_norm = UPPER(TRIM(NEW.transaction_code));

    IF NEW.amount IS NULL OR NEW.amount <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount must be greater than zero.';
    END IF;
    IF NEW.payment_method IS NULL OR TRIM(NEW.payment_method) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment method is required.';
    END IF;
    IF ref_norm IS NULL OR ref_norm = '' OR ref_norm = '0' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A valid payment transaction reference is required.';
    END IF;
    IF NEW.payment_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment status is required.';
    END IF;
    IF NEW.order_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.id = NEW.order_id LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Referenced order does not exist.';
    END IF;

    IF EXISTS (SELECT 1 FROM payroll_records pr WHERE pr.reference_number IS NOT NULL AND TRIM(pr.reference_number) <> '' AND UPPER(TRIM(pr.reference_number)) = ref_norm LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment reference already exists in payroll.';
    END IF;
    IF EXISTS (SELECT 1 FROM operating_expenses oe WHERE oe.reference_number IS NOT NULL AND TRIM(oe.reference_number) <> '' AND UPPER(TRIM(oe.reference_number)) = ref_norm LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment reference already exists in operating expenses.';
    END IF;
    IF EXISTS (SELECT 1 FROM refund_logs rl WHERE rl.reversal_reference IS NOT NULL AND TRIM(rl.reversal_reference) <> '' AND UPPER(TRIM(rl.reversal_reference)) = ref_norm LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment reference already exists in refunds.';
    END IF;
END$$

CREATE TRIGGER trg_payments_update_guard
BEFORE UPDATE ON payments
FOR EACH ROW
BEGIN
    DECLARE ref_norm VARCHAR(100);
    SET ref_norm = UPPER(TRIM(NEW.transaction_code));

    IF NEW.amount IS NULL OR NEW.amount < 0 OR (NEW.amount = 0 AND NOT (OLD.amount = 0 AND NEW.amount <=> OLD.amount)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount must be greater than zero.';
    END IF;
    IF NEW.payment_method IS NULL OR TRIM(NEW.payment_method) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment method is required.';
    END IF;
    IF ref_norm IS NULL OR ref_norm = '' OR ref_norm = '0' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A valid payment transaction reference is required.';
    END IF;
    IF NEW.payment_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment status is required.';
    END IF;
    IF NEW.order_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.id = NEW.order_id LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Referenced order does not exist.';
    END IF;

    IF NOT (UPPER(TRIM(OLD.transaction_code)) <=> ref_norm) THEN
        IF EXISTS (SELECT 1 FROM payroll_records pr WHERE pr.reference_number IS NOT NULL AND TRIM(pr.reference_number) <> '' AND UPPER(TRIM(pr.reference_number)) = ref_norm LIMIT 1) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment reference already exists in payroll.';
        END IF;
        IF EXISTS (SELECT 1 FROM operating_expenses oe WHERE oe.reference_number IS NOT NULL AND TRIM(oe.reference_number) <> '' AND UPPER(TRIM(oe.reference_number)) = ref_norm LIMIT 1) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment reference already exists in operating expenses.';
        END IF;
        IF EXISTS (SELECT 1 FROM refund_logs rl WHERE rl.reversal_reference IS NOT NULL AND TRIM(rl.reversal_reference) <> '' AND UPPER(TRIM(rl.reversal_reference)) = ref_norm LIMIT 1) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment reference already exists in refunds.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_order_items_insert_guard
BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
    IF NEW.order_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item must reference an order.';
    END IF;
    IF NEW.quantity IS NULL OR NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item quantity must be greater than zero.';
    END IF;
    IF NEW.net_price IS NULL OR NEW.net_price < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item net price cannot be negative.';
    END IF;
    IF NEW.vat_price IS NULL OR NEW.vat_price < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item VAT cannot be negative.';
    END IF;
    IF NEW.price IS NULL OR NEW.price <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item gross price must be greater than zero.';
    END IF;
    IF NEW.unit_cost IS NOT NULL AND NEW.unit_cost < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item unit cost cannot be negative.';
    END IF;
    IF ABS(ROUND(NEW.net_price + NEW.vat_price, 2) - ROUND(NEW.price, 2)) > 0.01 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item net price plus VAT must equal gross price.';
    END IF;
END$$

CREATE TRIGGER trg_order_items_update_guard
BEFORE UPDATE ON order_items
FOR EACH ROW
BEGIN
    IF NEW.order_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item must reference an order.';
    END IF;
    IF NEW.quantity IS NULL OR NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item quantity must be greater than zero.';
    END IF;
    IF NEW.net_price IS NULL OR NEW.net_price < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item net price cannot be negative.';
    END IF;
    IF NEW.vat_price IS NULL OR NEW.vat_price < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item VAT cannot be negative.';
    END IF;
    IF NEW.price IS NULL OR NEW.price <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item gross price must be greater than zero.';
    END IF;
    IF NEW.unit_cost IS NOT NULL AND NEW.unit_cost < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item unit cost cannot be negative.';
    END IF;
    IF ABS(ROUND(NEW.net_price + NEW.vat_price, 2) - ROUND(NEW.price, 2)) > 0.01 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item net price plus VAT must equal gross price.';
    END IF;
END$$

DELIMITER ;
