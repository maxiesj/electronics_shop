-- Checkout legacy VAT/net repair for Orders #41-#51
-- MariaDB/XAMPP
-- Reconstructs order_items net/VAT values and order-level net/VAT totals.
-- Does NOT change total_amount, order_status, payment history, layaway history, or inventory.

START TRANSACTION;

UPDATE order_items oi
JOIN orders o ON o.id = oi.order_id
SET
    oi.net_price = ROUND(oi.price / (1 + (o.applied_tax_rate / 100)), 2),
    oi.vat_price = ROUND(oi.price - (oi.price / (1 + (o.applied_tax_rate / 100))), 2)
WHERE oi.order_id BETWEEN 41 AND 51
  AND oi.price > 0;

UPDATE orders o
JOIN (
    SELECT
        oi.order_id,
        ROUND(SUM(oi.net_price * oi.quantity), 2) AS calc_net,
        ROUND(SUM(oi.vat_price * oi.quantity), 2) AS calc_vat,
        ROUND(SUM(oi.price * oi.quantity), 2) AS calc_total
    FROM order_items oi
    WHERE oi.order_id BETWEEN 41 AND 51
    GROUP BY oi.order_id
) x ON x.order_id = o.id
SET
    o.net_amount = x.calc_net,
    o.vat_amount = x.calc_vat
WHERE o.id BETWEEN 41 AND 51
  AND ABS(x.calc_total - o.total_amount) <= 0.01;

SELECT
    o.id,o.order_status,o.applied_tax_rate,o.net_amount,o.vat_amount,o.total_amount,
    ROUND(o.net_amount + o.vat_amount, 2) AS calculated_total,
    ROUND(SUM(oi.price * oi.quantity), 2) AS item_total
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
WHERE o.id BETWEEN 41 AND 51
GROUP BY o.id,o.order_status,o.applied_tax_rate,o.net_amount,o.vat_amount,o.total_amount
ORDER BY o.id;

COMMIT;
