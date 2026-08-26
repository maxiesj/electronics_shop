# Checkout improvements pending

## Review status

The checkout flow was reviewed on 2026-08-11. No checkout changes were applied yet.

## Required fixes

1. **Do not complete M-Pesa orders before confirmation.**
   `customer/checkout_form_process.php` currently treats the `mpesa` method as paid and sets an order to `delivered` without a verified Safaricom STK callback. Until callback handling exists, the checkout processor should reject this method and the UI should send customers to `customer/deposit.php` to top up their wallet.

2. **Add checkout CSRF protection.**
   Generate a session token in `customer/checkout.php`, include it in the checkout request, and validate it in `customer/checkout_form_process.php` with `hash_equals` before starting a transaction.

3. **Fix transaction-code binding.**
   The payment insert currently uses `isdds`. It must be `issds`, because `transaction_code` is a string.

4. **Prevent overselling during concurrent checkout.**
   Load cart/product rows with `FOR UPDATE`, then reduce stock with:

   ```sql
   UPDATE products
   SET stock_quantity = stock_quantity - ?
   WHERE id = ? AND stock_quantity >= ?
   ```

   Check that exactly one row was updated; otherwise roll back.

5. **Improve checkout usability.**
   Add payment-method guidance and disable the checkout button while the request is processing to prevent accidental duplicate orders.

## Existing flow observations

- Wallet checkout deducts the full balance and is the only immediately settled option.
- Lipa Pole Pole deducts a 50% wallet deposit and creates an installment plan.
- `customer/mpesa_stk_push.php` contains placeholder credentials and a simulator; it is not safe to use as real payment confirmation.

## Files to update

- `customer/checkout.php`
- `customer/checkout_form_process.php`
- optionally `customer/mpesa_stk_push.php` when real Daraja callback processing is added
