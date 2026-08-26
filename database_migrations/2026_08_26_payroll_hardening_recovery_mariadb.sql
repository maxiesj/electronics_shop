-- Payroll hardening recovery migration for MariaDB/XAMPP.
-- Use this when an earlier payroll migration partially applied.
-- Safe approach: preserve existing payroll history, repair generated columns,
-- then add only missing indexes/constraints.

SET @db := DATABASE();

-- 1) active_period_guard: add if missing, otherwise convert/refresh as PERSISTENT.
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='payroll_records' AND COLUMN_NAME='active_period_guard'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payroll_records ADD COLUMN active_period_guard TINYINT GENERATED ALWAYS AS (CASE WHEN status = ''voided'' THEN NULL ELSE 1 END) PERSISTENT',
    'ALTER TABLE payroll_records MODIFY COLUMN active_period_guard TINYINT GENERATED ALWAYS AS (CASE WHEN status = ''voided'' THEN NULL ELSE 1 END) PERSISTENT'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) normalized_reference: add if missing, otherwise convert/refresh as PERSISTENT.
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='payroll_records' AND COLUMN_NAME='normalized_reference'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payroll_records ADD COLUMN normalized_reference VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN reference_number IS NULL OR TRIM(reference_number) = '''' THEN NULL ELSE UPPER(TRIM(reference_number)) END) PERSISTENT',
    'ALTER TABLE payroll_records MODIFY COLUMN normalized_reference VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN reference_number IS NULL OR TRIM(reference_number) = '''' THEN NULL ELSE UPPER(TRIM(reference_number)) END) PERSISTENT'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) Prevent duplicate active payroll periods.
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='payroll_records' AND INDEX_NAME='uq_payroll_active_period'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payroll_records ADD UNIQUE KEY uq_payroll_active_period (employee_id, pay_period_start, pay_period_end, active_period_guard)',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) Index normalized references without rejecting legacy duplicates.
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='payroll_records' AND INDEX_NAME='idx_payroll_normalized_reference'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payroll_records ADD KEY idx_payroll_normalized_reference (normalized_reference)',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5) Amount integrity constraint.
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='payroll_records' AND CONSTRAINT_NAME='chk_payroll_amounts'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payroll_records ADD CONSTRAINT chk_payroll_amounts CHECK (basic_salary > 0 AND allowances >= 0 AND deductions >= 0 AND gross_pay = basic_salary + allowances AND net_pay = gross_pay - deductions AND net_pay >= 0)',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6) Paid metadata integrity constraint.
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='payroll_records' AND CONSTRAINT_NAME='chk_payroll_paid_metadata'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payroll_records ADD CONSTRAINT chk_payroll_paid_metadata CHECK (status <> ''paid'' OR (payment_date IS NOT NULL AND payment_method IS NOT NULL AND TRIM(payment_method) <> '''' AND reference_number IS NOT NULL AND TRIM(reference_number) <> '''' AND paid_by IS NOT NULL AND paid_at IS NOT NULL))',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 7) Voided metadata integrity constraint.
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='payroll_records' AND CONSTRAINT_NAME='chk_payroll_void_metadata'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payroll_records ADD CONSTRAINT chk_payroll_void_metadata CHECK (status <> ''voided'' OR (voided_at IS NOT NULL AND void_reason IS NOT NULL AND CHAR_LENGTH(TRIM(void_reason)) >= 5))',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 8) Salary profile positive-amount constraint.
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='staff_salary_profiles' AND CONSTRAINT_NAME='chk_staff_salary_positive'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE staff_salary_profiles ADD CONSTRAINT chk_staff_salary_positive CHECK (monthly_basic_salary > 0 AND monthly_basic_salary <= 999999999.99)',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 9) Final verification.
SELECT COLUMN_NAME, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@db
  AND TABLE_NAME='payroll_records'
  AND COLUMN_NAME IN ('active_period_guard','normalized_reference')
ORDER BY COLUMN_NAME;

SELECT INDEX_NAME, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=@db
  AND TABLE_NAME='payroll_records'
  AND INDEX_NAME IN ('uq_payroll_active_period','idx_payroll_normalized_reference')
GROUP BY INDEX_NAME, NON_UNIQUE
ORDER BY INDEX_NAME;

SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA=@db
  AND (
       (TABLE_NAME='payroll_records' AND CONSTRAINT_NAME IN ('chk_payroll_amounts','chk_payroll_paid_metadata','chk_payroll_void_metadata'))
       OR
       (TABLE_NAME='staff_salary_profiles' AND CONSTRAINT_NAME='chk_staff_salary_positive')
  )
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
