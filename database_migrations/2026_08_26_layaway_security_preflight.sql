-- Layaway / Installment Security Preflight
-- MariaDB/XAMPP compatible
-- READ-ONLY checks. No data is modified.
-- Run against the electronics_shop database before applying hardening.

-- 1. Invalid monetary values.
SELECT
    id,
    user_id,
    order_id,
    total_amount,
    deposit_paid,
    balance_remaining,
    status
FROM layaway_plans
WHERE total_amount IS NULL
   OR deposit_paid IS NULL
   OR balance_remaining IS NULL
   OR total_amount <= 0
   OR deposit_paid < 0
   OR balance_remaining < 0
   OR deposit_paid > total_amount
   OR balance_remaining > total_amount;

-- 2. Plans whose paid + remaining does not equal the total.
-- A one-cent tolerance is allowed for decimal rounding.
SELECT
    id,
    order_id,
    total_amount,
    deposit_paid,
    balance_remaining,
    ROUND(deposit_paid + balance_remaining, 2) AS calculated_total
FROM layaway_plans
WHERE ABS(ROUND(deposit_paid + balance_remaining, 2) - ROUND(total_amount, 2)) > 0.01;

-- 3. Orphaned user references.
SELECT
    lp.id,
    lp.user_id,
    lp.order_id
FROM layaway_plans lp
LEFT JOIN users u ON u.id = lp.user_id
WHERE u.id IS NULL;

-- 4. Orphaned order references.
SELECT
    lp.id,
    lp.user_id,
    lp.order_id
FROM layaway_plans lp
LEFT JOIN orders o ON o.id = lp.order_id
WHERE o.id IS NULL;

-- 5. Layaway customer does not match the order customer.
SELECT
    lp.id,
    lp.user_id AS layaway_user_id,
    lp.order_id,
    o.user_id AS order_user_id
FROM layaway_plans lp
JOIN orders o ON o.id = lp.order_id
WHERE lp.user_id <> o.user_id;

-- 6. More than one layaway plan attached to the same order.
SELECT
    order_id,
    COUNT(*) AS plan_count
FROM layaway_plans
GROUP BY order_id
HAVING COUNT(*) > 1;

-- 7. Invalid or unexpected status values.
SELECT
    id,
    order_id,
    status,
    total_amount,
    deposit_paid,
    balance_remaining
FROM layaway_plans
WHERE status IS NULL
   OR TRIM(status) = ''
   OR LOWER(TRIM(status)) NOT IN ('active', 'fully paid', 'cancelled', 'defaulted');

-- 8. Fully paid plans that still have a material balance.
SELECT
    id,
    order_id,
    status,
    total_amount,
    deposit_paid,
    balance_remaining
FROM layaway_plans
WHERE LOWER(TRIM(status)) = 'fully paid'
  AND balance_remaining > 0.01;

-- 9. Active plans with no balance remaining.
SELECT
    id,
    order_id,
    status,
    total_amount,
    deposit_paid,
    balance_remaining
FROM layaway_plans
WHERE LOWER(TRIM(status)) = 'active'
  AND balance_remaining <= 0.01;

-- 10. Impossible timestamp chronology.
SELECT
    id,
    order_id,
    created_at,
    updated_at
FROM layaway_plans
WHERE created_at IS NOT NULL
  AND updated_at IS NOT NULL
  AND updated_at < created_at;
