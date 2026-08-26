# ADONAK Electronics – Change Record

Last updated: 11 August 2026
Application root: D:\xammp\htdocs\electronics_shop

## Purpose

This file records the customer, staff, and administration improvements completed during the current development session. It also documents the financial safeguards and operational assumptions that should be preserved in future changes.

## 1. Customer checkout and payment safety

- Added CSRF protection to checkout submissions.
- Prevented duplicate checkout clicks and repeated order creation.
- Added stock locking and guarded stock deductions during checkout.
- Corrected the checkout payment parameter binding.
- Disabled unfinished direct M-Pesa order settlement and directed customers to top up their wallet first.
- Added clearer checkout guidance for wallet and Lipa Pole Pole payments.
- Lipa Pole Pole checkout now deducts an initial 50% deposit and creates an active installment plan for the remaining 50%.

Files: customer/checkout.php and customer/checkout_form_process.php.

## 2. Order settlement guard

- Added order_payment_guard.php as the shared source of truth for order settlement.
- It calculates expected total, completed payments, payment balance, latest installment balance, and fully-paid state.
- Admin, staff, and customer pages use verified payment state instead of relying only on the visible order status.
- Staff, admin, and super admin cannot mark an order Delivered while any payment or installment balance remains.
- The Delivered option is disabled when an outstanding balance exists.
- A fully paid installment order moves to Processing and must still be deliberately fulfilled before delivery.

Files: order_payment_guard.php, admin/manage_orders.php, staff/manage_orders.php, and customer/pay_installment.php.

## 3. Historical order reconciliation

Orders #36, #37, #38, #58, #59, #61, #62, and #70 had previously been marked Delivered without full payment. They were reconciled back to Pending with audit entries. A follow-up audit found no remaining Delivered orders with unpaid balances.

Known exception: plan #18 / order #63 remains a historical inconsistency because its financial history is incomplete. It was intentionally not given an invented payment amount.

## 4. Order cancellation and wallet refunds

- Cancellation refunds completed payments only.
- Refunded payment rows are marked Refunded.
- Linked installment plans are cancelled.
- Refund, payment-status, order-status, and audit changes run in one database transaction.
- Delivered orders remain non-refundable from Manage Sales.
- The payments status definition supports refunded.

File: admin/manage_orders.php.

## 5. Wallet top-up correction

- Wallet rows are created automatically when missing.
- Successful deposits update the stored wallet balance.
- The saved balance is verified before success is reported.
- Added CSRF protection, phone validation, and token rotation.

File: customer/deposit.php.

## 6. Lipa Pole Pole calculations and flexible payments

- Removed hard-coded 30%/70% wording.
- Installment views show actual amount paid, outstanding balance, and calculated percentages.
- Customers may pay any amount from KES 0.01 up to the current balance after the initial 50% deposit.
- Overpayments are rejected and active plans are locked during balance updates.
- Full settlement changes the plan to Fully Paid and the order to Processing.
- Order #100 is plan #27: total KES 470,500; deposit KES 235,250; balance KES 235,250; status Active.
- Customer order cards show the Lipa Pole Pole badge for both Pending and Processing orders.
- The installment button remains available while the plan is Active and has a balance.

Files: customer/my_orders.php, customer/pay_installment.php, admin/layaway_defaulters.php, and admin/manage_layaways.php.

## 7. Lipa Pole Pole reminder sequence

The 30-day plan uses Nairobi time:

- Days 1–20: no scheduled reminder.
- Days 21–25: one reminder daily at 1:00 PM.
- Days 26–30: reminders daily at 9:00 AM and 6:00 PM.
- Reminders stop when the balance is cleared, the plan is inactive, or the period ends.
- A unique plan/date/time-slot rule prevents duplicates.
- Reminder records are stored in polepole_reminders.
- The order screen explains the final-ten-day schedule.

Windows tasks:

- ADONAK PolePole Reminder 9AM
- ADONAK PolePole Reminder 1PM
- ADONAK PolePole Reminder 6PM

Operational note: local tasks run in Interactive mode. The computer must be running, the user logged in, and MySQL available. The current channel is an in-app queue. Actual SMS requires an SMS provider such as Africa's Talking.

File: customer/cron_polepole_cleanup.php.

## 8. Customer statements

- Every fully paid order receives a Statement button.
- Active Lipa Pole Pole orders retain their running statement.
- Ordinary unpaid orders do not receive a completed statement button.
- Eligibility uses the shared settlement helper rather than order status alone.

Files: customer/my_orders.php, customer/statement.php, and order_payment_guard.php.

## 9. Admin navigation and loading

- Reorganized the sidebar into Overview, Commerce, Inventory, Customers & Payments, Finance & Reports, Team, and System.
- Added expandable groups; clicking an open group collapses it.
- Added equivalent behavior for small devices.
- Added smoother sidebar and workspace loaders.
- Added mobile full-screen navigation.
- Added staff quick actions and return/back navigation.

Files: admin/dashboard.php, css/panel-polish.css, and relevant staff pages.

## 10. Admin dashboard cards

- Redesigned KPI cards with icons, color accents, entrance animation, hover motion, and responsive layouts.
- Covered orders, layaways, low stock, staff, revenue, order value, wallet approvals, and installment flags.
- Corrected conflicting responsive styles that had broken the dashboard.

Files: admin/dashboard_overview.php and css/panel-polish.css.

## 11. Manage Sales layout and payment visibility

- Renamed Gross Cost to Expected Total Cost.
- Added Paid / Outstanding details, percentage, and progress indicator.
- Corrected the desktop table so all seven columns remain visible.
- Capped oversized controls and allowed refund labels to wrap.
- Preserved the working mobile layout.

Files: admin/manage_orders.php and css/panel-polish.css.

## 12. Customer storefront redesign

- Corrected desktop alignment and horizontal overflow.
- Expanded content into a consistent centered workspace.
- Rebuilt the newest-product banner with image, actions, full price, and calculated 50% deposit.
- Added benefits for secure payments, Lipa Pole Pole, genuine stock, and tracking.
- Added live category filters using the real categories table.
- Search, sorting, and categories work together through AJAX.
- Product cards display the calculated 50% installment starting payment.
- Added hover animation and responsive filter scrolling.
- Added an About Us link and responsive customer/about.php page.

Files: customer/home.php, customer/about.php, and css/panel-polish.css.

## 13. Shared interface polish

- Added shared focus styles and tap behavior.
- Improved cards, navigation, responsive spacing, and touch targets.
- Added stylesheet cache versions where immediate refresh was required.

File: css/panel-polish.css.

## Validation completed

- PHP syntax checks passed for edited customer and admin pages.
- The storefront rendered without PHP warnings after the final update.
- Reminder slots support 09:00, 13:00, and 18:00.
- Duplicate reminder audit returned zero duplicates.
- Order #100 was verified against its live plan.
- Delivered-with-unpaid audit returned zero orders after reconciliation.

## Maintenance rules

1. Never decide an order is fully paid from order_status alone. Use getOrderSettlementState().
2. Never mark an order Delivered when outstanding_balance exceeds KES 0.009.
3. Keep refunds, payment changes, plan changes, wallet changes, and order changes in a database transaction.
4. Do not invent payments when reconciling incomplete historical data.
5. Keep reminder generation idempotent through the unique plan/date/time-slot rule.
6. Do not describe the reminder queue as SMS until a real SMS provider is connected.
7. Preserve the working mobile layout when changing desktop table widths.
8. After CSS changes, update the stylesheet version or perform a hard refresh.
## 14. Customer shipping details editor

- Customers can save and edit a dedicated delivery phone number and full shipping address from their profile.
- The account/login phone remains separate from the delivery contact number.
- Added CSRF protection, Kenyan phone validation, address-length validation, prepared updates, success/error feedback, and a post-save redirect.
- The saved shipping phone and address are displayed together above the responsive editor.

File: customer/profile.php.
## 15. Customer password change

- Added a Change Password section to the customer profile.
- Requires the current password and verifies it using the existing password hash.
- The new password must be at least eight characters and include uppercase, lowercase, and numeric characters.
- Requires matching confirmation and rejects reuse of the current password.
- Uses a separate CSRF token, bcrypt hashing, prepared updates, password-reset token invalidation, session ID regeneration, and post-save feedback.

File: customer/profile.php.
## 16. Admin and staff account security

- Added a shared Account Security page for admin, super admin, and operational staff roles.
- Requires the current password, matching confirmation, and a strong replacement password.
- Uses separate CSRF protection, bcrypt, prepared updates, reset-token invalidation, and session ID regeneration.
- Added Account Security links to the admin sidebar and shared staff navigation.
- Replaced direct GET logout with a confirmation page and CSRF-protected POST logout.
- Logout clears session data, removes the session cookie with HttpOnly and SameSite settings, disables caching, and sends Clear-Site-Data.
- Logout auditing is best-effort and cannot prevent session termination when the audit enum lacks Staff Logout.

Files: account_security.php, logout.php, admin/dashboard.php, and staff/navbar.php.
## 17. Inactivity timeout audit and correction

- Confirmed the intended timeout is 900 seconds (15 minutes).
- Added a shared browser inactivity controller for authenticated customer, staff, and admin screens.
- A modal warning appears after 14 inactive minutes with a live 60-second countdown and Stay Signed In button.
- Active pages send throttled keepalive requests so genuine mouse, keyboard, scroll, or touch activity updates the server timer.
- At 15 inactive minutes the session cookie and server session are destroyed before redirecting to the login page.
- Added server-side timeout enforcement to the shared staff navigation.
- Corrected admin AJAX recognition of AUTH_ERROR and SESSION_TIMEOUT.
- Corrected the admin expiration redirect, which previously pointed to a non-existent admin/index.php location.
- Guest storefront browsing does not run the authenticated inactivity controller.

Files: js/session-idle.js, session_keepalive.php, session_expire.php, session_auth.php, admin/dashboard.php, staff/navbar.php, and selected authenticated customer pages.

## 18. Login timeout message

- Replaced the technical session-expiration wording with a concise customer-friendly sign-in message.
- Confirmed the password field does not render a server-supplied value and retains autocomplete="current-password" for safe password-manager support.

File: login.php.

## 19. Password update spinner

- Added matching inline loading spinners to customer, admin, and staff password-update buttons.
- Buttons are disabled during submission to prevent duplicate password updates and display Updating Password feedback.

Files: js/password-submit.js, customer/profile.php, and account_security.php.
## Guest account onboarding redirects

- Guests opening Cart, Checkout, Orders, Profile, Wallet Deposit, Installment Payment, or Payment Statements are redirected to the root account-registration page.
- The registration page provides a Return to Login Portal option for customers who already have an account.
- Guest catalogue browsing remains public; purchasing and account features remain protected by authentication checks.

## Guest storefront navigation

- Made the public storefront header session-aware.
- Guests now see Create Account and Log In; they do not see Log Out or customer account links.
- Signed-in customers retain Cart, Orders, Profile, and secure Log Out navigation.

## Strict guest navigation access

- Updated the About Us header to use the same session-aware navigation as the public Shop page.
- Guests strictly see Shop, About Us, Create Account, and Log In only.
- Cart, Orders, Profile, Wallet, Checkout, Installments, and Statements remain protected from unauthenticated direct access.

## Guest catalogue hero control

- Disabled the hero Browse Catalogue control for unauthenticated guests.
- The guest control is non-interactive, visibly muted, keyboard-safe, and includes a sign-in/account-creation hint.
- Signed-in customers retain the active catalogue-scroll button.

## Guest order-tracking control

- Disabled the About Us Track My Orders action for guests without an authenticated session.
- Guests see a non-interactive Log in to Track Orders control with clear disabled styling.
- Authenticated customers retain the working order-tracking link, while the Orders page continues enforcing its server-side session guard.

## Authentication UI redesign

- Introduced a shared responsive authentication design across Login, Registration, Forgot Password, and Reset Password.
- Added a desktop two-panel layout, compact mobile presentation, ADONAK branding, account benefits, guest-shop access, clearer wording, accessible labels, visible focus states, password visibility controls, and smooth submission feedback.
- Registration now preserves non-sensitive submitted values after validation errors and includes password strength guidance plus confirm-password validation in both the browser and PHP backend.
- Password recovery screens now use consistent messaging, loading states, navigation, and new-password guidance.
- Existing CSRF, secure password hashing, lockout, session timeout, and recovery-token behavior were retained.

## Sliding authentication transition

- Added a blue-panel slide transition when switching between Login and Registration.
- Login presents the brand panel on the left; Registration presents it on the right, reinforcing the two-state interaction.
- The panel updates its message during the transition, prevents duplicate clicks, adapts to a compact vertical motion on small screens, and respects reduced-motion accessibility preferences.

## Single-view authentication transition correction

- Replaced the permanent side-by-side authentication layout with a single visible white form card.
- Login, Registration, Forgot Password, and Reset Password now display their form only; the blue brand layer stays off-screen until navigation begins.
- Create Account, Log In, and Continue as Guest trigger a full-card blue transition that completely covers the white form before opening the selected destination.
- Added visible Continue as Guest actions to both Login and Registration while preserving reduced-motion handling.

## Authentication card width refinement

- Reduced the desktop authentication card width from 560px to 520px for a more compact presentation.
- Mobile continues using the full available width, and the blue transition curtain remains aligned to the complete card.

## Email-first password recovery

- Forgot password now requires a valid email address in the Login email field before opening account recovery.
- Empty or malformed addresses keep the user on Login, focus the email field, and display native accessible validation guidance.
- A valid address is safely URL-encoded, carried into the recovery screen, and prefilled there before the normal protected reset request is submitted.
- The response continues avoiding account-existence disclosure, reducing email-enumeration risk.

## Authentication card 25% desktop width

- Set the authentication card to 25% of the desktop viewport width.
- Added a 420px usability minimum and 520px maximum so form controls remain readable across laptop and wide-screen displays.
- Tablet and mobile breakpoints continue expanding the card to the full available width.

## Create Account spacing refinement

- Reduced the desktop Create Account panel padding to 28px vertically and 42px horizontally.
- Reduced its minimum height from 680px to 650px for a tighter presentation while retaining comfortable form spacing.
- Login and mobile-specific spacing remain unchanged.

## Animated registration placeholders

- Added subtle horizontal motion and opacity animation to the Mobile Number and Repeat Password placeholders while those fields are empty.
- Placeholder animation pauses on focus and automatically disables when reduced-motion accessibility is enabled.

## Permission-based review moderation

- Restricted review moderation to Super Admin and operators explicitly assigned the `manage_reviews.php` view.
- Admin and Staff without the Reviews permission no longer receive direct-page or backend approval access, matching the existing hidden navigation behavior.
- Super Admin retains automatic review moderation access.
- Added a reusable explicit-permission clearance helper so permission-sensitive modules do not inherit the general Admin bypass.

## Staff revenue payment-card styling

- Redesigned the Staff dashboard Total Realized Revenue metric as a premium payment-card-inspired summary.
- Added an ADONAK-branded blue gradient, metallic chip detail, contactless-style motif, layered highlights, and embossed revenue typography.
- No Visa wording, logo, or payment-network branding is used.
- The remaining operational metric cards retain their original design.

## Admin monthly revenue payment-card styling

- Applied the Staff revenue card visual language to the Admin Monthly Revenue KPI.
- Added the ADONAK blue gradient, metallic chip, contactless-style motif, layered highlights, bright revenue typography, and ADONAK Finance label without payment-network branding.
- Preserved the existing Monthly Revenue calculation, KPI entrance animation, responsive grid, and all other Admin KPI designs.

## Admin and Staff revenue color synchronization

- Locked the Admin Monthly Revenue card to the exact Staff revenue gradient: `#0f172a` to `#1e3a8a` to `#2563eb` at the same stops.
- Matched the Staff card shadow color and forced the gradient above generic KPI background rules.
- Refreshed the Admin stylesheet version so browsers immediately load the synchronized colors instead of a cached copy.

## Burnt-orange Admin low-stock card

- Applied a burnt-orange gradient background to the Admin Low Stock Alerts KPI using `#9a3412`, `#c2410c`, and `#ea580c`.
- Added warm cream labels, a high-contrast white value and icon, matching alert dot, and an orange-toned hover shadow.
- Preserved the live low-stock count, healthy/alert messaging, pulse state, and responsive KPI behavior.
- Refreshed the Admin stylesheet version to prevent stale cached colors.

## Double-click Admin low-stock navigation

- Made the Admin Low Stock Alerts KPI open the Low Stock Monitor on double-click.
- A single click selects and highlights the card without navigating.
- Enter and Space open the destination for keyboard accessibility.
- Used delegated dashboard events so the interaction survives AJAX view reloads.

## Double-click Wallet Approvals navigation

- Applied the Admin KPI navigation behavior to Wallet Approvals.
- A single click selects the card; double-click opens Customer Wallets; Enter or Space provides keyboard access.
- The interaction uses the existing smooth dashboard loader and shared selected-card feedback.

## Admin sidebar horizontal scrollbar removal

- Removed the unnecessary horizontal scrollbar from the desktop Admin sidebar by hiding horizontal overflow explicitly.
- Preserved vertical scrolling so every navigation group and Logout remain reachable on shorter screens.
- Left table and chart horizontal scrolling unchanged because those areas may contain legitimately wide content.

## Admin sidebar collapse-arrow visibility

- Moved the desktop sidebar collapse arrow from 14px outside the panel to 8px inside its right edge after confirming the clipping in the supplied screenshot.
- Applied the inside position to both expanded and condensed sidebar states.
- Preserved hidden horizontal overflow and vertical menu scrolling, preventing the bottom scrollbar from returning.

## Admin KPI semantic color palette

- Applied soft semantic backgrounds to the remaining Admin KPIs: amber Pending Orders, violet Active Layaways, teal Staff Accounts, emerald Average Order Value, gold Wallet Approvals, and crimson Installment Flags.
- Kept Monthly Revenue and Low Stock Alerts as the strongest full-gradient visual anchors.
- Preserved alert pulses, dynamic status text, values, responsive behavior, and Wallet double-click navigation.
- Refreshed the Admin stylesheet version to deliver the palette immediately.

## Staff dashboard semantic card palette

- Mirrored the Admin KPI palette on the Staff dashboard metrics.
- Pending Invoices uses soft amber, Active Layaway Contracts uses soft violet, and Critical Out-of-Stock uses the burnt-orange alert gradient.
- Preserved the blue ADONAK Finance revenue card, all live metric calculations, responsive grid behavior, and added consistent hover elevation.

## Staff shift handover refresh

- Completed shifts now remain in attendance history without being reused as the staff member's current shift.
- After clock-out succeeds, the dashboard refreshes and immediately returns to the Clock In state for the next shift.
- Successful clock-in and clock-out actions use a redirect with a one-time confirmation message, preventing accidental duplicate form submissions on browser refresh.
- Moved the staff navigation output inside the page body so shift redirects can run before any page content is sent.
## Backup integrity center redesign and hardening

- Rebalanced the backup screen with a compact hero, backup summary cards, searchable archive table, latest-backup badge, responsive layout, and clearer wording.
- Added visible loading feedback for backup creation, authenticated SQL downloads, safer Delete wording, and automatic dashboard refresh after successful actions.
- Added CSRF protection to backup creation and deletion, strict archive filename validation, audit logging for creation/deletion, protected storage access, and non-sensitive error responses.
- Changed database export generation to stream records to disk, reducing the risk of memory exhaustion on larger databases.
- Fixed the dashboard bug that replaced the workspace with raw SUCCESS or ERROR response text after backup actions.
- Restore remains deliberately restricted to maintenance mode because replacing the live transactional database requires an offline recovery workflow and a pre-restore safety snapshot.
## Workspace Activity Tracker redesign

- Replaced the basic 30-row activity table with a responsive audit dashboard.
- Added daily activity, active-operator, financial-event, and security-event summary cards.
- Added keyword, operator, category, and date-range filters with searchable pagination.
- Added colour-coded activity badges, expandable descriptions, mobile activity cards, refresh controls, and CSV export.
- Preserved logs for deleted users by using the stored staff name when an account no longer exists.
- Fixed the search workflow that could inject a second complete dashboard inside the current workspace.
- Changed the restricted activity category field to a flexible text field so Financial, Staff Management, Permissions, System, and future categories are no longer silently saved as blank values.
- Existing historical blank categories are labelled Unclassified because their original category cannot be reconstructed safely.
## Workspace Tracker filter correction

- Fixed relative tracker filter requests resolving to the website root instead of the admin tracker endpoint.
- Added visible feedback when a filter request fails so unchanged results no longer look like a successful filter.
- The correction also improves other relative GET forms loaded inside the Admin workspace.
## Workspace Tracker summary-card backgrounds

- Added soft semantic gradient backgrounds to the tracker summary cards.
- Activities Today uses blue, Active Operators uses violet, Financial Events uses green, and Security Events uses amber.
- Added matching accent borders, subtle elevation, and hover feedback while preserving text contrast and responsive behaviour.
## Feedback Moderator UI and workflow correction

- Added Pending, Live, Hidden, and Rejected review states while preserving existing approved reviews as Live.
- New and edited customer reviews now return to Pending instead of publishing immediately.
- Restricted customer reviews to products contained in that customer's delivered orders and added secure form tokens plus 10–1,500 character validation.
- Product pages now show the Write Feedback action only to eligible verified purchasers.
- Replaced unsafe URL-based moderation and permanent deletion with protected Approve, Hide, and Reject actions.
- Rejected feedback remains stored for audit history rather than being permanently erased.
- Unified authorized Admin and Staff moderation controls and records every moderation action in the Workspace Tracker.
- Added Pending/Live/Hidden/Rejected summary cards, search, status/rating filters, pagination, responsive review cards, action feedback, and corrected icons.
- Preserved the existing explicit Review permission requirement for Admin and Staff moderators.
## Backup summary-card colour palette


## Client project documentation
- Created a client-facing seven-page handover guide covering the shopfront, inventory, orders, payment records, Lipa Pole Pole, invoices, staff access, attendance, financial analytics, operating expenses, payroll, accounting rules, security and routine operating procedures.
- The guide clearly explains that final tax receipts require fully paid, non-cancelled and non-refunded orders; invoices include the handling staff member and Lipa Pole Pole payment method for accountability.
- Generated and verified the final Word document through Microsoft Word: deliverables/ADONAK_Electronics_Client_Project_Documentation.docx.
- Latest Successful Backup now uses a soft emerald health background.
- Stored Archives now uses a soft blue data-storage background.
- Storage Used now uses a soft amber capacity background.
- Added matching accent borders, subtle shadows, and hover elevation while preserving responsive behaviour.
## Backup Download action colour

- Styled Download as a blue primary action with white text and a matching hover state.
- Kept Delete as a separate red outlined destructive action so the two controls are easy to distinguish.
## Tax Receipt Lookup redesign and calculation correction

- Replaced recalculated VAT with the exact net amount, VAT amount, and applied tax rate stored on each order.
- Restricted final receipt generation to fully paid, non-cancelled orders using completed payments minus recorded refunds.
- Removed the fabricated fallback KRA PIN; missing tax details now display as Not provided.
- Added customer contact details, order/payment status, payment method/reference, unique receipt number, full timestamps, and Paid & Verified status.
- Added a clean empty state, outstanding-balance warning, clear search action, responsive receipt layout, and Print / Save PDF control.
- Receipt lookups are now recorded in the Workspace Tracker as financial activity.
## Tax receipt final visual polish

- Reduced search-panel height and aligned Print / Save PDF with the receipt card width.
- Normalized customer-name capitalization and improved common Kenyan phone-number formatting.
- Payment reference values that are empty or zero now display as Not provided.
- Added horizontal overflow containment and extra bottom clearance so receipt totals remain accessible.
- Preserved clean print output that excludes administrative controls and navigation.
- Business contact details were not invented because no verified business-address or support settings currently exist.
## Monthly Tax Report correction and redesign

- Renamed the unverified eTIMS audit document to Monthly Tax and Sales Statement.
- Removed the placeholder Trader PIN and shows Not configured until verified business details are supplied.
- Financial totals now include only fully paid, non-cancelled, non-refunded orders using each order's stored Net, VAT, Gross, and tax rate.
- Added Paid, Processing, Delivered, Cancelled, and Refunded counts plus clear Included/Excluded treatment per order.
- Added four balanced semantic summary cards, non-wrapping currency values, responsive layout, CSV export, and improved print page breaks.
- Added the remaining nine-digit Kenyan phone format correction on customer tax receipts.
## Low-stock supplier reorder email and tracking workflow

- Replaced the copy-only console with real supplier email delivery using the existing PHPMailer SMTP transport.
- Centralized SMTP configuration for supplier and password-reset emails, with environment-variable overrides for deployment.
- Added editable reorder quantity, supplier contact, lead time, estimated unit cost/total, current stock, and expected stock.
- Added persistent Sent, Confirmed, Received, and Cancelled reorder states with duplicate active-request prevention.
- Mark Received now atomically closes the request and adds the requested quantity to inventory exactly once.
- Added secure request tokens, email validation, copy action, progress loader compatibility, failure feedback, and Workspace Tracker audit entries.
- Replaced unfinished INDEX wording and corrected corrupted separator characters.
## Database-driven reorder estimates
- Low-stock reorder unit prices now come directly from the product price stored in the database.
- Estimated totals are calculated as database unit price multiplied by the requested quantity and update automatically when quantity changes.
- The server ignores client-supplied cost values, preventing manual or altered pricing from being recorded or emailed.

## Add Product audit and cleanup
- Added CSRF protection, stricter server-side validation, safe customer-facing errors and database error logging.
- Product images now require verified JPG, PNG or WebP content, use random filenames and have a 5 MB limit.
- Added database-level unique SKU protection to prevent duplicate catalog records during concurrent submissions.
- Added inventory creation audit logs, responsive form styling, improved focus states and a back-to-inventory action.
- Failed database writes clean up newly uploaded images instead of leaving unused files behind.

## Add Product UI refinement
- Narrowed and centered the catalog-entry workspace for easier scanning.
- Grouped fields into Product details, Pricing and stock, and Media and description sections.
- Simplified technical labels and guidance, aligned the inventory back action, and replaced inconsistent icon styling.
- Added a styled image selector with live preview and filename feedback.
- Added a sticky save action with loading spinner plus responsive single-column behavior on small screens.
- Preserved entered values after validation errors so staff do not need to retype the form.

## Add Product inline validation
- Replaced overlapping browser validation popups with clear inline messages beneath each invalid field.
- Invalid fields now receive a visible red state, accessibility attributes, automatic focus and smooth scrolling.
- Validation messages clear immediately when the user corrects the relevant value.

## Dashboard validation coordination
- Updated the dashboard AJAX form handler to respect forms cancelled by page-level validation.
- Invalid Add Product submissions now remain on the page with inline field messages instead of being posted and replaced by the legacy generic banner.
- Replaced the old technical validation fallback with clearer user-friendly guidance for no-JavaScript requests.

## Warehouse Inventory audit and redesign
- Rebuilt the Warehouse Inventory Hub with product, unit, low-stock and stock-value summaries.
- Added instant search, stock-status filtering, live result counts, responsive mobile rows and a direct Add Product action.
- Replaced remote placeholder images with safe local image checks and a built-in no-image state.
- Corrected the malformed Warehouse navigation URL that encoded an unnecessary nested route.
- Product removal now requires POST and CSRF validation, uses a database transaction, and respects foreign-key protection for products referenced by orders.
- Product images are deleted only after the database deletion succeeds, preventing broken catalog images after rejected removals.
- Removal audit logs now use a dedicated Inventory Delete activity type, and inventory summaries refresh after successful removal.

## Warehouse summary card colors
- Added distinct semantic backgrounds to Warehouse summary cards: blue for catalog products, green for available units, burnt orange for low stock and indigo for stock value.
- Added matching accent borders, readable text colors and a subtle hover lift while preserving responsive layouts.

## Low Stock Monitor audit and redesign
- Standardized the low-stock threshold to fewer than 5 units, correcting products with 4 units that were previously labelled healthy.
- Corrected the permission check to use the Low Stock Monitor permission instead of Warehouse access.
- Secured restocking with CSRF validation, bounded quantities, transactions, row locking and product-existence checks.
- Added detailed Inventory Restock audit logs containing the product, added quantity, previous stock and resulting stock.
- Removed the obsolete dashboard quick-restock engine that used incompatible request fields and bypassed the current form workflow.
- Replaced synthetic SKU labels with real database SKUs and added safe local image handling.
- Redesigned the page with semantic summary cards, attention-first filtering, search, responsive table cards, clearer status badges and a direct Stock Dispatcher action.

## Categories and Brands audit and redesign
- Replaced multiple loosely coupled POST fields with one explicit, whitelisted taxonomy action controller for add, rename and delete operations.
- Added CSRF protection, strict name validation, duplicate handling, safe database errors and dedicated Catalog Taxonomy audit logs.
- Added usage-aware deletion: categories and brands assigned to products are visibly protected and also checked transactionally on the server.
- Added secure in-page renaming and retired the older GET-based delete and edit endpoints that could bypass the protected workflow.
- Redesigned the page with category, brand and catalog-product summary cards, global search, assignment counts, clearer actions, responsive panels and user-friendly copy.
- Added loading feedback to create actions and a focused rename dialog.

## Customer Wallets audit and redesign
- Added CSRF protection, strict validation, prepared statements and wallet row locking.
- Failed balance or audit-log writes now roll back the entire adjustment.
- Corrected currency labels to KES and added wallet summary cards.
- Added customer search, balance filters, responsive layout and adjustment feedback.

## Payment Reference Lookup correction
- Replaced false live-network and completed-payment claims with an accurate local-record lookup.
- Results now show the real stored payment status, method, amount, customer, order and timestamp.
- Added strict reference validation, recent reference history, status-specific badges and responsive layout.
- Clearly states that the page does not contact Safaricom or independently verify M-Pesa settlement.

## Installment Defaulters correction and redesign
- Defaulters now means active plans with balances beyond the established 30-day Nairobi deadline, not every open installment plan.
- Added CSRF protection, strict amount validation, row locking, overpayment prevention and atomic payment/audit updates.
- Recorded collections receive unique references and timestamps; fully settled orders return to Processing.
- Added overdue-plan, outstanding-balance and affected-customer summaries plus exact deadlines and days overdue.
- Added customer search, contact details, responsive layout, confirmation and safe failure feedback.

## Financial Analytics correction and redesign
- Replaced non-cancelled order totals with fully paid, non-cancelled and non-refunded settlement totals.
- Daily results now use the final completed-payment date instead of the order creation date.
- Added validated date-range filters and consistent Gross, Net and stored VAT calculations.
- Removed unsupported cleared-revenue and real-time KRA-liability claims.
- Added settled-order summaries, an accurate daily settlement ledger, responsive layout and clear exclusions.
- Added Pending and Cancelled order summaries for orders created within the selected period, showing counts and gross order values without treating either as settled revenue.

## Global Tax Settings correction and redesign
- Added CSRF protection and strict 0.00% to 100.00% validation with at most two decimal places.
- Tax updates, collision-resistant history snapshots and mandatory operator audit entries now commit atomically.
- The active setting is inserted when missing and updated when present.
- Clarified that rate changes affect future orders only; existing orders retain their stored tax rate.
- Added current-rate summary, confirmation/loading feedback, responsive layout and readable history.
- Tax history now displays the actual Nairobi date and time for both new timestamped archives and older Unix-timestamp archive records.
- Refined the Tax Settings UI with a compact two-column control area, stronger active-rate hierarchy, quieter preservation guidance, improved history badges and better mobile stacking.
- Added active-rate last-change timing, before-to-after rate transitions, clearer history labels and a Save button that activates only when the entered rate differs.

## Manage Staff Network security and UI audit
- Added CSRF protection across staff, role and permission forms and replaced URL-based account purging with confirmed POST suspension.
- Added strict staff name, email, password and role validation while preventing customer-role assignment and unauthorized super-admin escalation.
- Staff creation/reactivation, profile updates, permission changes, suspensions and mandatory audit records now use transactions.
- Submitted permissions are whitelisted against registered system modules before assignment.
- Customer accounts are excluded from the staff registry and customer is removed from staff role selectors.
- Added active-staff search, live result counts, clearer suspension wording, card hover/focus feedback, loading states and improved mobile layout.

## Staff Customer Directory correction and redesign
- Fixed the malformed PHP opening tag that prevented the page from rendering.
- Disabled an inappropriate hidden endpoint that allowed staff to change customer roles; the directory is now explicitly read-only.
- Replaced one order-count query per customer with a single aggregate customer/order query.
- Added active-customer, customers-with-orders and lifetime-order-value summaries.
- Added instant search across customer contact, PIN and address details plus live result counts and responsive styling.

## Shared Staff navigation repair
- Made the shared Staff navbar self-contained so pages such as Feedback Moderator no longer fall back to unstyled browser links.
- Added a consistent dark header, active-page highlighting, readable operator controls and responsive wrapping/scrolling for smaller screens.

## Staff Payment Reference Lookup correction
- Enforced the current authenticated Staff session and explicit M-Pesa Checker permission before rendering.

## Staff Order Books transactional status updates
- Order status submissions accept only Pending, Processing or Delivered and retain CSRF validation.
- Cancelled and Delivered orders are treated as finalized and cannot be changed by Staff.
- Processing and Delivered remain blocked until the shared settlement guard confirms full payment.
- Settlement state is rechecked while the order is locked inside the update transaction.
- The order update and mandatory Staff audit record now commit atomically; either failure rolls back both changes.
- Successful changes rotate the Staff Order Books CSRF token.

## Staff Order Details and Invoice Print authorization
- Staff Order Details now requires the explicit manage_orders.php permission rather than accepting any Staff session.
- Shared Staff navigation on Order Details renders only after authorization and inside the HTML document body.
- Staff Invoice Print now requires the same explicit Order Books permission.
- Both pages use the shared authentication and inactivity-timeout guard.
- Unauthorized operators return to the Staff dashboard with the standard access-denied message.

## Staff Pole Pole cancellation and refund audit
- Restricted the Staff Pole Pole page to operators explicitly assigned layaway_defaulters.php; Super Administrator retains master access.
- Added the shared authentication and inactivity-timeout guard and moved shared navigation after authorization.
- Added a dedicated CSRF token, strict plan/order ID validation and token rotation after success.
- The selected active installment plan and linked order are locked and cross-checked before cancellation.
- Already cancelled, delivered or inactive records are rejected, preventing repeated cancellation and duplicate stock restoration.
- Refund amounts now come from verified completed payments rather than the plan deposit field.
- Verified refunds are credited to the customer wallet, creating the wallet row when missing.
- Completed payment rows are marked Refunded so settlement state cannot count them again.
- The installment plan and linked order are cancelled, and ordered inventory is restored exactly once.
- Refund history and a mandatory Financial Update Staff audit record are saved in the same transaction.
- Any plan, order, wallet, payment, stock, refund-log or audit failure rolls back the complete operation.
- Success wording now accurately states whether money was refunded or no completed payment existed.

## Admin order cancellation and installment safeguard alignment
- Audited Admin Installment Defaulters and Manage Sales against the secured Staff Pole Pole workflow.
- Confirmed Admin overdue-payment collection already validates CSRF and payment amounts, locks the plan and order, prevents overpayment, records payment and balance changes atomically, moves fully paid orders to Processing and requires a Staff audit record.
- Added successful CSRF-token rotation to the Admin overdue-payment collector while keeping the rendered form token synchronized.
- Added CSRF protection and strict positive order-ID validation to Admin status and cancellation forms.
- Admin cancellation continues deriving refunds only from verified completed payments and locking the order and customer wallet.
- Completed payments are marked Refunded and linked installment plans are cancelled.
- Added inventory restoration for every cancelled order; finalized-order checks prevent repeat cancellation and duplicate restocking.
- Added mandatory refund_logs history for both paid and zero-payment cancellations.
- Made order lookup, cancellation, wallet credit, payment reconciliation, installment cancellation, inventory restoration, refund history and audit writes mandatory.
- Admin cancellation now rolls back the entire order, payment, wallet, installment, inventory, refund-history and audit operation if any step fails.
- Made Admin order-status audit logging mandatory so a status update cannot commit without its audit trail.
- Successful Admin status and cancellation actions rotate the Orders CSRF token while keeping rendered forms synchronized.
- Verified admin/manage_orders.php and admin/layaway_defaulters.php have no PHP syntax errors.

## Staff dashboard shift security and integrity
- Added the shared authentication and 15-minute inactivity-timeout guard to the Staff dashboard.
- Added a dedicated CSRF token to both Clock In and Conclude Shift forms.
- Shift submissions now strictly whitelist clock_in/clock_out actions and regular, night or short_coverage shift types.
- Clock-in locks the operator account before checking attendance, serializing repeated submissions for the same operator.
- A second active-shift check runs inside the transaction and rejects duplicate active attendance rows.
- Starting a shift and its mandatory System Update audit record now commit together or roll back together.
- Clock-out locks the active attendance row and updates it only while its status remains Active.
- Concluding a shift and its mandatory System Update audit record now commit together or roll back together.
- Repeated or concurrent clock-out attempts cannot complete the same attendance row twice.
- Rejected clock-out validation and missing-active-shift cases explicitly roll back the transaction.
- Successful clock-in and clock-out actions rotate the shift CSRF token.
- Internal transaction failures are logged privately while the page retains operator-facing feedback.

## Shared Staff navigation permission and encoding correction
- Hardened shared navigation permission loading so a failed permission query hides restricted links instead of breaking page rendering.
- Standardized database and authentication includes using paths anchored to the navigation file.
- Normalized the operator ID before permission lookup.
- Super Administrator now sees all six authorized Staff modules without requiring redundant staff_permissions rows.
- Ordinary operators continue seeing only modules explicitly assigned to them.
- Replaced all eight corrupted navigation symbols with encoding-safe numeric HTML entities.
- Added a quoted navigation class and an accessible Staff workspace navigation label.
- Confirmed eight interactive Staff pages use the shared navigation; Invoice Print intentionally remains navigation-free for print output.
- Verified six Super Administrator-aware permission gates and eight clean navigation labels are present.
- Verified all ten PHP files in the Staff directory have no syntax errors after the shared-navigation correction.

## Staff dashboard metric and payment-record correction
- Replaced Delivered-status revenue with a settlement-based realized-revenue calculation.
- Realized revenue now includes only non-cancelled orders whose completed payments cover the order total and whose latest installment balance is cleared.
- Active installment totals now require an Active plan, a balance above KES 0.009 and a non-cancelled linked order.
- Changed the stock KPI from zero-stock-only to the shared low-stock threshold of fewer than five units.
- Added safe zero/empty fallbacks when dashboard metric or recent-payment queries fail.
- Renamed the stock card to Low Stock Alerts and synchronized its count with the Low Stock Monitor.
- Replaced the recent income/audit claim with Recent Stored Payment Records.
- Recent payments now display their actual stored status, including Completed, Pending, Refunded and Failed styling.
- Removed the invented TXN_MPESA_AUDITED fallback; missing references now display Not recorded.
- Removed the unsupported payment-reference hash and cleared-payment claims, and made unlinked orders explicit.
- Verified the dashboard has no PHP syntax errors and all corrected SQL executes against the local database.
- Read-only validation returned KES 1,684,700 realized revenue, 9 active plans with balances and 1 low-stock product.
- Added strict bounded reference validation and exact trimmed database matching.
- Moved the shared navbar into the document body so page markup remains valid.
- Replaced the fixed Completed / Audited claim with the actual stored payment status.
- Added a clear notice that the page checks local store records and does not independently verify Safaricom settlement.

## Staff Payment Reference Lookup bug recheck
- The stored payment status is now rendered directly by PHP, so pending and refunded records cannot fall back to a false Completed label when JavaScript is unavailable.
- Legacy one-character references are accepted, while input remains restricted to letters, numbers, hyphens and underscores with a 100-character limit.
- Duplicate references now return every matching ledger entry with an explicit non-unique-reference warning instead of silently displaying one arbitrary payment.
- Reference matching now trims stored values and orders duplicate matches by newest payment ID first.
- Modern wallet deposits whose references embed a customer ID are attributed to that customer; older deposits without reliable ownership remain clearly labelled Not linked.

## Staff Customer Feedback Moderator audit and correction
- Confirmed live review data has valid ratings, no orphaned customers or products, no duplicate customer/product reviews and consistent approval/status flags.
- Made review status changes and mandatory staff audit logging atomic, with rollback if either write fails.
- Added proper HTTP status codes for access denial, expired CSRF tokens, invalid requests, state conflicts and server failures.
- Replaced assembled filter SQL with prepared parameters for status, rating and text search while keeping pagination bounded.
- Corrected the review metadata separator and added visible Saving feedback to moderation buttons.
- Buttons now recover correctly after handled or network failures instead of remaining disabled.

- Corrected the multiply encoded review-date separator; review metadata now uses a plain hyphen that renders consistently across browsers.

## Staff Low Stock Monitor audit and correction
- Replaced the obsolete Staff-only stock page with the shared secured monitor while preserving explicit Staff permission checks.
- Added CSRF-protected bounded restocking, row locking and transactional stock updates.
- Made staff audit logging mandatory: a restock now rolls back if its audit record cannot be saved.
- Corrected out-of-stock handling for zero or invalid negative quantities and prevented negative values from inflating available-unit totals.
- Kept the administrator-only Stock Dispatcher action out of the Staff view and corrected the product metadata separator.
- Verified PHP syntax, unauthenticated login redirection and an authorized read-only render without changing live stock.

## Stock Dispatcher audit and correction
- Made reorder confirmation, cancellation and receipt transactional with mandatory staff audit logging before commit.
- Kept receipt idempotent by locking the active reorder before adding delivered units to inventory.
- Added locked product and active-request checks to reduce duplicate reorder races and reject products no longer below the reorder threshold.
- Added strict integer and length validation for reorder quantities, lead time and supplier contact fields.
- Rotated the CSRF token after successful state changes and replaced internal exception details with safe user messages.
- Corrected the dispatcher metadata separator and replaced script-based access denial with a proper server redirect.
- Verified PHP syntax, unauthenticated redirection and CSRF rejection; database stock, reorder and audit totals remained unchanged during testing.

## Customer self-reactivation during registration
- Customer registration now restores a soft-deleted customer when the same email is registered again.
- The returning customer supplies new name, phone and password details; the existing customer ID and related history are preserved.
- Active duplicate emails remain blocked, and deleted staff/admin accounts cannot be converted into customers through public registration.
- Phone ownership is checked before restoration, reset tokens are cleared and all lookup/update work is transactional.
- Added distinct login-page messages for newly created and restored accounts.
- Verified PHP syntax and the purged-customer restoration path inside a rolled-back test; no test account remained in the database.

## Customer Management deactivation audit and correction
- Added administrator-only customer deactivation while keeping ordinary Staff access read-only.
- Deactivation uses POST, CSRF validation, customer-role verification, row locking and a database transaction.
- Mandatory staff audit logging occurs before commit; failures roll back the account-status change.
- The confirmation explains that login access is removed while orders, payments, wallet data and reviews remain preserved.
- Reset tokens are cleared during deactivation, and already-deactivated or non-customer accounts are rejected.
- Restored the shared Staff navigation before the Customer Management content.
- Verified the administrator action render and used a rolled-back live relationship check: 67 orders, 33 payments, one wallet and six reviews remained linked to the tested customer; its status returned to active after rollback.

## Customer registration and login audit
- Login now matches normalized email addresses, so capitalization and accidental surrounding spaces do not block a valid account.
- Unknown emails and wrong passwords now share the same three-attempt counter and reliably trigger the 30-second lockout.
- Expired CSRF submissions display a clear inline message instead of a corrupted JavaScript popup.
- Login accepts only recognized account roles; an invalid or missing role no longer falls through into customer access.
- Removed the obsolete duplicate password-toggle and delayed-submit script; the shared authentication UI script remains authoritative.
- Replaced corrupted security symbols and technical authentication wording with clear messages.
- Verified PHP syntax, mixed-case login for james@gmail.com, customer redirection, unknown-email lockout and inline CSRF failure handling through local HTTP requests.

## Password recovery audit and correction
- Added CSRF protection and a one-minute request cooldown to the forgot-password form.
- Recovery requests now use normalized email matching and only active accounts are eligible, while unknown and inactive accounts receive the same generic response.
- Increased reset tokens to 256 bits and builds links from the current host or ADONAK_BASE_URL instead of hard-coded localhost.
- Reset tokens are stored only after email delivery succeeds; delivery failures are logged privately and clear any token for that account.
- Password reset now validates exact token format, expiry and active account status again inside a row-locked transaction.
- A token is single-use, the new password cannot equal the existing password, and password/token changes commit atomically.
- Removed public SMTP error details and corrected recovery-page text encoding.
- Verified PHP syntax, malformed/unknown token rejection, CSRF rejection and generic unknown-account responses; password/token data hashes remained unchanged during rejection tests.

## Super Administrator email OTP login
- Added a second login step only for the Super Administrator role; all other roles retain their current login flow.
- Correct email/password verification sends a six-digit code to the registered Super Administrator email before any privileged session is created.
- OTP codes are stored only as password hashes in the session, expire after five minutes and are single-use.
- Limited verification to five attempts and resending to once per minute; resending replaces the previous code.
- Direct OTP-page access is rejected without a pending password-verified challenge, and active Super Administrator status is rechecked before login completes.
- Staff login auditing occurs only after successful OTP verification.
- Added a responsive OTP page with masked email, numeric autofill, resend and return-to-login controls.
- Verified PHP syntax, direct-access rejection and the wrong-code attempt counter without emailing a real code or completing a live administrator login.

## Operational Account Security audit and correction
- Kept the standalone Account Security page restricted to administrator and operational staff roles; customers continue using their Profile password controls.
- Password changes now lock the active account row and update the password, clear reset tokens and write a mandatory staff audit record in one transaction.
- A failed audit record rolls back the password change.
- Added a five-attempt current-password limit with a 60-second lockout to slow guessing from an already-open session.
- Uses the default current password-hashing algorithm and keeps session-ID and CSRF rotation after success.
- Added Show/Hide controls for all three password fields while retaining the responsive layout.
- Verified PHP syntax and the five-attempt lockout using a temporary session; the tested password hash and staff-log count remained unchanged.

## Staff dashboard page inventory and Order Books checkpoint
- Confirmed six primary Staff modules: Order Books, Pole Pole, Stock Alarms, M-Pesa Check, Clients and Reviews.
- Confirmed supporting Order Details and Invoice Print pages plus shared Account Security and Logout actions.
- All ten PHP files in the Staff directory passed syntax validation before the Order Books changes began.
- Stock Alarms, M-Pesa Check, Clients and Reviews already enforce explicit assigned permissions.
- Identified missing explicit permission enforcement on Order Books, Pole Pole, Order Details and Invoice Print.
- Identified dashboard quick actions that remain visible without matching assigned permissions.
- Began the Order Books security correction in staff/manage_orders.php.
- Order Books now checks the explicit manage_orders.php permission before rendering and loads shared navigation only after authorization.
- Added a dedicated CSRF token to the Staff Order Books session and validated submitted order IDs against a strict status whitelist.
- Added the CSRF token to every order-status form.
- The Staff Order Books permission gap and its two supporting-page access gaps are now closed.

## Staff dashboard quick-action permission filtering
- Process Orders now appears only with the manage_orders.php permission.
- Check Stock now appears only with the low_stock_monitor.php permission.
- Installment Alerts now appears only with the layaway_defaulters.php permission.
- Customers now appears only with the manage_customers.php permission.
- Super Administrator retains access to all four actions, and the action container is hidden when no matching action is assigned.

## Pole Pole invoice payment-history and accountability correction
- Staff Invoice Print now classifies documents from the shared settlement state as a Paid Sales Receipt, Provisional Order Invoice or Cancelled Order Record.
- Staff invoices include Served By and Document Issued By, use the order's stored tax rate and retain the original order value on cancelled records.
- Added a chronological payment table to Staff invoices showing the stored date, payment method, reference, amount and actual status for every payment row.
- Pending, failed and refunded rows remain visibly distinct and are not counted as completed payments.
- Replaced unsupported KRA/payment-network verification wording with a precise statement that the document comes from ADONAK store records.

## Admin Pole Pole invoice parity
- Removed the fully-paid-only gate from the Admin Invoice PDF Archive.
- The archive now produces a Paid Sales Receipt for settled orders, a Provisional Pole Pole Invoice for active installment orders with a balance, a Provisional Order Invoice for other unpaid orders and a Cancelled Order Record for cancelled orders.
- Added CSRF protection and successful-token rotation to the Admin invoice lookup.
- Added Served By and Document Issued By to both the Admin Invoice Archive and the Admin direct-print invoice.
- Both Admin invoice entry points now list every recorded payment with date, method, reference, amount and stored status, followed by completed-payment, refund and outstanding-balance totals.
- The Admin direct-print invoice now uses KES consistently, prepared queries and strict positive order-number validation.
- Cancelled records preserve the original sale values and show recorded refunds instead of rewriting historical totals to zero.
- Order #89 was verified as a Provisional Pole Pole Invoice: order total KES 300.00, one completed Lipa Pole Pole payment of KES 150.00, and an outstanding balance of KES 150.00.
- Order #89 records the payment reference as zero, so the invoice truthfully displays Not recorded rather than inventing a reference.
- All three invoice files passed PHP syntax validation, and read-only render checks confirmed payment history, actual status, accountability names, KES currency, the KES 150.00 balance and removal of the old blocking/false-verification wording.

Files: staff/print_invoice.php, admin/invoice_archiver.php, admin/print_invoice.php.

## Staff Stock Alarms audit and correction
- Confirmed Stock Alarms remains protected by the explicit low_stock_monitor.php permission; Super Administrator retains master access and ordinary Staff require a direct assignment.
- Fixed the Staff restock response path so a submitted restock continues rendering the complete Staff page and navbar instead of exiting through an Admin-only content fragment.
- Confirmed the shared Staff/Admin restock operation validates CSRF, product ID and a quantity from 1 to 100,000, locks the product row and updates stock with a mandatory audit record in one transaction.
- Added explicit failures for product-query preparation/execution and stock-update preparation, plus a signed-database-integer ceiling check before updating inventory.
- Extended audit attribution to use either the session fullname or staff_name and retained single-use CSRF rotation after a successful restock.
- Corrected the malformed brand/category separator in the shared stock table.
- Verified an assigned Staff account receives the complete Stock Alarms page and an unassigned Staff account receives neither the stock module nor the Staff page shell.
- Verified an invalid restock retains the full Staff layout while changing neither product stock nor Inventory Restock audit history.
- Live read-only metrics remain 0 out of stock, 1 low-stock product, 6 healthy products and 129 available units across 7 products; no inventory quantity was changed during this audit.
- Both Staff and shared Admin Stock Alarm files passed PHP syntax validation and the rendered page passed raw UTF-8 artifact checks.

Files: staff/low_stock_monitor.php and admin/low_stock_monitor.php.

## Staff Clients audit and correction
- Confirmed Clients remains protected by the explicit manage_customers.php permission; ordinary Staff require a direct assignment and Super Administrator retains master access.
- Confirmed assigned Staff receive a read-only customer directory while only an assigned Administrator or Super Administrator receives the account-deactivation action.
- Preserved the existing locked, CSRF-protected and audited customer-deactivation transaction, including retention of orders, payments, wallet and review history.
- Closed prepared statements before throwing on customer lookup, account update or audit execution failures and extended operator attribution to the staff_name session fallback.
- Removed the redundant order-count query that previously ran once for every displayed customer; all order counts now come from the single registry aggregate query.
- Customer rows now show the stored account status instead of labelling every non-purged account Active.
- Renamed Purchases to Order Records and show both total historical records and the non-cancelled count.
- Replaced the misleading Lifetime order value calculation with Non-cancelled order value, excluding cancelled orders while keeping their historical records visible in the count.
- Replaced raw database-structure error disclosure with a safe customer-facing error and server-side error logging.
- Fixed search counting so only real customer rows are counted when the registry is empty or filtered.
- Repaired all seven shared Staff navbar links; their class attributes, active state, icons and labels now render as valid HTML.
- Browser-equivalent tests confirmed two visible customers, two customers with order records, and KES 2,150,448.60 non-cancelled order value. The eight cancelled orders worth KES 2,120.00 are excluded from that value.
- An assigned Staff account could view the registry without deactivation controls, an assigned Administrator received the protected controls, and an unassigned Staff account received no customer data.
- Denied Staff and invalid-Admin-CSRF deactivation tests changed neither customer status nor Customer Deactivation audit history.
- All 10 Staff PHP files passed syntax validation after the shared navbar repair; no customer data was changed during this audit.

Files: staff/manage_customers.php and staff/navbar.php.

## Staff Reviews audit and correction
- Confirmed Reviews remains protected by the explicit manage_reviews.php permission; assigned Staff, assigned Administrators and Super Administrator can moderate, while unassigned Staff receive no review module or review data.
- Confirmed customer submission is restricted to products in delivered orders, ratings must be 1-5, comments must be 10-1,500 characters and editing closes after 15 minutes.
- Confirmed the database has unique customer/product indexes, preventing duplicate reviews during concurrent submissions.
- Moderation now locks and loads the target review before updating, rejects missing or already-current states and re-verifies a delivered purchase before approval.
- The moderation update records status, approval flag, moderator, moderation time and an explicit moderation note.
- Replaced the generic System Update audit with Review Moderation and added review ID, customer, product, previous state and new state to the mandatory transactional audit entry.
- Review moderation CSRF tokens now rotate after successful moderation and remain unchanged on invalid-CSRF or no-op failures.
- Added checked query preparation/execution, safe load-error messages and server-side logging for review count, list and summary failures.
- Review cards now show Verified delivered purchase or Delivered purchase not found; Approve is unavailable in the UI without verified delivery and the server independently enforces the same rule.
- Review cards show the last moderation timestamp when available, and pagination is hidden when unnecessary or when loading fails.
- Public product pages now require a review to be Approved, Live and still linked to a delivered purchase for both the displayed list and average rating.
- Hardened customer review-page bootstrapping with safe session startup and __DIR__ database loading.
- Customer review-form CSRF tokens now rotate after successful insert or edit only; all four success/failure token branches were verified explicitly.
- Live read-only checks found nine reviews, all Live, Approved, valid 1-5 ratings and linked to delivered purchases, with no duplicate customer/product pairs.
- Product #54 rendered two publicly eligible reviews with a 4.5 average; the full public eligibility set contains nine reviews with a 4.78 average.
- Invalid-CSRF and already-current-state moderation tests changed neither review data nor Review Moderation audit history. Approval locking was tested inside a rolled-back transaction.
- All four Review-related PHP files passed syntax validation; no review status, moderation metadata or audit record was changed during this audit.

Files: admin/manage_reviews.php, staff/manage_reviews.php, customer/submit_review.php and customer/product_detail.php.

## Staff and Admin Payment Reference Lookup audit and correction
- Confirmed the Staff M-Pesa Check page remains protected by the explicit mpesa_checker.php assignment; ordinary Staff require a direct assignment and Super Administrator retains master access.
- Confirmed the Admin component retains the Admin/Super Administrator bypass and assigned-operator permission behavior provided by verifyWorkspaceClearance.
- Renamed Staff-facing hash/settlement language to accurate local Payment Reference wording and retained the notice that the page does not contact Safaricom or independently verify settlement.
- Staff and Admin searches now accept only 4-100 letters, numbers, hyphens or underscores and explicitly reject the historical placeholder reference 0.
- Both searches now return every matching local payment row in newest-first order, show its record ID and warn when a reference is reused instead of silently treating the newest row as unique.
- Both pages show the stored payment method, amount, actual payment status, linked order, customer and recorded time; Staff also shows the registered phone when available.
- Corrected the Staff status styling so completed, refunded and failed records retain their distinct colors instead of every non-success class being forced to pending yellow.
- Added safe lookup failure handling instead of allowing database prepare errors to become fatal page failures.
- Admin Recent Payment References now excludes the 32 legacy rows whose reference is 0, while retaining the newest 20 genuine references.
- User-linked M-Pesa deposits with references such as TXN_DEP_U7_99B4B296 now resolve the customer on both Staff and Admin even though the payment itself is not linked to an order.
- Read-only live checks confirmed TXN_6A7D7B0144BD0 belongs to james kimani, order #103, Customer Wallet, KES 160,000.00, completed; TXN_DEP_U7_99B4B296 resolves to james kimani, M-Pesa Deposit, KES 500,000.00, completed and no linked order.
- Rendered-page checks passed for both Staff and Admin with a genuine order reference, a user-linked deposit reference and the rejected 0 placeholder; Staff also passed the unknown-reference message, and Admin recent results contained no placeholder 0 codes.
- Both files passed PHP syntax validation. No payment row or other business data was changed during this audit.
- In-app visual browser verification could not start because the local Windows sandbox refresh failed; rendered PHP templates, live queries and content assertions were used instead and all passed.

Files: staff/mpesa_checker.php and admin/mpesa_checker.php.

## Final Staff dashboard and roles-table regression
- Confirmed roles are authoritative through users.role_id -> roles.id; login continues copying the verified roles.role_name into the session for navigation and routing.
- Added a shared live-role resolver that rechecks the current account against users and roles, requires an active account, normalizes the role name and rejects customers, missing accounts and unsupported roles from workspace access.
- Workspace permissions no longer trust a stale or forged session role or the legacy user_permissions JSON value; live roles and staff_permissions rows are now authoritative and failures deny access.
- Staff dashboard, shared Staff navbar and Account Security now use the live database role. Account Security remains available to valid workspace roles and rejects customer sessions even when their session role text is forged.
- Removed dashboard.php and dashboard_overview.php from universal baseline access. Only verified workspace roles can use the Staff dashboard baseline, and Staff roles can no longer enter the Admin dashboard without the required Admin role.
- Added an entry-area boundary based on the real requested script: direct /admin pages require the database role admin or super_admin, while Staff wrapper pages can continue including shared Admin components for Reviews and Stock Alarms.
- Closed seven same-filename cross-folder collisions: manage_orders.php, layaway_defaulters.php, low_stock_monitor.php, manage_reviews.php, mpesa_checker.php, print_invoice.php and view_order_items.php. A Staff assignment for one of these filenames no longer grants access to the stronger Admin page with the same name.
- Removed the shared authentication file's closing PHP tag and trailing whitespace so access-denied redirects do not emit content or trigger header warnings.
- Replaced JavaScript-only access denials in Admin Orders, Reviews, Invoice Print and Order Details with server-side redirects and 403 AUTH_ERROR or access-denied responses for AJAX/POST requests.
- Corrected Admin Database Backup bootstrap order so the database is available before the roles-table gate runs.
- Removed the former Super Administrator elevation through a permission string; only the live roles.role_name value super_admin now enables Super Administrator shift overrides and complete Staff navigation.
- Live role tests passed for assigned Staff #6 and #10, Administrator #14, Super Administrator #5, customer #7 and a missing account. Forged session roles and forged JSON permissions did not change the allow/deny result.
- Navbar and quick-action visibility passed for Staff #6, Staff #10 and the Super Administrator. Each Staff account saw only live assigned modules, while the Super Administrator retained the complete workspace.
- All seven direct Staff-to-Admin collision tests returned zero page content; Admin Orders AJAX denial returned AUTH_ERROR. Legitimate Admin dashboard, Database Backup, Staff dashboard, Reviews, Stock Alarms, Order Details and Invoice pages all rendered without PHP errors.
- PHP syntax validation passed for all ten Staff files and every shared/Admin file changed in this phase.
- Final read-only integrity counts remained users 8, roles 8, permissions 63, orders 87, payments 69 totaling KES 4,945,230.40, staff logs 263 and attendance rows 14. No business data or audit record was changed during this regression.

Files: session_auth.php, account_security.php, staff/staff_dashboard.php, staff/navbar.php, admin/db_backup.php, admin/manage_orders.php, admin/manage_reviews.php, admin/print_invoice.php and admin/view_order_items.php.


## Profit, payroll and operating-expense accounting
- Added product buying cost as products.cost_price and an immutable order-line snapshot as order_items.unit_cost. Existing historical values intentionally remain NULL rather than inventing costs.
- Add Product and Edit Product now require a positive buying cost, log the recorded selling and buying values, and Warehouse shows selling price, buying cost, missing-cost count and inventory value based on buying cost rather than selling price.
- Checkout now locks and reads the live buying cost, blocks a sale if a product has no valid cost, and freezes that value on every new order item so later price edits cannot rewrite historical profit.
- Confirmed the user entered buying prices for all seven current products; 7/7 are ready for future profit tracking and checkout. The 125 lines in the existing 87 historical orders still have no frozen cost, by design.
- Financial Analytics now calculates profit only for fully paid, non-cancelled and non-refunded orders whose every order line has a frozen cost. Gross profit is settled net sales after VAT less frozen product costs. Coverage counts and warnings prevent incomplete orders from being treated as 100% profit.
- Added accountable Payroll under Admin Finance & Reports. Active non-customer users from the roles table can receive a monthly salary profile; payroll is created as a full calendar-month draft with basic salary, allowances, deductions, gross cost and net pay snapshots.
- Payroll follows Draft -> Paid -> Voided. Paid status requires payment date, method and reference and records who paid it. Voiding requires a reason and retains the original record and actor history. Gross payroll cost (basic plus allowances), not net cash after deductions, feeds business expenses only after payment.
- Added Operating Expenses under Admin Finance & Reports for Transport, Rent, Utilities, Internet & Airtime, Delivery & Logistics, Repairs & Maintenance, Marketing, Licences & Fees, Office Supplies, Security, Insurance, Bank Charges and Other. Salary is deliberately excluded to prevent double-counting with Payroll.
- Every operating expense requires date, amount, description, payment method and receipt/transaction reference plus the recorder's ID/name. Corrections use void-with-reason rather than deletion.
- Net profit is available only when the period's settled-order product-cost coverage is complete: gross product profit minus active non-salary operating expenses minus paid gross payroll by payment date. Draft/voided payroll and voided expenses are excluded.
- Registered payroll.php and operating_expenses.php in system_permissions and added both links to the Admin Finance & Reports menu. No Staff user received either permission automatically.
- Activated operating_expenses, staff_salary_profiles and payroll_records in the local database. Their final live counts remain zero; no salary, payroll or expense amount was invented.
- PHP syntax passed for all eight changed application pages. Authenticated server rendering passed for Financial Analytics, Payroll and Operating Expenses.
- Rolled-back form tests verified salary-profile/draft creation (KES 30,000 basic + KES 2,000 allowance - KES 1,000 deduction = KES 32,000 gross cost and KES 31,000 net pay), Paid payroll accountability, KES 1,500 Transport recording, expense voiding, and combined expense aggregation. All test salary, payroll, expense and audit rows were rolled back to zero.
- The in-app visual browser could not connect because the local Windows sandbox refresh failed; authenticated PHP rendering, live schema checks and content assertions were used as the fallback and passed.

Files: database_migrations/2026_08_13_profit_tracking.sql, database_migrations/2026_08_13_payroll.sql, database_migrations/2026_08_13_operating_expenses.sql, admin/add_product.php, admin/edit_product.php, admin/warehouse.php, customer/checkout_form_process.php, admin/sales_analytics.php, admin/payroll.php, admin/operating_expenses.php and admin/dashboard.php.



## Authorized historical buying-cost backfill
- The user explicitly authorized BACK FILL HISTORICAL COSTS on 13 Aug 2026 after all seven current products had buying prices.
- Used each current product cost_price as a historical estimate only where order_items.unit_cost was NULL or non-positive and the source product still existed with a positive buying price.
- Created order_item_cost_backfill_audit before updating. Batch HISTORICAL_COST_20260813_CURRENT_PRODUCT_COST stores every changed order-item ID, order, product/name/SKU snapshot, quantity, old value, new cost, actor, timestamp and reason.
- Transactionally snapshot and updated counts matched exactly: 124 audited order lines, 124 updated order lines and zero post-update mismatches. The batch covers 339 historical units with estimated COGS KES 19,763,850.00.
- Added one Historical Cost Backfill staff_logs entry attributed to Super Administrator Maxies John. Orders, payments and order-item counts remained 87, 69 and 125.
- One line remains unresolved: order_item #63 on delivered order #17, quantity 4, sale unit price KES 700.00. Its product_id is NULL because the product was deleted, so no current buying-price source exists. It was not guessed or updated.
- For 1-13 Aug 2026, Financial Analytics is now 10/10 profit-ready settled orders: profit-ready net sales KES 1,514,889.10, recorded COGS KES 1,153,150.00 and gross profit KES 361,739.10 before payroll and operating expenses.
- Authenticated page rendering confirmed the 10/10 coverage, COGS and gross-profit figures and confirmed the incomplete-profit warning is absent for that period.
- This is an estimate based on current buying prices, not proof that those same costs applied on each historical sale date. The permanent row-level audit enables a controlled reversal or correction if original invoices later provide different costs.

Files: database_migrations/2026_08_13_historical_cost_backfill_audit.sql and note.md.

