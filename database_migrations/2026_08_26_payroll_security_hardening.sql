-- Payroll security hardening: database-level integrity protections.
-- Apply after database_migrations/2026_08_13_payroll.sql.
-- MariaDB/XAMPP-compatible version: generated columns are PERSISTENT so they can be indexed.
--
-- IMPORTANT: Back up the database before running this migration.
-- If an ALTER TABLE reports duplicate existing data, stop and review the
-- duplicate rows rather than deleting payroll history automatically.

-- Preflight: these queries should return no rows before the unique guards are added.
SELECT employee_id, pay_period_start, pay_period_end, COUNT(*) AS active_records
FROM payroll_records
WHERE status IN ('draft','paid')
GROUP BY employee_id, pay_period_start, pay_period_end
HAVING COUNT(*) > 1;

SELECT UPPER(TRIM(reference_number)) AS normalized_reference, COUNT(*) AS uses
FROM payroll_records
WHERE reference_number IS NOT NULL
  AND TRIM(reference_number) <> ''
GROUP BY UPPER(TRIM(reference_number))
HAVING COUNT(*) > 1;

ALTER TABLE payroll_records
    ADD COLUMN active_period_guard TINYINT
        GENERATED ALWAYS AS (
            CASE WHEN status = 'voided' THEN NULL ELSE 1 END
        ) PERSISTENT,
    ADD COLUMN normalized_reference VARCHAR(100)
        GENERATED ALWAYS AS (
            CASE
                WHEN reference_number IS NULL OR TRIM(reference_number) = '' THEN NULL
                ELSE UPPER(TRIM(reference_number))
            END
        ) PERSISTENT,
    ADD UNIQUE KEY uq_payroll_active_period
        (employee_id, pay_period_start, pay_period_end, active_period_guard),
    ADD UNIQUE KEY uq_payroll_payment_reference
        (normalized_reference),
    ADD CONSTRAINT chk_payroll_amounts CHECK (
        basic_salary > 0
        AND allowances >= 0
        AND deductions >= 0
        AND gross_pay = basic_salary + allowances
        AND net_pay = gross_pay - deductions
        AND net_pay >= 0
    ),
    ADD CONSTRAINT chk_payroll_paid_metadata CHECK (
        status <> 'paid'
        OR (
            payment_date IS NOT NULL
            AND payment_method IS NOT NULL
            AND TRIM(payment_method) <> ''
            AND reference_number IS NOT NULL
            AND TRIM(reference_number) <> ''
            AND paid_by IS NOT NULL
            AND paid_at IS NOT NULL
        )
    ),
    ADD CONSTRAINT chk_payroll_void_metadata CHECK (
        status <> 'voided'
        OR (
            voided_at IS NOT NULL
            AND void_reason IS NOT NULL
            AND CHAR_LENGTH(TRIM(void_reason)) >= 5
        )
    );

-- Salary profiles must also reject invalid amounts at the database boundary.
ALTER TABLE staff_salary_profiles
    ADD CONSTRAINT chk_staff_salary_positive CHECK (
        monthly_basic_salary > 0 AND monthly_basic_salary <= 999999999.99
    );
