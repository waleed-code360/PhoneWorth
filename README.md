# PhoneWorth

PhoneWorth estimates the resale price of a used smartphone using prices and
condition rules saved in a MySQL database. The site has a phone-checking flow
for visitors and an admin panel for managing the data.

## Built with

Ubuntu, Apache, MySQL, PHP (PDO), HTML, CSS and JavaScript. No PHP or
frontend framework is required.

## Folders

- `public/` - website pages, `admin/` pages, CSS and JavaScript
- `includes/` - shared PHP functions, SQL queries and price calculation
- `config/` - local database configuration (example file committed)
- `database/` - MySQL tables and initial sample records
- `scripts/` - first-admin setup and connection check for the terminal
- `tests/` - basic PHP/JavaScript syntax and calculation checks

## Setup (fresh database)

1. Install and start Apache, MySQL and PHP with the `pdo_mysql` extension.
2. Put this folder somewhere like `/var/www/phoneworth-app`.
3. Import the SQL files in order:

   ```bash
   cd /var/www/phoneworth-app
   sudo mysql < database/schema.sql
   sudo mysql phoneworth < database/demo_data.sql
   ```

4. Create a MySQL user for the application:

   ```sql
   CREATE USER 'phoneworth_user'@'localhost' IDENTIFIED BY 'YOUR_OWN_PASSWORD';
   GRANT SELECT, INSERT, UPDATE, DELETE ON phoneworth.* TO 'phoneworth_user'@'localhost';
   ```

5. Copy `config/database.example.php` to `config/database.php`. Set the local
   MySQL password and choose the email for the first admin account. This file
   must **not** be pushed to GitHub.
6. Create the first admin from the terminal:

   ```bash
   php scripts/create_first_admin.php
   php scripts/check_setup.php
   ```

7. Configure an Apache VirtualHost with `DocumentRoot` pointing to
   `/var/www/phoneworth-app/public`. Test the Apache config with
   `sudo apache2ctl configtest` before reloading Apache. Open the URL you
   configured; the admin login page is `/admin/login.php`.

**Existing installations:** Do not import demo SQL again into a database that
already has records. This repository uses a `public/` document root, so test
it separately before replacing an older `/var/www/html/phoneworth` setup.

## How it works

The visitor chooses a country, brand, model and storage option, then completes
Quick Check or Detailed Check. PHP reads a base resale price and applies the
deductions configured in the admin panel. Some conditions require physical
inspection instead of a price. Completed estimates are stored in MySQL.

Admins can manage brands, models, variants, countries, market prices, part
costs and condition rules. Only the configured owner can manage admin accounts.

**Note:** `demo_data.sql` contains example prices for testing, not verified
market prices.

## Checks

```bash
sh tests/check_source.sh
```

These checks cover source syntax and price calculation. Before submission,
also test phone selection, valuation, admin login, CRUD and history against
MySQL on your own Ubuntu installation.

## Project Demo

[Watch / Download the PhoneWorth Project Demo](demo-videos/PhoneWorth-Project-Demo.mp4)

## Project Demo

[Watch / Download the PhoneWorth Project Demo](demo-videos/PhoneWorth-Project-Demo.mp4)
