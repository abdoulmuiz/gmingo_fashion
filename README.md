# Gmingo Fashion

A shop book for one tailoring business. It runs on plain PHP 8.1 and MySQL. Put the folder where the web server can see it as `/gmingo_fashion`.

The demo shop is **Kano Stitches Tailoring**. Log in as `demo@gmingo.test` with the password `password`. Demo data loads only when `environment` in `config/config.php` is `local`.

Paystack stays off until `payment_placeholder_domain` in `config/config.php` is the site address, such as `https://shop.example.com`. Leave that setting empty until the address is known. Paystack mode cannot be switched on while it is empty. The Paystack API address is `paystack_base_url` and should stay `https://api.paystack.co`.

## Windows with XAMPP

1. Install XAMPP and start Apache and MySQL from the XAMPP control panel.
2. Copy this folder to `C:\xampp\htdocs\gmingo_fashion`.
3. Double-click `setup-windows.bat`.

The setup checks that PHP is 8.1 or newer and that MySQL is running. It copies `config/config.example.php` to `config/config.php` when that file is missing, creates the database, runs the migrations, loads the demo shop, and opens `http://localhost/gmingo_fashion/`. If a step fails, the window says what to fix.

XAMPP’s MySQL uses port **3306** and an empty root password. Those are already the values in the example config.

Later, double-click `update-windows.bat` to run new migrations only. It does not load the demo shop again and it does not replace shop data.

## Mac with MAMP

1. Install MAMP and start the servers.
2. Copy this folder into MAMP’s htdocs directory, still named `gmingo_fashion`.
3. Double-click `setup-mac.command`. If macOS blocks it, right-click the file and choose Open.

MAMP’s MySQL listens on port **8889** and the root password is **root**. The Apache site is usually `http://localhost:8888/gmingo_fashion/`. The setup script detects `/Applications/MAMP` and writes those database settings into `config/config.php` when it creates that file.

## Mac with XAMPP

The same `setup-mac.command` detects `/Applications/XAMPP`. XAMPP on a Mac uses port **3306** and an empty root password, and the site opens at `http://localhost/gmingo_fashion/`.

On a Mac, `update-mac.command` runs migrations only.

## Tests

```
php tests/run.php
```

Tests use the database named in `test_db`, and they refuse to run unless that name ends in `_test`.
