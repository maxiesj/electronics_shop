-- Salary profiles and accountable payroll history.
CREATE TABLE IF NOT EXISTS staff_salary_profiles (
    employee_id INT NOT NULL,
    monthly_basic_salary DECIMAL(12,2) NOT NULL,
    updated_by INT NULL,
    updated_by_name VARCHAR(100) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id),
    CONSTRAINT fk_salary_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_salary_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_records (
    id INT NOT NULL AUTO_INCREMENT,
    employee_id INT NULL,
    employee_name VARCHAR(100) NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL,
    allowances DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gross_pay DECIMAL(12,2) NOT NULL,
    net_pay DECIMAL(12,2) NOT NULL,
    status ENUM('draft','paid','voided') NOT NULL DEFAULT 'draft',
    payment_date DATE NULL,
    payment_method VARCHAR(50) NULL,
    reference_number VARCHAR(100) NULL,
    notes VARCHAR(500) NULL,
    processed_by INT NULL,
    processed_by_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_by INT NULL,
    paid_by_name VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    voided_by INT NULL,
    voided_by_name VARCHAR(100) NULL,
    voided_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_payroll_period (pay_period_start, pay_period_end, status),
    KEY idx_payroll_payment (payment_date, status),
    KEY idx_payroll_employee (employee_id),
    KEY idx_payroll_employee_period (employee_id, pay_period_start, pay_period_end, status),
    CONSTRAINT fk_payroll_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_payroll_processor FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_payroll_payer FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_payroll_voider FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_permissions (permission_key, display_name)
VALUES ('payroll.php', 'Payroll');
