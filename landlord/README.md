# Landlord module

Pages for the landlord (and the maintenance and vendor pages the property manager shares).
Open them at `/landlord/` after signing in.

| File | What it does |
|---|---|
| index.php | Occupancy dashboard |
| tenants.php | Read-only tenant list with search |
| rent.php | Rent collected for any month, who has not paid |
| expenses.php | Landlord approves or rejects repairs of KES 20,000 and above |
| maintenance.php | Track repair jobs (landlord views, manager edits: vendor, cost, status) |
| vendors.php | Add, edit and remove vendors |
| reports.php | Five reports, view, CSV download or print to PDF |
| queries.php | All SQL for the module |
| layout.php | Shared header, menu and styles |
| seed_demo.php | Sample data for testing (command line only) |

## Try it

1. Follow the main README (XAMPP, import `rentease_db.sql`, run `reset_manager.php`).
2. Add sample data: `php landlord/seed_demo.php`
3. Start the server: `php -S 127.0.0.1:8000`
4. Sign in as `landlord@rentease.local` (password printed by `reset_manager.php`). It opens `/landlord/index.php`.
   Sign in as `manager@rentease.local` to edit maintenance jobs.

## Changes outside this folder

- `app_helpers.php`: landlords now land on `landlord/index.php` after login.
- A new table `expense_approvals` is created automatically the first time a landlord page loads
  (the schema had nowhere to store the landlord's decision). It should be added to `rentease_db.sql` too.
