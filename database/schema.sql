-- ============================================================
-- FinSight MVP — PostgreSQL Schema
-- Trimmed down from the full product spec to what the MVP needs:
--   users, budgets (one active budget per user), subcategories
--   (presets only), transactions, streaks.
-- No Clerk/Stripe/OpenAI/Redis references — plain PHP + Postgres.
-- ============================================================

-- Clean re-runs during development. Remove in production.
DROP TABLE IF EXISTS transactions CASCADE;
DROP TABLE IF EXISTS streaks CASCADE;
DROP TABLE IF EXISTS subcategories CASCADE;
DROP TABLE IF EXISTS budgets CASCADE;
DROP TABLE IF EXISTS users CASCADE;

DROP TYPE IF EXISTS budget_pillar;
DROP TYPE IF EXISTS budget_period;

-- ------------------------------------------------------------
-- Enums
-- ------------------------------------------------------------
CREATE TYPE budget_pillar AS ENUM ('NEEDS', 'WANTS', 'SAVINGS');
CREATE TYPE budget_period AS ENUM ('MONTHLY', 'YEARLY');

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE users (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- budgets
-- One row per user for the MVP (no multi-period history).
-- Updating a budget overwrites this row.
-- ------------------------------------------------------------
CREATE TABLE budgets (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    period_type     budget_period NOT NULL DEFAULT 'MONTHLY',
    total_amount    NUMERIC(12, 2) NOT NULL CHECK (total_amount > 0),
    needs_pct       SMALLINT NOT NULL CHECK (needs_pct BETWEEN 0 AND 100),
    wants_pct       SMALLINT NOT NULL CHECK (wants_pct BETWEEN 0 AND 100),
    savings_pct     SMALLINT NOT NULL CHECK (savings_pct BETWEEN 0 AND 100),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT budget_pct_sum CHECK (needs_pct + wants_pct + savings_pct = 100)
);

-- ------------------------------------------------------------
-- subcategories
-- MVP ships preset (system-owned) sub-categories only.
-- user_id is kept nullable so the table can support custom,
-- user-defined sub-categories later without a migration.
-- ------------------------------------------------------------
CREATE TABLE subcategories (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    pillar      budget_pillar NOT NULL,
    label       VARCHAR(255) NOT NULL,
    is_custom   BOOLEAN NOT NULL DEFAULT false,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_subcategories_pillar ON subcategories(pillar);

-- ------------------------------------------------------------
-- transactions
-- ------------------------------------------------------------
CREATE TABLE transactions (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount          NUMERIC(12, 2) NOT NULL CHECK (amount > 0),
    pillar          budget_pillar NOT NULL,
    subcategory_id  INTEGER REFERENCES subcategories(id) ON DELETE SET NULL,
    note            VARCHAR(500),
    payee           VARCHAR(255),
    txn_date        DATE NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Supports "monthly total per pillar" and "paginated list" queries.
CREATE INDEX idx_transactions_user_date ON transactions(user_id, txn_date DESC);
CREATE INDEX idx_transactions_user_pillar ON transactions(user_id, pillar);

-- ------------------------------------------------------------
-- streaks
-- One row per user. Updated whenever a transaction is logged.
-- ------------------------------------------------------------
CREATE TABLE streaks (
    id                  SERIAL PRIMARY KEY,
    user_id             INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    current_streak      INTEGER NOT NULL DEFAULT 0,
    longest_streak      INTEGER NOT NULL DEFAULT 0,
    last_logged_date    DATE,
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- Seed data — preset sub-categories (curated for Indian spending
-- patterns, trimmed from the full PRD list to keep the MVP lean).
-- ------------------------------------------------------------
INSERT INTO subcategories (pillar, label, is_custom) VALUES
    ('NEEDS', 'Rent / EMI', false),
    ('NEEDS', 'Groceries', false),
    ('NEEDS', 'Utilities', false),
    ('NEEDS', 'Healthcare', false),
    ('NEEDS', 'Transport / Fuel', false),
    ('NEEDS', 'Internet & Mobile', false),
    ('WANTS', 'Dining Out', false),
    ('WANTS', 'Entertainment', false),
    ('WANTS', 'Shopping', false),
    ('WANTS', 'Travel', false),
    ('WANTS', 'Personal Care', false),
    ('SAVINGS', 'Emergency Fund', false),
    ('SAVINGS', 'Mutual Funds / SIP', false),
    ('SAVINGS', 'Fixed Deposit', false),
    ('SAVINGS', 'Insurance Premium', false);
