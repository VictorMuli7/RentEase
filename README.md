# RentEase: House Rental Management System

RentEase is a PHP/MySQL rental management app. It supports tenant registration and sign-in, property browsing and applications, manager application review and lease creation, maintenance requests, and recording rent payments. `rentease.html` remains a separate in-memory UI prototype.

## Run locally with XAMPP

The default local settings target the XAMPP MariaDB server on `127.0.0.1:3307` with the local `root` account and no password. This is for local development only; do not expose this database or use these settings in production.

1. Start XAMPP's MariaDB on port `3307`. If another MySQL service already occupies port `3306`, run:

   ```powershell
   & "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --port=3307 --bind-address=127.0.0.1 --console
   ```

2. Import the schema once:

   ```powershell
   & "C:\xampp\mysql\bin\mysql.exe" --plugin-dir="C:\xampp\mysql\lib\plugin" -h 127.0.0.1 -P 3307 -u root -e "source C:/path/to/RentEase-main/rentease_db.sql"
   ```

3. Create local demo staff accounts and two sample vacant units:

   ```powershell
   & "C:\xampp\php\php.exe" "C:\path\to\RentEase-main\reset_manager.php"
   ```

   The command prints randomly generated manager, landlord, and admin passwords. This CLI-only script resets those demo passwords each time it runs. Change them from each account's dashboard and do not run the script on production data.

4. Start the PHP server from the project directory:

   ```powershell
   & "C:\xampp\php\php.exe" -S 127.0.0.1:8000
   ```

5. Open <http://127.0.0.1:8000/>. Register a tenant account; use one of the generated demo accounts to access the staff portals.

The connection can be overridden with `RENTEASE_DB_HOST`, `RENTEASE_DB_PORT`, `RENTEASE_DB_USER`, `RENTEASE_DB_PASSWORD`, and `RENTEASE_DB_NAME`.

## Notes

- Tenant accounts are self-registered; staff roles are not selectable during public registration.
- Signed-in users can change their passwords from their dashboard.
- Approving an application creates a one-year lease and marks its unit occupied.
- The payments page records payments already received; live M-Pesa, card processing, and file uploads are not integrated.
- `reset_manager.php` is command-line-only and regenerates demo credentials every time it runs.
