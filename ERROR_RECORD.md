# Error Record

This log tracks confirmed application errors, their fixes, and validation results.

## 2026-08-10

### Product detail page syntax error

- **Affected file:** `customer/product_detail.php`
- **Status:** Open
- **Evidence:** PHP syntax validation reports `unexpected end of file` on line 187.
- **Cause:** The file ends after `<?php else: ?>`, leaving the reviews conditional and HTML document unclosed.
- **Planned fix:** Complete the empty-reviews branch, close the PHP conditional and document markup, and restore the add-to-cart form handler.

### Staff dashboard undefined function

- **Affected file:** `staff/staff_dashboard.php`
- **Status:** Needs browser retest
- **Evidence:** Apache log recorded `Call to undefined function is_regular_clockin_time()` on 2026-08-07.
- **Current state:** `ShiftValidator.php` now defines `is_regular_clockin_time()`, which the dashboard loads. The error may be from an earlier version.

### VAT settings statement-close warnings

- **Affected file:** `admin/tax_settings.php`
- **Status:** Open
- **Evidence:** Apache log recorded `mysqli_stmt::close(): Couldn't fetch mysqli_stmt` on 2026-08-07.
- **Cause:** Statements are closed after execution and then closed again at the end of the file.
- **Planned fix:** Remove the duplicate close calls and retest the VAT update flow.
