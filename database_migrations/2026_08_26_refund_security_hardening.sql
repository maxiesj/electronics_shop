-- Refund security hardening for MariaDB/XAMPP
-- Apply ONLY after 2026_08_26_refund_security_preflight.sql returns empty result sets.
-- This migration hardens refund_logs at database level.

ALTER TABLE refund_logs
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    ADD COLUMN normalized_reversal_reference VARCHAR(100)
        AS (
            CASE
                WHEN reversal_reference IS NULL OR TRIM(reversal_reference) = '' THEN NULL
                ELSE UPPER(TRIM(reversal_reference))
            END
        ) PERSISTENT,
    ADD UNIQUE KEY uq_refund_payment_once (payment_id),
    ADD UNIQUE KEY uq_refund_reversal_reference (normalized_reversal_reference),
    ADD KEY idx_refund_processed_at (processed_at),
    ADD CONSTRAINT fk_refund_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

DROP TRIGGER IF EXISTS trg_refund_logs_insert_guard;
DROP TRIGGER IF EXISTS trg_refund_logs_update_guard;

DELIMITER $$

CREATE TRIGGER trg_refund_logs_insert_guard
BEFORE INSERT ON refund_logs
FOR EACH ROW
BEGIN
    DECLARE payment_order_id INT;
    DECLARE payment_amount DECIMAL(10,2);
    DECLARE ref_norm VARCHAR(100);

    SET ref_norm = UPPER(TRIM(NEW.reversal_reference));

    IF NEW.amount_processed IS NULL OR NEW.amount_processed <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund amount must be greater than zero.';
    END IF;

    IF ref_norm IS NULL OR ref_norm = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund reversal reference is required.';
    END IF;

    SELECT p.order_id, p.amount
      INTO payment_order_id, payment_amount
    FROM payments p
    WHERE p.id = NEW.payment_id
    LIMIT 1;

    IF payment_order_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Referenced payment does not exist.';
    END IF;

    IF NEW.order_id <> payment_order_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund order does not match the source payment.';
    END IF;

    IF ABS(NEW.amount_processed - payment_amount) > 0.009 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund amount must match the source payment amount.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM payments p
        WHERE p.transaction_code IS NOT NULL
          AND TRIM(p.transaction_code) <> ''
          AND UPPER(TRIM(p.transaction_code)) = ref_norm
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund reference already exists in customer/sales payments.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM payroll_records pr
        WHERE pr.reference_number IS NOT NULL
          AND TRIM(pr.reference_number) <> ''
          AND UPPER(TRIM(pr.reference_number)) = ref_norm
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund reference already exists in payroll.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM operating_expenses oe
        WHERE oe.reference_number IS NOT NULL
          AND TRIM(oe.reference_number) <> ''
          AND UPPER(TRIM(oe.reference_number)) = ref_norm
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund reference already exists in operating expenses.';
    END IF;
END$$

CREATE TRIGGER trg_refund_logs_update_guard
BEFORE UPDATE ON refund_logs
FOR EACH ROW
BEGIN
    DECLARE payment_order_id INT;
    DECLARE payment_amount DECIMAL(10,2);
    DECLARE ref_norm VARCHAR(100);

    SET ref_norm = UPPER(TRIM(NEW.reversal_reference));

    IF NEW.amount_processed IS NULL OR NEW.amount_processed <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund amount must be greater than zero.';
    END IF;

    IF ref_norm IS NULL OR ref_norm = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund reversal reference is required.';
    END IF;

    SELECT p.order_id, p.amount
      INTO payment_order_id, payment_amount
    FROM payments p
    WHERE p.id = NEW.payment_id
    LIMIT 1;

    IF payment_order_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Referenced payment does not exist.';
    END IF;

    IF NEW.order_id <> payment_order_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund order does not match the source payment.';
    END IF;

    IF ABS(NEW.amount_processed - payment_amount) > 0.009 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Refund amount must match the source payment amount.';
    END IF;

    IF NOT (UPPER(TRIM(OLD.reversal_reference)) <=> ref_norm) THEN
        IF EXISTS (
            SELECT 1
            FROM payments p
            WHERE p.transaction_code IS NOT NULL
              AND TRIM(p.transaction_code) <> ''
              AND UPPER(TRIM(p.transaction_code)) = ref_norm
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Refund reference already exists in customer/sales payments.';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM payroll_records pr
            WHERE pr.reference_number IS NOT NULL
              AND TRIM(pr.reference_number) <> ''
              AND UPPER(TRIM(pr.reference_number)) = ref_norm
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Refund reference already exists in payroll.';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM operating_expenses oe
            WHERE oe.reference_number IS NOT NULL
              AND TRIM(oe.reference_number) <> ''
              AND UPPER(TRIM(oe.reference_number)) = ref_norm
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Refund reference already exists in operating expenses.';
        END IF;
    END IF;
END$$

DELIMITER ;
