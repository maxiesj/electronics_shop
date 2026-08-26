-- Customer Wallet Security Hardening
-- MariaDB/XAMPP compatible
-- Apply only after the wallet security preflight returns clean results.

-- 1. Modernize table character set and add a useful timestamp index.
ALTER TABLE customer_wallets
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    ADD KEY idx_customer_wallet_updated_at (updated_at);

-- 2. Database-level guards.
-- MariaDB CHECK behavior varies by version/configuration, so triggers are used
-- for reliable enforcement.

DROP TRIGGER IF EXISTS trg_customer_wallet_insert_guard;
DROP TRIGGER IF EXISTS trg_customer_wallet_update_guard;

DELIMITER $$

CREATE TRIGGER trg_customer_wallet_insert_guard
BEFORE INSERT ON customer_wallets
FOR EACH ROW
BEGIN
    IF NEW.user_id IS NULL OR NEW.user_id <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Wallet user_id must reference a valid user.';
    END IF;

    IF NEW.available_balance IS NULL
       OR NEW.available_balance < 0
       OR NEW.available_balance > 99999999.99 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Wallet balance must be between KES 0.00 and KES 99,999,999.99.';
    END IF;

    IF NEW.is_active_toggle IS NULL
       OR NEW.is_active_toggle NOT IN (0, 1) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Wallet active status must be 0 or 1.';
    END IF;
END$$

CREATE TRIGGER trg_customer_wallet_update_guard
BEFORE UPDATE ON customer_wallets
FOR EACH ROW
BEGIN
    -- Prevent moving a wallet and its balance from one customer to another.
    IF NEW.user_id <> OLD.user_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A wallet cannot be reassigned to another user.';
    END IF;

    IF NEW.available_balance IS NULL
       OR NEW.available_balance < 0
       OR NEW.available_balance > 99999999.99 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Wallet balance must be between KES 0.00 and KES 99,999,999.99.';
    END IF;

    IF NEW.is_active_toggle IS NULL
       OR NEW.is_active_toggle NOT IN (0, 1) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Wallet active status must be 0 or 1.';
    END IF;
END$$

DELIMITER ;
