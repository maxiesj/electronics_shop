-- Customer Wallet Security Preflight
-- Read-only checks for MariaDB/XAMPP.
-- Run this before applying wallet database hardening.
-- Every result set should be empty unless otherwise noted.

-- 1. Wallets with invalid or negative balances.
SELECT
    id,
    user_id,
    available_balance,
    created_at,
    updated_at
FROM customer_wallets
WHERE available_balance IS NULL
   OR available_balance < 0;

-- 2. Duplicate wallets for the same user.
-- The current UNIQUE(user_id) index should normally make this empty.
SELECT
    user_id,
    COUNT(*) AS wallet_count
FROM customer_wallets
GROUP BY user_id
HAVING COUNT(*) > 1;

-- 3. Wallet rows whose user no longer exists.
SELECT
    cw.id,
    cw.user_id,
    cw.available_balance
FROM customer_wallets cw
LEFT JOIN users u ON u.id = cw.user_id
WHERE u.id IS NULL;

-- 4. Invalid user identifiers.
SELECT
    id,
    user_id,
    available_balance
FROM customer_wallets
WHERE user_id IS NULL
   OR user_id <= 0;

-- 5. Very large wallet balances for manual review.
-- This is informational only; rows here are not automatically errors.
SELECT
    id,
    user_id,
    available_balance,
    updated_at
FROM customer_wallets
WHERE available_balance > 99999999.99
ORDER BY available_balance DESC;

-- 6. Wallet timestamps with unexpected chronology.
SELECT
    id,
    user_id,
    created_at,
    updated_at
FROM customer_wallets
WHERE created_at IS NOT NULL
  AND updated_at IS NOT NULL
  AND updated_at < created_at;
