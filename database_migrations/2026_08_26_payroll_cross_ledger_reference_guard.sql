-- Cross-ledger payroll payment-reference guard.
-- Prevent a payroll payment from reusing a reference that already exists
-- in customer/sales payments, operating expenses, refunds, or another payroll.
-- Apply after 2026_08_26_payroll_security_hardening.sql.

-- Optional preflight checks: review any existing collisions before enabling the trigger.
SELECT 'payments' AS source_table, UPPER(TRIM(p.transaction_code)) AS normalized_reference, COUNT(*) AS uses
FROM payments p
WHERE p.transaction_code IS NOT NULL
  AND TRIM(p.transaction_code) <> ''
  AND EXISTS (
      SELECT 1
      FROM payroll_records pr
      WHERE pr.reference_number IS NOT NULL
        AND UPPER(TRIM(pr.reference_number)) = UPPER(TRIM(p.transaction_code))
  )
GROUP BY UPPER(TRIM(p.transaction_code));

SELECT 'operating_expenses' AS source_table, UPPER(TRIM(oe.reference_number)) AS normalized_reference, COUNT(*) AS uses
FROM operating_expenses oe
WHERE oe.reference_number IS NOT NULL
  AND TRIM(oe.reference_number) <> ''
  AND EXISTS (
      SELECT 1
      FROM payroll_records pr
      WHERE pr.reference_number IS NOT NULL
        AND UPPER(TRIM(pr.reference_number)) = UPPER(TRIM(oe.reference_number))
  )
GROUP BY UPPER(TRIM(oe.reference_number));

SELECT 'refund_logs' AS source_table, UPPER(TRIM(rl.reversal_reference)) AS normalized_reference, COUNT(*) AS uses
FROM refund_logs rl
WHERE rl.reversal_reference IS NOT NULL
  AND TRIM(rl.reversal_reference) <> ''
  AND EXISTS (
      SELECT 1
      FROM payroll_records pr
      WHERE pr.reference_number IS NOT NULL
        AND UPPER(TRIM(pr.reference_number)) = UPPER(TRIM(rl.reversal_reference))
  )
GROUP BY UPPER(TRIM(rl.reversal_reference));

DROP TRIGGER IF EXISTS trg_payroll_reference_cross_ledger_guard;

DELIMITER $$
CREATE TRIGGER trg_payroll_reference_cross_ledger_guard
BEFORE UPDATE ON payroll_records
FOR EACH ROW
BEGIN
    DECLARE ref_key VARCHAR(100);
    DECLARE collision_count INT DEFAULT 0;

    IF NEW.status = 'paid' AND OLD.status <> 'paid' THEN
        SET ref_key = UPPER(TRIM(COALESCE(NEW.reference_number, '')));

        IF ref_key = '' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Payroll payment reference is required.';
        END IF;

        SELECT COUNT(*) INTO collision_count
        FROM payroll_records pr
        WHERE pr.id <> NEW.id
          AND pr.reference_number IS NOT NULL
          AND UPPER(TRIM(pr.reference_number)) = ref_key;
        IF collision_count > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Payment reference already exists in payroll.';
        END IF;

        SELECT COUNT(*) INTO collision_count
        FROM payments p
        WHERE p.transaction_code IS NOT NULL
          AND TRIM(p.transaction_code) <> ''
          AND UPPER(TRIM(p.transaction_code)) = ref_key;
        IF collision_count > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Payment reference already exists in customer/sales payments.';
        END IF;

        SELECT COUNT(*) INTO collision_count
        FROM operating_expenses oe
        WHERE oe.reference_number IS NOT NULL
          AND TRIM(oe.reference_number) <> ''
          AND UPPER(TRIM(oe.reference_number)) = ref_key;
        IF collision_count > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Payment reference already exists in operating expenses.';
        END IF;

        SELECT COUNT(*) INTO collision_count
        FROM refund_logs rl
        WHERE rl.reversal_reference IS NOT NULL
          AND TRIM(rl.reversal_reference) <> ''
          AND UPPER(TRIM(rl.reversal_reference)) = ref_key;
        IF collision_count > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Payment reference already exists in refund/reversal records.';
        END IF;
    END IF;
END$$
DELIMITER ;
