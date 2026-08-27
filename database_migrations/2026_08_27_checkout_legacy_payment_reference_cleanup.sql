-- Checkout legacy payment-reference cleanup
-- MariaDB/XAMPP
-- Preserves every historical payment row.
-- Replaces only invalid placeholder transaction_code = '0' with a unique traceable legacy reference.

START TRANSACTION;

UPDATE payments
SET transaction_code = CONCAT('LEGACY_PAY_', LPAD(id, 6, '0'))
WHERE TRIM(transaction_code) = '0';

SELECT id,order_id,payment_method,transaction_code,amount,payment_status,created_at
FROM payments
WHERE transaction_code IS NULL
   OR TRIM(transaction_code) = ''
   OR TRIM(transaction_code) = '0'
ORDER BY id;

SELECT UPPER(TRIM(transaction_code)) AS normalized_reference,
       COUNT(*) AS uses,
       GROUP_CONCAT(id ORDER BY id) AS payment_ids
FROM payments
WHERE transaction_code IS NOT NULL
  AND TRIM(transaction_code) <> ''
GROUP BY UPPER(TRIM(transaction_code))
HAVING COUNT(*) > 1;

COMMIT;
