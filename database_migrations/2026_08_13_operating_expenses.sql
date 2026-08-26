-- Accountable operating expenses used to calculate net profit.
CREATE TABLE IF NOT EXISTS operating_expenses (
    id INT NOT NULL AUTO_INCREMENT,
    expense_date DATE NOT NULL,
    category VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(500) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    reference_number VARCHAR(100) NULL,
    recorded_by INT NULL,
    recorded_by_name VARCHAR(100) NOT NULL,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','voided') NOT NULL DEFAULT 'active',
    voided_by INT NULL,
    voided_by_name VARCHAR(100) NULL,
    voided_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_expense_period (expense_date, status),
    KEY idx_expense_category (category),
    KEY idx_expense_recorder (recorded_by),
    KEY idx_expense_voider (voided_by),
    CONSTRAINT fk_expense_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_expense_voider FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_permissions (permission_key, display_name)
VALUES ('operating_expenses.php', 'Operating Expenses');
