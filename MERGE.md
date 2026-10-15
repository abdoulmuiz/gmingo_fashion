# What to replace when merging

Replace these three pieces with the main application's versions. Leave the rest of this module as it is.

## The three functions in app/core/auth.php

No other fashion file reads the session. Replace only:

- `current_user_id(): ?string` — the signed-in user's id, or null
- `current_tenant_id(): ?string` — that user's business id, or null
- `require_login(): void` — send a logged-out person to the login screen

## The customers table

Replace `fashion_customers`. This module reads and writes these columns:

- `id` CHAR(36)
- `tenant_id` CHAR(36)
- `name` VARCHAR(190)
- `phone` VARCHAR(40)
- `gender` VARCHAR(20)
- `customer_type` VARCHAR(20)
- `messages_opt_in` TINYINT
- `created_at` DATETIME

Spent and owed are calculated from orders and payments. They are not stored on the customer.

## The payments table

Replace `fashion_payments`. Money is whole kobo in `amount_kobo` (BIGINT). Rows are append-only: a mistake is a new row with a negative `amount_kobo`, `kind` of `reversal`, and `reverses_payment_id` pointing at the original row. Do not update or delete payment rows.

- `id` CHAR(36)
- `tenant_id` CHAR(36)
- `order_id` CHAR(36)
- `amount_kobo` BIGINT
- `method` VARCHAR(20)
- `reverses_payment_id` CHAR(36), null when this row is not a reversal
- `kind` VARCHAR(20), `payment` or `reversal`
- `user_id` CHAR(36)
- `created_at` DATETIME
- `seq` BIGINT UNSIGNED, auto-increment
