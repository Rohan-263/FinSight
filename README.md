# FinSight — Personal Finance Tracker (MVP)

A simple web app to track spending across three budget categories —
**Needs**, **Wants**, and **Savings** — with a daily logging streak.

Stack: HTML/CSS/vanilla JS frontend, PHP (PDO) backend, PostgreSQL database,
PHP sessions for auth, `password_hash()` for passwords, REST + JSON API.

## Features

- Sign up / log in / log out (secure sessions, hashed passwords)
- Set a monthly budget split across Needs / Wants / Savings (must total 100%)
- Log, edit, and delete transactions (amount, category, date, note, payee)
- Dashboard: spend vs. budget per category with a green/amber/red status
  and a bar chart
- Daily logging streak (current + longest)
- Transaction list with filtering by category/date and pagination

## Setup

1. **Create the database:**
   ```bash
   createdb finsight
   psql -d finsight -f database/schema.sql
   ```

2. **Set environment variables** (or edit the defaults in `includes/db.php`):
   ```
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_NAME=finsight
   DB_USER=postgres
   DB_PASSWORD=postgres
   COOKIE_SECURE=false   # set true only when serving over HTTPS
   ```

3. **Run it** (quick local test with PHP's built-in server):
   ```bash
   cd public
   DB_HOST=127.0.0.1 DB_NAME=finsight DB_USER=postgres DB_PASSWORD=postgres \
   COOKIE_SECURE=false php -S localhost:8000
   ```
   Open `http://localhost:8000/index.html`.

   For Apache/nginx, point the document root at `public/` — `includes/`
   and `database/` should stay outside the web root.

## Project structure

```
finsight/
├── database/schema.sql      # Tables + seed data (preset categories)
├── includes/                 # PHP helpers (kept outside the web root)
│   ├── db.php                  # Database connection
│   ├── session.php             # Session/auth helpers
│   ├── helpers.php             # JSON response + validation helpers
│   └── streaks.php             # Streak logic
└── public/                   # Web root
    ├── index.html               # Login / register
    ├── app.html                  # Dashboard, transactions, budget
    ├── css/style.css
    ├── js/                       # api.js, app.js, auth.js, dashboard.js, transactions.js, budget.js
    └── api/                      # PHP REST endpoints (JSON)
```

## API endpoints

| Endpoint | Method(s) | Purpose |
|---|---|---|
| `api/register.php` | POST | Create account |
| `api/login.php` | POST | Log in |
| `api/logout.php` | POST | Log out |
| `api/me.php` | GET | Current session/user |
| `api/budget.php` | GET, POST | Read / set budget |
| `api/subcategories.php` | GET | List preset categories |
| `api/transactions.php` | GET, POST, PUT, DELETE | List/create/update/delete |
| `api/dashboard.php` | GET | Monthly summary + streak |

Tested end-to-end: registration/login, budget validation, transaction
CRUD, dashboard math, and streak tracking all verified against a live
PostgreSQL + PHP server.
