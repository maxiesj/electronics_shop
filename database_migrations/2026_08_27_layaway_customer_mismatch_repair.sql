-- Layaway customer/order mismatch repair
-- MariaDB/XAMPP
-- Repairs historical layaway_plans.user_id values so they match the linked order.

START TRANSACTION;

DROP TRIGGER IF EXISTS trg_layaway_plan_update_guard;

UPDATE layaway_plans lp
JOIN orders o ON o.id = lp.order_id
SET lp.user_id = o.user_id
WHERE lp.user_id <> o.user_id;

SELECT
    lp.id AS plan_id,
    lp.user_id AS layaway_user_id,
    lp.order_id,
    o.user_id AS order_user_id
FROM layaway_plans lp
JOIN orders o ON o.id = lp.order_id
WHERE lp.user_id <> o.user_id;

DELIMITER $$

CREATE TRIGGER trg_layaway_plan_update_guard
BEFORE UPDATE ON layaway_plans
FOR EACH ROW
BEGIN
    DECLARE order_user_id INT DEFAULT NULL;

    IF NEW.order_id <> OLD.order_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'An existing layaway plan cannot be moved to another order.';
    END IF;

    IF NEW.user_id <> OLD.user_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'An existing layaway plan cannot be reassigned to another customer.';
    END IF;

    IF NEW.total_amount IS NULL OR NEW.total_amount <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Layaway total amount must be greater than zero.';
    END IF;

    IF NEW.deposit_paid IS NULL OR NEW.deposit_paid < 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Layaway deposit cannot be negative.';
    END IF;

    IF NEW.balance_remaining IS NULL OR NEW.balance_remaining < 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Layaway balance cannot be negative.';
    END IF;

    IF NEW.deposit_paid > NEW.total_amount
       OR NEW.balance_remaining > NEW.total_amount THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Layaway deposit or balance cannot exceed the total amount.';
    END IF;

    IF ABS(
        ROUND(NEW.deposit_paid + NEW.balance_remaining, 2)
        - ROUND(NEW.total_amount, 2)
    ) > 0.01 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Layaway paid amount plus remaining balance must equal the total.';
    END IF;

    IF NEW.status IS NULL
       OR TRIM(NEW.status) = ''
       OR LOWER(TRIM(NEW.status)) NOT IN ('active', 'fully paid', 'cancelled', 'defaulted') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid layaway status.';
    END IF;

    IF LOWER(TRIM(NEW.status)) = 'fully paid'
       AND NEW.balance_remaining > 0.01 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A fully paid layaway plan cannot have an outstanding balance.';
    END IF;

    IF LOWER(TRIM(NEW.status)) = 'active'
       AND NEW.balance_remaining <= 0.01 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'An active layaway plan must have an outstanding balance.';
    END IF;

    SELECT o.user_id
      INTO order_user_id
    FROM orders o
    WHERE o.id = NEW.order_id
    LIMIT 1;

    IF order_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Referenced order does not exist.';
    END IF;

    IF NEW.user_id <> order_user_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Layaway customer must match the order customer.';
    END IF;
END$$

DELIMITER ;

COMMIT;
