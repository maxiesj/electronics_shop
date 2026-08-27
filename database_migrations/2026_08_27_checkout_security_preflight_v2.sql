-- Checkout Security Preflight v2
-- MariaDB/XAMPP compatible
-- READ-ONLY checks for orders, payments, and order_items.
-- Accepts both current gross-price checkout rows and older tax-exclusive item-price rows.

-- 1. Orders with invalid monetary totals.
SELECT id,user_id,net_amount,vat_amount,applied_tax_rate,total_amount,order_status,created_at
FROM orders
WHERE net_amount IS NULL OR vat_amount IS NULL OR applied_tax_rate IS NULL OR total_amount IS NULL
   OR net_amount < 0 OR vat_amount < 0 OR total_amount <= 0
   OR applied_tax_rate < 0 OR applied_tax_rate > 100;

-- 2. Orders whose net + VAT does not equal total (1 cent tolerance).
SELECT id,net_amount,vat_amount,total_amount,
       ROUND(net_amount + vat_amount,2) AS calculated_total
FROM orders
WHERE ABS(ROUND(net_amount + vat_amount,2) - ROUND(total_amount,2)) > 0.01;

-- 3. Orders whose user no longer exists.
SELECT o.id,o.user_id,o.total_amount,o.order_status
FROM orders o
LEFT JOIN users u ON u.id=o.user_id
WHERE o.user_id IS NOT NULL AND u.id IS NULL;

-- 4. Payments with invalid core values.
SELECT id,order_id,payment_method,transaction_code,amount,payment_status,created_at
FROM payments
WHERE order_id IS NULL
   OR payment_method IS NULL OR TRIM(payment_method)=''
   OR transaction_code IS NULL OR TRIM(transaction_code)=''
   OR amount IS NULL OR amount <= 0
   OR payment_status IS NULL;

-- 5. Duplicate normalized payment transaction codes.
SELECT UPPER(TRIM(transaction_code)) AS normalized_transaction_code,
       COUNT(*) AS duplicate_count
FROM payments
WHERE transaction_code IS NOT NULL AND TRIM(transaction_code)<>''
GROUP BY UPPER(TRIM(transaction_code))
HAVING COUNT(*) > 1;

-- 6. Payments linked to missing orders.
SELECT p.id,p.order_id,p.transaction_code,p.amount,p.payment_status
FROM payments p
LEFT JOIN orders o ON o.id=p.order_id
WHERE o.id IS NULL;

-- 7. Completed/refunded payment totals exceeding order total.
SELECT o.id AS order_id,o.total_amount,
       ROUND(SUM(CASE WHEN LOWER(p.payment_status) IN ('completed','refunded')
                      THEN p.amount ELSE 0 END),2) AS completed_and_refunded_total
FROM orders o
JOIN payments p ON p.order_id=o.id
GROUP BY o.id,o.total_amount
HAVING completed_and_refunded_total > ROUND(o.total_amount,2) + 0.01;

-- 8. Order items with invalid quantity or monetary values.
SELECT id,order_id,product_id,quantity,net_price,vat_price,price,unit_cost
FROM order_items
WHERE order_id IS NULL
   OR quantity IS NULL OR quantity <= 0
   OR net_price IS NULL OR vat_price IS NULL OR price IS NULL
   OR net_price < 0 OR vat_price < 0 OR price <= 0
   OR (unit_cost IS NOT NULL AND unit_cost < 0);

-- 9. Order-item unit net + VAT does not equal gross price.
SELECT id,order_id,product_id,net_price,vat_price,price,
       ROUND(net_price + vat_price,2) AS calculated_price
FROM order_items
WHERE ABS(ROUND(net_price + vat_price,2) - ROUND(price,2)) > 0.01;

-- 10. Order items linked to missing orders.
SELECT oi.id,oi.order_id,oi.product_id
FROM order_items oi
LEFT JOIN orders o ON o.id=oi.order_id
WHERE o.id IS NULL;

-- 11. Duplicate product lines within the same order.
SELECT order_id,product_id,COUNT(*) AS duplicate_lines
FROM order_items
WHERE product_id IS NOT NULL
GROUP BY order_id,product_id
HAVING COUNT(*) > 1;

-- 12. Order/item total mismatches.
-- Accept either:
--   A) current model: item price is gross/inclusive and item_total = order total
--   B) legacy model: item price was tax-exclusive and item_total * (1 + tax rate) = order total
SELECT
    o.id AS order_id,
    o.order_status,
    o.applied_tax_rate,
    o.total_amount,
    ROUND(SUM(oi.price * oi.quantity),2) AS item_total,
    ROUND(SUM(oi.price * oi.quantity) * (1 + o.applied_tax_rate / 100),2) AS legacy_tax_added_total
FROM orders o
JOIN order_items oi ON oi.order_id=o.id
GROUP BY o.id,o.order_status,o.applied_tax_rate,o.total_amount
HAVING ABS(ROUND(SUM(oi.price * oi.quantity),2) - ROUND(o.total_amount,2)) > 0.01
   AND ABS(
       ROUND(SUM(oi.price * oi.quantity) * (1 + o.applied_tax_rate / 100),2)
       - ROUND(o.total_amount,2)
   ) > 0.01;

-- 13. Payments whose transaction reference collides with payroll.
SELECT p.id,p.transaction_code,pr.id AS payroll_id,pr.reference_number
FROM payments p
JOIN payroll_records pr
  ON pr.reference_number IS NOT NULL
 AND TRIM(pr.reference_number)<>''
 AND UPPER(TRIM(pr.reference_number))=UPPER(TRIM(p.transaction_code))
WHERE p.transaction_code IS NOT NULL AND TRIM(p.transaction_code)<>'';

-- 14. Payments whose transaction reference collides with operating expenses.
SELECT p.id,p.transaction_code,oe.id AS expense_id,oe.reference_number
FROM payments p
JOIN operating_expenses oe
  ON oe.reference_number IS NOT NULL
 AND TRIM(oe.reference_number)<>''
 AND UPPER(TRIM(oe.reference_number))=UPPER(TRIM(p.transaction_code))
WHERE p.transaction_code IS NOT NULL AND TRIM(p.transaction_code)<>'';

-- 15. Payments whose transaction reference collides with refunds.
SELECT p.id,p.transaction_code,rl.id AS refund_id,rl.reversal_reference
FROM payments p
JOIN refund_logs rl
  ON rl.reversal_reference IS NOT NULL
 AND TRIM(rl.reversal_reference)<>''
 AND UPPER(TRIM(rl.reversal_reference))=UPPER(TRIM(p.transaction_code))
WHERE p.transaction_code IS NOT NULL AND TRIM(p.transaction_code)<>'';
