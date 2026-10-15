@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "PHP="
if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP (
    where php >nul 2>&1
    if not errorlevel 1 set "PHP=php"
)
if not defined PHP (
    echo Could not find PHP. Install XAMPP, then run this file again.
    pause
    exit /b 1
)

"%PHP%" -r "exit(PHP_VERSION_ID >= 80100 ? 0 : 1);"
if errorlevel 1 (
    echo PHP is too old. Gmingo Fashion needs PHP 8.1 or newer.
    pause
    exit /b 1
)

set "MYSQLADMIN="
if exist "C:\xampp\mysql\bin\mysqladmin.exe" set "MYSQLADMIN=C:\xampp\mysql\bin\mysqladmin.exe"
if not defined MYSQLADMIN (
    echo Could not find MySQL. Start MySQL from the XAMPP control panel, then run this file again.
    pause
    exit /b 1
)
"%MYSQLADMIN%" --protocol=TCP -h 127.0.0.1 -P 3306 -u root ping >nul 2>&1
if errorlevel 1 (
    echo MySQL is not running. Open XAMPP, start MySQL, then run this file again.
    pause
    exit /b 1
)

if not exist "config\config.php" (
    copy /Y "config\config.example.php" "config\config.php" >nul
    if errorlevel 1 (
        echo Could not copy config\config.example.php to config\config.php.
        pause
        exit /b 1
    )
    echo Wrote config.php for XAMPP.
) else (
    echo config.php is already here, so it was left as it is.
)

"%PHP%" tools/migrate.php
if errorlevel 1 (
    echo Could not update the database. Check that MySQL is running and that config\config.php has the right password.
    pause
    exit /b 1
)

"%PHP%" tools/seed.php
if errorlevel 1 (
    echo Could not load the demo shop.
    pause
    exit /b 1
)

echo Opening the shop in your browser.
start "" "http://localhost/gmingo_fashion/"
exit /b 0
