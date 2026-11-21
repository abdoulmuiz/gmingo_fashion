# Demo accounts

These logins are for a local demo only. Shop owner/staff passwords below are `password`.

## Platform admin

- Console: `/platform`
- Email: from `platform_admin_email` in `config/config.php` (example seed uses `platform@gmingo.test`)
- Password: must be a strong value in `platform_admin_password` (12+ characters; never the old default `password`). First login forces a password change.
- Manages plans and shop subscriptions (suspend / reactivate)

## Tailor shop

- Business: Kano Stitches Tailoring
- Company type: tailor
- Owner: demo@gmingo.test
- Staff: tailor-staff@gmingo.test
- Modules on: orders, production, finance, measurements, communication
- Modules off: inventory, sales, staff, online payments

## Garment factory

- Business: Demo Factory
- Company type: factory
- Owner: factory@gmingo.test
- Staff: factory-staff@gmingo.test
- Modules on: production, finance, inventory, staff
- Modules off: orders, measurements, communication, sales, online payments

## Fashion brand

- Business: Demo Brand
- Company type: brand
- Owner: brand@gmingo.test
- Staff: brand-staff@gmingo.test
- Modules on: orders, finance, communication, inventory, sales
- Modules off: production, measurements, staff, online payments
