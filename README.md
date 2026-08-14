# Caffe BMS — Business Management System (Blueprint)

## Overview

Caffe BMS is a modular, enterprise-ready Business Management System (BMS) tailored for cafés, restaurants, and similar F&B businesses. It provides a unified platform for order management, payments, menu management, reporting, and staff operations. The architecture is built to be deployable standalone or integrated as a plugin into larger systems.

## Goals

- Provide a secure, scalable, and modular platform for cafe operations.
- Support online and in-person ordering with clear staff workflows.
- Offer robust financial tracking and reporting for management.
- Enable easy customization of menus and items across deployments.

## Scope

This blueprint covers: product goals, user roles and permissions, key features, high-level architecture, deployment notes, core data model, API/integration considerations, security requirements, developer setup, and a suggested roadmap.

## Roles & Permissions

- Customer: Browse public menu, place orders, optionally pay online.
- Barista / Waitstaff: Receive orders, update order status, mark items prepared.
- Cashier: Accept payments, reconcile transactions, issue receipts.
- Manager: Manage menus, prices, inventory (optional), view/export reports, manage users and permissions.

Note: In small setups, the Waitstaff and Cashier roles can be combined.

## Key Features

- Public Virtual Menu: Browsable without authentication; images and descriptions supported.
- Order Management: Create, update, and track orders (queued, preparing, ready, completed).
- Payments: Integrate with payment gateway(s); record and reconcile payments.
- Financial Ledger: Immutable transaction log for audits and reporting.
- Reporting & Exports: Daily/weekly/monthly financial and sales reports (CSV, PDF).
- Modular Menu Management: Create menus, categories, items, variants, and pricing.
- Role-Based Access Control (RBAC): Permission system for staff.

## High-level Architecture

- Frontend: Static PHP/JS pages for public menu and staff dashboards.
- Backend: PHP application (existing structure under `private/` and `public/`) handling business logic.
- Database: Relational DB (MySQL/MariaDB) for core data and ledger.
- Reverse Proxy / CDN: Nginx for TLS termination, caching, and security headers (see `deployment/docker/nginx`).
- Containerization: Docker / docker-compose for consistent deployments.

Example deployment components (high level):

- `web` (PHP-FPM + app)
- `nginx` (reverse proxy)
- `db` (MySQL/MariaDB)
- `queue` (optional - worker for background jobs)

## Deployment & Infrastructure Notes

- Use `docker-compose.yaml` for local and small-scale deployments.
- Separate production concerns: TLS, firewall rules, database backups, and monitoring.
- Run the app behind a secure reverse proxy and enable strict CSP and other security headers in Nginx.

## Core Data Model (Overview)

Primary tables and purpose:

- `users` — staff accounts, roles, authentication metadata.
- `menu` — items, categories, images, descriptions, price, availability.
- `orders` — order header, customer details (optional), status, timestamps.
- `order_items` — line items per order, quantity, unit price, modifiers.
- `payments` — payment records, gateway reference, amount, status.
- `financial_ledger` — immutable ledger entries for every financial event (sales, refunds, expenses).
- `expenses` — operational expenses for reconciliation and reporting.

Schema notes:

- Keep `financial_ledger` append-only. Link ledger entries to source entities (order_id, payment_id, expense_id).
- Use foreign keys where appropriate and indexed columns for common queries (status, created_at, user_id).

## Data Flow (Order -> Payment -> Ledger)

1. Customer places order (public or via staff).
2. Order created in `orders` with `order_items`.
3. Payment is processed (if applicable) and stored in `payments`.
4. Transactional ledger entries are appended to `financial_ledger`.
5. Manager uses reporting tools to export aggregated results.

## API & Integration (Guidelines)

- Expose authenticated internal API endpoints for staff dashboards (e.g., `/api/orders`, `/api/menu`, `/api/payments`).
- Public read-only endpoints for menu and availability (cache aggressively).
- Webhook support for payment gateway events and asynchronous notifications.

Suggested endpoints (RESTful):

- `GET /menu` — public menu list
- `POST /orders` — create order
- `GET /orders/{id}` — order details
- `POST /payments` — initiate payment
- `GET /reports/sales` — manager-only sales reports

## Security & Compliance

- Authentication: Strong password hashing, session management, and optional 2FA for managers.
- Authorization: Role-based access control (RBAC) for staff actions.
- Transport: Enforce HTTPS and HSTS.
- Input validation and prepared statements to prevent SQL injection.
- CSP, X-Frame-Options, X-Content-Type-Options set via Nginx.
- Rate limiting and brute-force protection on staff login endpoints.
- Logging: Audit logs for critical actions and financial events.

## Operational Considerations

- Backups: Regular automated DB backups and verification.
- Monitoring: Basic metrics (uptime, DB connections, queue backlog) and alerts.
- Scaling: Separate read replicas and stateless app servers for higher load.

## Developer Setup

1. Copy `.env.example` to `.env` and configure DB and gateway credentials.
2. Start services locally with Docker Compose:

```bash
docker-compose up --build
```

3. Run DB migrations or import `deployment/caffe.sql` for an initial schema.

## Roadmap (Suggested)

- V1: Stable order/payment flow, menu management, basic reports.
- V2: Inventory tracking, multicurrency support, multi-branch support.
- V3: Third-party integrations, advanced analytics, mobile-optimized staff apps.

