-- Operating expense security preflight for MariaDB/XAMPP
-- Read-only. Run this first and review every result set before applying the hardening migration.

-- 1) Invalid expense amounts or missing payment metadata
SELECT id, expense_date, category, amount, payment_method, reference_number, status
FROM operating_expenses
WHERE amount <= 0
   OR payment_method IS NULL
   OR TRIM(payment_method) = ''
   OR reference_number IS NULL
   OR TRIM(reference_number) = '';

-- 2) Duplicate references inside operating_expenses
SELECT UPPER(TRIM(reference_number)) AS normalized_reference, COUNT(*) AS uses
FROM operating_expenses
WHERE reference_number IS NOT NULL
  AND TRIM(reference_number) <> ''
GROUP BY UPPER(TRIM(reference_number))
HAVING COUNT(*) > 1;

-- 3) Expense references colliding with customer/sales payments
SELECT oe.id AS expense_id,
       UPPER(TRIM(oe.reference_number)) AS normalized_reference,
       p.id AS payment_id
FROM operating_expenses oe
JOIN payments p
  ON p.transaction_code IS NOT NULL
 AND TRIM(p.transaction_code) <> ''
 AND UPPER(TRIM(p.transaction_code)) = UPPER(TRIM(oe.reference_number))
WHERE oe.reference_number IS NOT NULL
  AND TRIM(oe.reference_number) <> '';

-- 4) Expense references colliding with payroll
SELECT oe.id AS expense_id,
       UPPER(TRIM(oe.reference_number)) AS normalized_reference,
       pr.id AS payroll_id
FROM operating_expenses oe
JOIN payroll_records pr
  ON pr.reference_number IS NOT NULL
 AND TRIM(pr.reference_number) <> ''
 AND UPPER(TRIM(pr.reference_number)) = UPPER(TRIM(oe.reference_number))
WHERE oe.reference_number IS NOT NULL
  AND TRIM(oe.reference_number) <> '';

-- 5) Expense references colliding with refunds/reversals
SELECT oe.id AS expense_id,
       UPPER(TRIM(oe.reference_number)) AS normalized_reference,
       rl.id AS refund_id
FROM operating_expenses oe
JOIN refund_logs rl
  ON rl.reversal_reference IS NOT NULL
 AND TRIM(rl.reversal_reference) <> ''
 AND UPPER(TRIM(rl.reversal_reference)) = UPPER(TRIM(oe.reference_number))
WHERE oe.reference_number IS NOT NULL
  AND TRIM(oe.reference_number) <> '';

-- 6) Voided records missing audit metadata
SELECT id, status, voided_by, voided_by_name, voided_at, void_reason
FROM operating_expenses
WHERE status = 'voided'
  AND (
      voided_by IS NULL
      OR voided_by_name IS NULL
      OR TRIM(voided_by_name) = ''
      OR voided_at IS NULL
      OR void_reason IS NULL
      OR CHAR_LENGTH(TRIM(void_reason)) < 5
  );
