-- Targeted checkout legacy repair for Orders #41 and #42
-- MariaDB/XAMPP
-- Based on verified historical evidence gathered before checkout hardening.
-- Order #41: one existing item worth KES 300.00; corrupted order total repaired to KES 300.00.
-- Order #42: preserve historical stored total KES 800.00; rebuild only order-level net/VAT split.

START TRANSACTION;

UPDATE orders
SET
    total_amount = 300.00,
    net_amount = ROUND(300.00 / 1.16, 2),
    vat_amount = ROUND(300.00 - (300.00 / 1.16), 2)
WHERE id = 41;

UPDATE orders
SET
    net_amount = ROUND(800.00 / 1.16, 2),
    vat_amount = ROUND(800.00 - (800.00 / 1.16), 2)
WHERE id = 42;

COMMIT;

SELECT
    id,
    net_amount,
    vat_amount,
    total_amount,
    ROUND(net_amount + vat_amount, 2) AS calculated_total,
    order_status
FROM orders
WHERE id IN (41,42);
