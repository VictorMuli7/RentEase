# RentEase: House Rental Management System

- `rentease.html`: clickable prototype of the Tenant, Manager, Landlord and Admin portals (open in any browser; data lives in memory only). The sign-in page has a role selector, and tenants can create an account.
- `rentease_db.sql`: MySQL 8 schema (`rentease_db`) from Deliverable 2.6. Import with `mysql -u root -p < rentease_db.sql`.

Sign-in and signup are prototypes only: role selection is not verified, passwords are not stored, and accounts reset when the page reloads. Do not use for real authentication. Not yet built: back end, real authentication, live M-Pesa Daraja integration, file uploads.
