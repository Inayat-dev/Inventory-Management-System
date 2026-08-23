# 📦 Inventory Management System

A web-based Inventory Management System for tracking raw materials, production, and sales — built for **Rifat Enterprise**. It provides role-based access to manage the full flow from raw material purchases to finished-product sales, with reporting and supplier tracking along the way.

---

## ✨ Features

- **Dashboard** – At-a-glance overview of inventory and business activity
- **Production Management** – Track production entries and finished production items
- **Raw Material Inventory** – Manage raw materials and material purchases from suppliers
- **Sales Management** – Record customer sales and track quantities/amounts
- **Suppliers** – Maintain a directory of suppliers used for material purchases
- **Reports / Analysis** – View analytics across production, inventory, and sales
- **Settings** – Manage application/account settings
- **Role-Based Access Control** – Two built-in roles:
  - **Super Admin** – Full access to every module
  - **Product Manager** – Restricted access (Dashboard + Production Management by default), configurable via permissions stored per role

---

## 🛠️ Tech Stack

| Layer     | Technology                     |
|-----------|---------------------------------|
| Frontend  | HTML, CSS, JavaScript           |
| Backend   | PHP (procedural, `mysqli`)      |
| Database  | MySQL / MariaDB                 |

---

## 📁 Project Structure

```
Inventory-Management-System/
├── assets/images/              # Static image assets
├── dashboard/
│   ├── dashboard.php           # Main dashboard view
│   ├── production_management.php
│   ├── raw_material_management.php
│   ├── sales_management.php
│   ├── suppliers.php
│   ├── reports.php
│   ├── settings.php
│   ├── profile.php
│   ├── sidebar.php             # Role-aware navigation menu
│   ├── access.php              # Access control checks
│   ├── login_check.php         # Session/login guard
│   ├── update_entry.php
│   ├── css/                    # Dashboard styles
│   └── js/                     # Dashboard scripts
├── documents/                  # Project documentation & reports
├── config.php                  # Database connection settings
├── index.php                   # Entry point / login page
├── login.php                   # Login handler
├── rifat_db.sql                # Database schema + seed data
└── start.bat                   # Windows script to start a local PHP dev server
```

---

## 🗄️ Database Schema

The system uses a MySQL database (`rifat_db`) with the following core tables:

- `users` — Application accounts, linked to a role
- `roles` — Role definitions with a `permissions` JSON array
- `raw_material` — Raw material inventory records
- `material_perchases` — Raw material purchase records (linked to suppliers)
- `suppliers` — Supplier directory
- `production_entries` / `production_item` — Production tracking
- `sales` — Customer sales records

---

## 🚀 Getting Started

### Prerequisites

- PHP 7.4+ with the `mysqli` extension
- MySQL or MariaDB
- A local server stack such as **XAMPP** or **WAMP** (recommended), or the PHP built-in server

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Inayat-dev/Inventory-Management-System.git
   ```

2. **Create the database**
   - Create a MySQL database named `rifat_db`
   - Import the schema and seed data:
     ```bash
     mysql -u root -p rifat_db < rifat_db.sql
     ```

3. **Configure the database connection**
   - Edit `config.php` if your MySQL credentials differ from the defaults:
     ```php
     $host = "localhost";
     $username = "root";
     $password = "";
     $db = "rifat_db";
     ```

4. **Start the application**
   - **Windows:** double-click `start.bat`, or run it from a terminal:
     ```bash
     start.bat
     ```
     This starts a local PHP server and generates a QR code so you can also open the app from a phone on the same network.
   - **Manual (any OS):** from the project root, run:
     ```bash
     php -S localhost:9000
     ```
   - Or place the project in your XAMPP/WAMP `htdocs`/`www` folder and start Apache + MySQL from the control panel.

5. **Open the app** at `http://localhost:9000` (or the URL/port your setup uses) and log in.

---

## 🔐 Demo Login Credentials

Seeded accounts from `rifat_db.sql`:

| Role            | Email                     | Password  |
|-----------------|----------------------------|-----------|
| Super Admin     | altafnaya55@gmail.com      | altaf@55  |
| Product Manager | inayat@gmail.com           | inayat    |

> ⚠️ These are for local testing only. Change or remove them before deploying to production, and switch to hashed password storage (the current schema stores plain-text passwords).

---

## ⚠️ Notes

- Passwords are currently stored and compared in plain text — do not use this setup as-is in a production/public-facing environment without adding password hashing (`password_hash`/`password_verify`) and other hardening.
- `start.bat` is Windows-specific; use the manual PHP server command on macOS/Linux.
- Ensure the `mysqli` PHP extension is enabled in your `php.ini`.

---

## 📌 Possible Future Improvements

- Hashed password storage and stronger authentication
- Expanded reporting/analytics and data export (CSV/PDF)
- API integration for external systems
- Improved UI/UX and mobile responsiveness

---

## 👨‍💻 Author

Developed by **Inayat-dev** (Rifat Enterprise project)
