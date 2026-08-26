-- Operating expense database hardening for MariaDB/XAMPP
-- IMPORTANT: Run 2026_08_26_expense_security_preflight.sql first.
-- Apply this file only after all preflight result sets are empty.

ALTER TABLE operating_expenses
    ADD COLUMN normalized_reference VARCHAR(100)
        AS (
            CASE
                WHEN reference_number IS NULL OR TRIM(reference_number) = '' THEN NULL
                ELSE UPPER(TRIM(reference_number))
            END
        ) PERSISTENT,
    ADD UNIQUE KEY uq_operating_expense_reference (normalized_reference),
    ADD KEY idx_operating_expense_date_status (expense_date, status),
    ADD CONSTRAINT chk_operating_expense_amount CHECK (amount > 0),
    ADD CONSTRAINT chk_operating_expense_reference CHECK (
        reference_number IS NOT NULL AND TRIM(reference_number) <> ''
    ),
    ADD CONSTRAINT chk_operating_expense_void_metadata CHECK (
        status <> 'voided'
        OR (
            voided_by IS NOT NULL
            AND voided_by_name IS NOT NULL
            AND TRIM(voided_by_name) <> ''
            AND voided_at IS NOT NULL
            AND void_reason IS NOT NULL
            AND CHAR_LENGTH(TRIM(void_reason)) >= 5
        )
    );

DROP TRIGGER IF EXISTS trg_operating_expense_reference_insert;
DROP TRIGGER IF EXISTS trg_operating_expense_reference_update;

DELIMITER $$

CREATE TRIGGER trg_operating_expense_reference_insert
BEFORE INSERT ON operating_expenses
FOR EACH ROW
BEGIN
    DECLARE ref_norm VARCHAR(100);
    SET ref_norm = UPPER(TRIM(NEW.reference_number));

    IF ref_norm IS NULL OR ref_norm = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Operating expense reference is required.';
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
            SET MESSAGE_TEXT = 'Reference already exists in customer/sales payments.';
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
            SET MESSAGE_TEXT = 'Reference already exists in payroll.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM refund_logs rl
        WHERE rl.reversal_reference IS NOT NULL
          AND TRIM(rl.reversal_reference) <> ''
          AND UPPER(TRIM(rl.reversal_reference)) = ref_norm
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Reference already exists in a refund/reversal.';
    END IF;
END$$

CREATE TRIGGER trg_operating_expense_reference_update
BEFORE UPDATE ON operating_expenses
FOR EACH ROW
BEGIN
    DECLARE ref_norm VARCHAR(100);
    SET ref_norm = UPPER(TRIM(NEW.reference_number));

    IF ref_norm IS NULL OR ref_norm = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Operating expense reference is required.';
    END IF;

    IF UPPER(TRIM(OLD.reference_number)) <> ref_norm THEN
        IF EXISTS (
            SELECT 1
            FROM payments p
            WHERE p.transaction_code IS NOT NULL
              AND TRIM(p.transaction_code) <> ''
              AND UPPER(TRIM(p.transaction_code)) = ref_norm
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Reference already exists in customer/sales payments.';
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
                SET MESSAGE_TEXT = 'Reference already exists in payroll.';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM refund_logs rl
            WHERE rl.reversal_reference IS NOT NULL
              AND TRIM(rl.reversal_reference) <> ''
              AND UPPER(TRIM(rl.reversal_reference)) = ref_norm
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Reference already exists in a refund/reversal.';
        END IF;
    END IF;
END$$

DELIMITER ;
