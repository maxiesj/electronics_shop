-- Operating expense trigger compatibility hardening for MariaDB/XAMPP
-- Use this when CHECK constraints are not retained by the installed MariaDB version.
-- This replaces the two operating_expenses triggers with versions that enforce
-- amount, reference, void metadata, and cross-ledger reference uniqueness.

DROP TRIGGER IF EXISTS trg_operating_expense_reference_insert;
DROP TRIGGER IF EXISTS trg_operating_expense_reference_update;

DELIMITER $$

CREATE TRIGGER trg_operating_expense_reference_insert
BEFORE INSERT ON operating_expenses
FOR EACH ROW
BEGIN
    DECLARE ref_norm VARCHAR(100);
    SET ref_norm = UPPER(TRIM(NEW.reference_number));

    IF NEW.amount IS NULL OR NEW.amount <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Operating expense amount must be greater than zero.';
    END IF;

    IF ref_norm IS NULL OR ref_norm = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Operating expense reference is required.';
    END IF;

    IF NEW.status = 'voided' AND (
        NEW.voided_by IS NULL
        OR NEW.voided_by_name IS NULL
        OR TRIM(NEW.voided_by_name) = ''
        OR NEW.voided_at IS NULL
        OR NEW.void_reason IS NULL
        OR CHAR_LENGTH(TRIM(NEW.void_reason)) < 5
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Voided expenses require complete audit metadata.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM payments p
        WHERE p.transaction_code IS NOT NULL
          AND TRIM(p.transaction_code) <> ''
          AND UPPER(TRIM(p.transaction_code)) = ref_norm
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Reference already exists in customer/sales payments.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM payroll_records pr
        WHERE pr.reference_number IS NOT NULL
          AND TRIM(pr.reference_number) <> ''
          AND UPPER(TRIM(pr.reference_number)) = ref_norm
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Reference already exists in payroll.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM refund_logs rl
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

    IF NEW.amount IS NULL OR NEW.amount <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Operating expense amount must be greater than zero.';
    END IF;

    IF ref_norm IS NULL OR ref_norm = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Operating expense reference is required.';
    END IF;

    IF NEW.status = 'voided' AND (
        NEW.voided_by IS NULL
        OR NEW.voided_by_name IS NULL
        OR TRIM(NEW.voided_by_name) = ''
        OR NEW.voided_at IS NULL
        OR NEW.void_reason IS NULL
        OR CHAR_LENGTH(TRIM(NEW.void_reason)) < 5
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Voided expenses require complete audit metadata.';
    END IF;

    IF NOT (UPPER(TRIM(OLD.reference_number)) <=> ref_norm) THEN
        IF EXISTS (
            SELECT 1 FROM payments p
            WHERE p.transaction_code IS NOT NULL
              AND TRIM(p.transaction_code) <> ''
              AND UPPER(TRIM(p.transaction_code)) = ref_norm
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Reference already exists in customer/sales payments.';
        END IF;

        IF EXISTS (
            SELECT 1 FROM payroll_records pr
            WHERE pr.reference_number IS NOT NULL
              AND TRIM(pr.reference_number) <> ''
              AND UPPER(TRIM(pr.reference_number)) = ref_norm
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Reference already exists in payroll.';
        END IF;

        IF EXISTS (
            SELECT 1 FROM refund_logs rl
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
