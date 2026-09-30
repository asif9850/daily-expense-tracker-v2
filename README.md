# Daily Expense Tracker V2

A modern, responsive, full-featured web application for personal expense tracking, upcoming bills management, recurring payment rules, monthly budgeting, and analytics.

---

## 1. System Requirements & Technology Stack

- **Server / Environment**: XAMPP (Apache + MySQL / MariaDB + PHP 8.0+)
- **Core Backend**: PHP with MySQLi (Prepared Statements)
- **Frontend**: Vanilla HTML5, CSS3 (Modular Design System + Full Dark/Light Theme), Vanilla JavaScript (No heavy frameworks)
- **Database**: MySQL with InnoDB engine and `utf8mb4_unicode_ci` collation

---

## 2. Directory Structure

```
daily-expense-tracker-v2/
├── index.php                # Entry point redirecting to dashboard or login
├── login.php                # Authentication: user login
├── register.php             # Authentication: user registration
├── logout.php               # Authentication: clean session termination
├── dashboard.php            # Primary dashboard: KPIs, recent activity, quick-add modal
│
├── analytics/               # Visual charts, monthly breakdowns & category analytics
│   └── index.php
│
├── budget/                  # Monthly budget setting, monitoring & threshold alerts
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   └── delete.php
│
├── expenses/                # Manual expense logging with category & date filtering
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   └── delete.php
│
├── upcoming/                # One-time upcoming bills & invoices (Mark as Paid workflow)
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   ├── delete.php
│   └── mark_paid.php
│
├── recurring/               # Recurring rules (Daily, Weekly, Monthly, Yearly) with auto-cycle
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   ├── delete.php
│   ├── pause.php
│   ├── resume.php
│   └── generate.php
│
├── notifications/           # Notification Center with filter tabs, read/unread, and dismiss
│   ├── index.php
│   ├── read.php
│   └── dismiss.php
│
├── profile/                 # Profile details, password changes & preferences
│   ├── index.php
│   ├── edit.php
│   ├── change_password.php
│   ├── preferences.php
│   └── ajax_theme.php
│
├── includes/                # Shared layout, authentication, database & business logic
│   ├── auth.php
│   ├── db.php
│   ├── functions.php
│   ├── header.php
│   ├── navbar.php
│   └── footer.php
│
├── database/                # Schema definitions & SQL migration backups
│   ├── schema.sql
│   └── ...
│
├── css/                     # Modular responsive CSS stylesheets & theme variables
└── js/                      # Clientside interactivity, theme synchronization & charts
```

---

## 3. Installation & Setup Instructions

1. **Install XAMPP**:
   - Ensure Apache and MySQL modules are running in the XAMPP Control Panel.
2. **Clone / Place Project**:
   - Place this project directory under `C:\xampp\htdocs\daily-expense-tracker-v2`.
3. **Database Import**:
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin/`).
   - Run the script in `database/schema.sql` (or import the file directly).
   - This automatically creates the database `expense_tracker_v2` and all required tables idempotently:
     - `users`
     - `budgets`
     - `expenses`
     - `upcoming_expenses`
     - `recurring_expenses`
     - `user_preferences`
     - `notifications`
4. **Database Configuration**:
   - Connection credentials reside in [includes/db.php](includes/db.php):
     ```php
     $host = "localhost";
     $username = "root";
     $password = "";
     $database = "expense_tracker_v2";
     ```
5. **Access Application**:
   - Open your browser and navigate to:
     `http://localhost/daily-expense-tracker-v2/`

> ### Database Schema Note
> The `schema.sql` file uses `CREATE TABLE IF NOT EXISTS` to prevent errors when tables already exist.
>
> However, `IF NOT EXISTS` does not modify an existing table structure. If the database schema changes in a future version, the required SQL migration must be executed separately.

---

## 4. Key Business Logic & Features

### Budget Alerts (80%, 100%, and Exceeded)
- Real-time spend tracking compares total logged expenses in the active month against the user's defined budget limit.
- **Threshold Crossing**:
  - **80% Warning**: Triggers when usage reaches or crosses 80%.
  - **100% Limit**: Triggers when usage reaches or crosses 100%.
  - **Budget Exceeded**: Triggers when usage exceeds 100% with the exact overage amount.
  - Intermediate jumps (e.g. 75% &rarr; 85%, or 95% &rarr; 110%) evaluate all applicable thresholds without skipping.
- **Duplicate Prevention**: Unique composite key `(user_id, notification_key)` prevents repeated notifications on refreshes.

### Upcoming & Recurring Reminders
- **Configurable Lead Window**: Reminders honor user-selected lead times (1, 2, or 3 days in advance).
- **Separated Timing Categories**:
  - *Advance Reminder*: Created within the lead window (`_advance` key).
  - *Due Today*: Created on the exact due date (`_today` key), superseding older advance alerts.
  - *Overdue*: Created for past-due unpaid items (`_overdue` key), superseding older alerts.
- **State Cleanliness**:
  - Marking upcoming bills as paid automatically dismisses active alerts and prevents new ones.
  - Pausing recurring rules dismisses active alerts and prevents reminder generation until resumed.
  - Generating an expense from a recurring rule advances `next_date` and dismisses cycle alerts.

### Theme Synchronization (Zero FOUC)
- Database preference is authoritative for logged-in users.
- Synchronized across `$_SESSION['theme']`, `localStorage`, and cookies.
- Server-side `<html data-theme="...">` rendering and inline execution eliminate flash of incorrect theme (FOUC) across navigation and re-login.

### Security
- Comprehensive CSRF token verification across all POST mutations.
- Strict prepared statements with parameter binding on all database interactions.
- User ownership isolation (`WHERE id = ? AND user_id = ?`) preventing horizontal privilege escalation on all read/write endpoints.
- No exposure of raw database errors or stack traces to end-users.

---

## 5. Verification & Testing Status

All 9 steps in the V2 Testing and Implementation Plan have been validated against live database operations:
- **Budget Alerts**: 50%, 80%, 100%, 120% scenarios and repeated sync duplicate checks passed (5/5).
- **Upcoming Reminders**: Lead window, due-today, overdue, paid status, and duplicate checks passed (5/5).
- **Recurring Reminders**: Upcoming due date, pause, resume, expense generation, and duplicate protection passed (5/5).
- **Theme & Notification Center**: Defaults, persistence, badge counts, mark read, dismiss, empty state, and cross-user isolation passed (9/9).
- **Database Compatibility**: Fresh import of `database/schema.sql` verified with `IF NOT EXISTS` idempotency.
- **Core Modules Regression**: Auth, Expenses CRUD, Upcoming CRUD/mark-paid, Recurring CRUD/generate, Budget CRUD, Analytics, and Profile passed (24/24).

To update an existing database, run the required SQL migrations separately, as `IF NOT EXISTS` does not modify existing table structures. For SQL error handling, use the `check_sql_errors()` function, which logs errors internally without exposing them on the page (logging is enabled by default in production mode).

---

## 6. Version & Release Information (V2.0 Final)

- **Version**: `2.0.0 (Final Release)`
- **Release Date**: September 2026
- **Key Enhancements Completed in Phase 2**:
  1. **Code Polishing & Cleanup**: Reorganized business logic with central utilities (`check_sql_errors`, `format_currency`), eliminated dead code and redundant selector rules.
  2. **UI/UX Polish**: Standardized color palette, shadows, borders, high-contrast action button states, and theme variables across light and dark modes.
  3. **Mobile Responsiveness**: Responsive 5-card dashboard grid, adaptive category breakdowns, mobile navigation drawer, and fluid touch-friendly form controls.
  4. **Dashboard Improvements**: Added complete financial snapshot with Active Recurring commitments, Upcoming Bills summary, and an intuitive Budget Health Progress & Alert Bar.
  5. **User Experience**: Clear flash message feedback upon add, edit, delete, mark paid, pause, resume, and generate actions across all modules.
  6. **Security & Production Readiness**: 100% prepared statements verified across all queries, strict CSRF token validation, user isolation, and internal SQL error logging.
  7. **Release Artifacts**: Fully idempotent database schema (`database/schema.sql`) and complete production export (`database/backup_expense_tracker_v2_v2.0_final_release.sql`).