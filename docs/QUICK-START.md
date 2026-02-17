# WB Deposits Quick Start

## 1) Enable Core Settings
- Go to `WooCommerce > Settings > WB Deposits`.
- In `General`, enable deposits and set:
  - default deposit type/value
  - payment mode (optional or mandatory)
  - stock + shipping stage

## 2) Configure Due Dates & Reminders
- Open `Due Dates`.
- Choose due type:
  - `Relative days` (recommended)
  - or `Fixed date`
- Set reminder offsets (example: `7,3,1,0`).

## 3) Configure Financial Rules
- Open `Tax & Discount Rules`.
- Set:
  - tax mode (`full_upfront` or `split`)
  - coupon split mode (`full`, `deposit`, `proportional`)

## 4) Configure Cancellation Policy
- Open `Cancellation & Refunds`.
- Choose:
  - whether to auto-cancel linked balance order on parent cancellation/refund
  - deposit refund policy (`none`, `full`, `partial`)
  - overdue auto-cancel window

## 5) Product/Category Overrides
- Product-level: edit product in WooCommerce and use WB Deposits fields in pricing section.
- Category-level: edit product category and set WB Deposits term fields.
- Priority: `Product > Category > Global`.

## 6) Customer Flow
- Customer selects `Pay full` or `Pay deposit`.
- At checkout, deposit order is paid now.
- Plugin auto-creates linked balance order with due date and payment URL.
- Customer can pay balance from:
  - My Account order actions
  - reminder emails

## 7) Admin Operations
- Order edit page meta box:
  - view linked deposit/balance orders
  - edit due date
  - send reminder now
  - mark balance paid
- Report page:
  - `WooCommerce > WB Deposits Report`

## 8) Optional CLI
- Cancel overdue balances:
  - `wp wbdpp cancel-overdue`
  - `wp wbdpp cancel-overdue --days=10 --limit=200`
  - `wp wbdpp cancel-overdue --dry-run`
