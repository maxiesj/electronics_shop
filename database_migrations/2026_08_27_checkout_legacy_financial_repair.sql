-- Checkout legacy financial metadata repair
-- MariaDB/XAMPP
-- Repairs only legacy Orders #35, #40 and #52 using their existing order_items gross prices.
-- Does NOT invent payments, does NOT create layaway plans, and does NOT change order_status.

START TRANSACTION;

-- 1. Reconstruct each order item's net/VAT split from its stored gross price
--    and the linked order's stored applied tax rate.
UPDATE order_items oi
JOIN orders o ON o.id = oi.order_id
SET
    oi.net_price = ROUND(
        oi.price / (1 + (o.applied_tax_rate / 100)),
        2
    ),
    oi.vat_price = ROUND(
        oi.price - (oi.price / (1 + (o.applied_tax_rate / 100))),
        2
    )
WHERE oi.order_id IN (35, 40, 52)
  AND oi.price > 0;

-- 2. Reconstruct order-level totals from the item rows.
UPDATE orders o
JOIN (
    SELECT
        oi.order_id,
        ROUND(SUM(oi.net_price * oi.quantity), 2) AS calc_net,
        ROUND(SUM(oi.vat_price * oi.quantity), 2) AS calc_vat,
        ROUND(SUM(oi.price * oi.quantity), 2) AS calc_total
    FROM order_items oi
    WHERE oi.order_id IN (35, 40, 52)
    GROUP BY oi.order_id
) x ON x.order_id = o.id
SET
    o.net_amount = x.calc_net,
    o.vat_amount = x.calc_vat,
    o.total_amount = x.calc_total
WHERE o.id IN (35, 40, 52);

-- 3. Verification snapshot.
SELECT
    o.id,
    o.order_status,
    o.applied_tax_rate,
    o.net_amount,
    o.vat_amount,
    o.total_amount,
    ROUND(SUM(oi.net_price * oi.quantity), 2) AS item_net,
    ROUND(SUM(oi.vat_price * oi.quantity), 2) AS item_vat,
    ROUND(SUM(oi.price * oi.quantity), 2) AS item_total,
    COALESCE((
        SELECT ROUND(SUM(p.amount), 2)
        FROM payments p
        WHERE p.order_id = o.id
    ), 0.00) AS payment_total
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
WHERE o.id IN (35, 40, 52)
GROUP BY
    o.id,
    o.order_status,
    o.applied_tax_rate,
    o.net_amount,
    o.vat_amount,
    o.total_amount
ORDER BY o.id;

COMMIT;
