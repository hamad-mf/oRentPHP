# oRent — Setup Guide

A fleet management system built with **PHP 8+ and MySQL**.  
Follow the steps below to get it running on any Windows PC.

---

## Requirements

| Software | Version | Download |
|---|---|---|
| XAMPP | Any recent | [xampp.apachefriends.org](https://www.apachefriends.org) |
| Web Browser | Any | — |

> XAMPP bundles **Apache** (web server) and **MySQL** (database) together. No other tools needed.

---

## Step 1 — Install XAMPP

1. Download and install XAMPP from the link above.
2. Open **XAMPP Control Panel** (run as Administrator).
3. Click **Start** next to **Apache** and **MySQL**.  
   Both rows should turn green.

---

## Step 2 — Copy the Project

Copy the entire `oRentPHP` folder into XAMPP's web root:

```
C:\xampp\htdocs\oRentPHP\
```

Your folder structure should look like this:

```
C:\xampp\htdocs\oRentPHP\
├── index.php
├── config\
│   └── db.php
├── vehicles\
├── clients\
├── reservations\
├── ...
└── SETUP.md  ← you are here
```

---

## Step 3 — Create the Database

1. Open your browser and go to: **http://localhost/phpmyadmin**
2. Click **New** in the left sidebar.
3. Name the database: `orent` → click **Create**.
4. Select the `orent` database → click the **Import** tab.
5. Click **Choose File** → select `database.sql` (inside this folder).
6. Click **Go** at the bottom.

---

## Step 4 — Configure the Database Connection

Open `config/db.php` in any text editor and verify the settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'orent');
define('DB_USER', 'root');
define('DB_PASS', '');   // default XAMPP has no password
```

If your MySQL has a password, enter it between the quotes on `DB_PASS`.

---

## Step 5 — Run the App

Open your browser and go to:

```
http://localhost/oRentPHP/
```

You should see the **oRent Dashboard**. 🎉

---

## Troubleshooting

| Problem | Solution |
|---|---|
| Apache or MySQL won't start | Run XAMPP Control Panel **as Administrator** |
| MySQL port conflict (3306) | In XAMPP → MySQL → Config → `my.ini`, change port to `3307` and update `DB_HOST` to `localhost:3307` in `config/db.php` |
| Blank page or 500 error | Enable PHP error display: in `php.ini` set `display_errors = On`, then restart Apache |
| "Access denied" database error | Check `DB_USER` and `DB_PASS` in `config/db.php` match your MySQL credentials |
| Page not found (404) | Make sure the folder is named exactly `oRentPHP` inside `htdocs` |

---

## Default Login

There is no login system — the app is designed for **internal/staff use** on a local or private network.

---

## What's Included

- **Dashboard** — Fleet status, daily operations, revenue overview
- **Vehicles** — Add, edit, view fleet with documents
- **Clients** — Client profiles, rental history, ratings, blacklist
- **Reservations** — Create, manage, deliver, and process returns with inspection photos
- **Stub modules** — Investments, GPS, Papers, Expenses, Challans, Staff (ready for future development)
