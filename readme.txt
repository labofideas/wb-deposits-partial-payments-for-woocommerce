=== WB Deposits & Partial Payments for WooCommerce ===
Contributors: wbcomdesigns
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept deposits, split balance into linked orders, and automate due-date reminders.

== Description ==

WB Deposits & Partial Payments for WooCommerce lets customers pay a deposit now and pay the remaining balance later.

Core features:

* Global, category, and product-level deposit rules (fixed or percentage).
* Optional or mandatory deposit mode.
* Automatic linked balance order creation with due date.
* Reminder scheduling with Action Scheduler / WP-Cron.
* My Account “Pay balance” action and deposit summary.
* Admin controls for due date edits, manual reminders, and manual paid marking.
* Outstanding/overdue balance report.
* Cancellation/refund automation with global + category/product override policy.
* Booking-aware due date calculation (X days before booking start).
* Auto-cancel overdue balance orders (scheduled) and manual bulk cancel action.
* WP-CLI command: `wp wbdpp cancel-overdue` (supports `--days`, `--limit`, `--dry-run`).
* HPOS compatibility declaration.
* Merchant onboarding guide: see `docs/QUICK-START.md` inside the plugin folder.

== WP-CLI ==

Use the command below to cancel overdue balance orders:

`wp wbdpp cancel-overdue`

Examples:

`wp wbdpp cancel-overdue --days=10 --limit=200`

`wp wbdpp cancel-overdue --dry-run`

== Developer Hooks ==

Filters:

* `wbdpp_resolved_refund_policy`
* `wbdpp_refund_amount_on_cancellation`
* `wbdpp_cancel_balance_on_parent_cancel`
* `wbdpp_overdue_days_threshold`
* `wbdpp_overdue_balance_query_args`
* `wbdpp_overdue_cancel_reason`
* `wbdpp_admin_cancel_email_recipient`
* `wbdpp_admin_cancel_email_subject`
* `wbdpp_admin_cancel_email_body`

Actions:

* `wbdpp_deposit_refund_created`
* `wbdpp_deposit_refund_failed`
* `wbdpp_overdue_orders_cancelled`

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate **WB Deposits & Partial Payments for WooCommerce**.
3. Configure settings under **WooCommerce > Settings > WB Deposits**.

== Changelog ==

= 1.0.1 =
* Fixed WooCommerce settings-page registration guard to prevent class-load fatals when other tabs render.
* Improved premium admin UX with contextual guidance cards across all settings sections.
* Added live reminder schedule chips and deposit message preview in settings.
* Polished settings visual hierarchy and mobile behavior.

= 1.0.0 =
* Initial release.
