#!/bin/bash
cd "$(dirname "$0")" || exit 1

fail() {
    echo "$1"
    read -r -p "Press Return to close."
    exit 1
}

stack=""
php_bin=""
mysqladmin=""

if [ -d /Applications/MAMP ]; then
    stack="mamp"
elif [ -d /Applications/XAMPP ]; then
    stack="xampp"
else
    fail "Could not find MAMP or XAMPP in /Applications. Install one of them, start MySQL, and run this again."
fi

if [ "$stack" = "mamp" ]; then
    php_bin=$(find /Applications/MAMP/bin/php -type f -path '*/bin/php' 2>/dev/null | sort | tail -n 1)
    if [ -x /Applications/MAMP/Library/bin/mysqladmin ]; then
        mysqladmin="/Applications/MAMP/Library/bin/mysqladmin"
    fi
else
    for candidate in /Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/bin/php; do
        if [ -x "$candidate" ]; then
            php_bin="$candidate"
            break
        fi
    done
    for candidate in /Applications/XAMPP/xamppfiles/bin/mysqladmin /Applications/XAMPP/bin/mysqladmin; do
        if [ -x "$candidate" ]; then
            mysqladmin="$candidate"
            break
        fi
    done
fi

if [ -z "$php_bin" ] || [ ! -x "$php_bin" ]; then
    fail "Could not find PHP. Install MAMP or XAMPP, then run this again."
fi

if ! "$php_bin" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);'; then
    fail "PHP is too old. Gmingo Fashion needs PHP 8.1 or newer."
fi

if [ -z "$mysqladmin" ]; then
    fail "Could not find MySQL. Start MySQL in MAMP or XAMPP and run this again."
fi

if [ "$stack" = "mamp" ]; then
    "$mysqladmin" --protocol=TCP -h 127.0.0.1 -P 8889 -u root -proot ping >/dev/null 2>&1 || fail "MySQL is not running. Open MAMP, start MySQL, and run this again."
else
    "$mysqladmin" --protocol=TCP -h 127.0.0.1 -P 3306 -u root ping >/dev/null 2>&1 || fail "MySQL is not running. Open XAMPP, start MySQL, and run this again."
fi

if [ ! -f config/config.php ]; then
    fail "config.php is missing. Run setup-mac.command first."
fi

if ! "$php_bin" tools/migrate.php; then
    fail "Could not update the database. Check that MySQL is running and that config/config.php matches your MAMP or XAMPP password."
fi

echo "Database update finished."
