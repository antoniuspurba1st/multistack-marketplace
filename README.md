# Polyglot Microservices Marketplace

A portfolio marketplace project built to demonstrate a production-style polyglot architecture with one storefront, one API gateway, and several supporting services written in different stacks.

## Progress Update - March 13, 2026

Today's progress focused on turning the admin side from a placeholder into a usable catalog operations surface:

- `admin-vue-dashboard` was upgraded from the default Vue starter into a working admin UI for product operations.
- The admin panel can now list products, search catalog entries, create products, edit products, delete products, and upload product images.
- `admin-vue-dashboard/src/lib/adminApi.ts` was added as a dedicated API layer for talking to the Laravel gateway.
- `core-api-laravel` now exposes full product CRUD coverage for the admin workflow, including `show`, `update`, and `delete` routes.
- Laravel feature tests were expanded to cover product detail, update, and delete behavior.
- The new admin slice was verified with Laravel tests and a production Vue build.

This shifts the project from "multiple apps exist in the workspace" toward "multiple apps are beginning to support real operational workflows".

## Progress Update - March 12, 2026

Today's progress focused on expanding the repo from backend-heavy services into a more complete product ecosystem:

- `frontend-nextjs` now has a real storefront flow: homepage, product listing, product detail, cart, checkout success, and order history.
- `frontend-nextjs/lib/api.ts` already talks to the Laravel gateway for products, cart, checkout, and orders.
- `search-service-rust` was bootstrapped with `Axum` and exposes a simple `/search` endpoint on port `4000`.
- `seller-service-rails` was initialized as a dedicated Rails service for future seller-side capabilities.
- `admin-vue-dashboard` was added as the base for an admin panel in Vue 3 + Vite.
- `core-api-laravel` continues to own marketplace flows such as product management, cart, checkout, orders, stock protection, idempotency, and outbox processing.

This means the project is no longer just "Laravel + supporting demos". It is now moving into a multi-app marketplace workspace with dedicated surfaces for buyers, admins, sellers, and supporting services.

---

## Current Workspace

### User-facing apps
- `frontend-nextjs` - buyer storefront built with Next.js App Router

### Core platform
- `core-api-laravel` - API gateway and main marketplace backend
- `recommendation-ai-django` - recommendation service
- `auth-service-go` - authentication service
- `chat-service-node` - realtime chat service

### New apps/services added today
- `search-service-rust` - search service prototype
- `seller-service-rails` - seller service scaffold
- `admin-vue-dashboard` - admin dashboard for catalog operations

### Primary database
- `PostgreSQL`

---

## Architecture

```text
Buyer
  |
Next.js Storefront
  |
Laravel API Gateway
  |------------------------------\
  |                               \
Marketplace Logic                  \
  |                                 \
PostgreSQL                      Django Recommendation Service
                                Go Authentication Service
                                Node.js Chat Service
                                Rust Search Service (prototype)

Admin -> Vue Dashboard -> Laravel API Gateway
Seller -> Rails Seller Service (planned integration)
```

Laravel remains the central orchestrator for:

- product catalog
- cart operations
- checkout and order creation
- stock protection
- idempotent requests
- outbox event creation
- recommendation calls

---

## Implemented Marketplace Features

### Frontend storefront
- Homepage with featured products
- Product listing with live data from Laravel
- Product detail page
- Add-to-cart flow
- Cart summary page
- Checkout flow
- Checkout success page with recommendations
- Order history page

### Laravel backend
- Product CRUD foundations
- Search and pagination
- Product image upload
- Cart add/view/remove
- Transaction-safe checkout
- Atomic stock decrement
- `Idempotency-Key` support
- Outbox event persistence
- Order history API
- Recommendation service integration with caching

### Supporting services
- Django recommendation endpoint integrated into checkout
- Go auth service present in workspace
- Node chat service present in workspace
- Rust search service bootstrapped and runnable
- Rails seller service bootstrapped
- Vue admin dashboard connected for catalog management

---

## Backend Engineering Patterns Already Present

- request validation
- reduced transaction scope
- atomic stock protection
- idempotent checkout
- outbox pattern
- structured logging
- scheduled outbox processing
- recommendation caching

These patterns live primarily in `core-api-laravel` and make the project stronger than a basic CRUD demo.

---

## API Endpoints in Active Use

### Products

```text
GET    /api/products
GET    /api/products/{id}
POST   /api/products
PUT    /api/products/{id}
DELETE /api/products/{id}
POST   /api/product/upload
```

### Cart

```text
POST   /api/cart/add
GET    /api/cart/{user_id}
DELETE /api/cart/item/{id}
```

### Orders

```text
POST   /api/checkout
GET    /api/orders/{user_id}
```

Checkout notes:

- accepts `user_id` in the request body
- supports `Idempotency-Key` in request headers
- returns `order` and `recommendations`

---

## Testing

The Laravel API already has feature coverage for the main marketplace workflows and backend hardening behaviors, including:

- product listing, creation, search, and pagination
- product detail, update, and delete
- cart add/view/remove
- checkout success flow
- order item creation
- atomic stock reduction
- validation failure
- idempotent checkout replay
- rollback on insufficient stock
- outbox event creation and processing
- scheduler registration
- recommendation caching and fallback
- product image upload and persistence

Run backend tests with:

```bash
cd core-api-laravel
php artisan test
```

---

## Running the Project

Start each app/service in its own terminal.

### Laravel API Gateway

```bash
cd core-api-laravel
php artisan serve
```

### Next.js Storefront

```bash
cd frontend-nextjs
npm install
npm run dev
```

### Django Recommendation Service

```bash
cd recommendation-ai-django
python manage.py runserver 8002
```

### Go Authentication Service

```bash
cd auth-service-go
go run main.go
```

### Node.js Chat Service

```bash
cd chat-service-node
node server.js
```

### Rust Search Service

```bash
cd search-service-rust
cargo run
```

### Rails Seller Service

```bash
cd seller-service-rails
bin/rails server
```

### Vue Admin Dashboard

```bash
cd admin-vue-dashboard
npm install
npm run dev
```

Admin dashboard notes:

- expects the Laravel API gateway to be running on `http://localhost:8000`
- expects product image storage to be available from Laravel's `/storage` public path
- currently uses manual `user_id` input for product ownership when creating or editing products

### Optional Laravel background processing

```bash
cd core-api-laravel
php artisan schedule:work
```

```bash
cd core-api-laravel
php artisan outbox:process
```

---

## Why This Project Matters

This repo is evolving into a stronger backend/full-stack portfolio project because it demonstrates:

- polyglot service boundaries across PHP, Python, Go, Node.js, Rust, Ruby, and TypeScript
- API gateway orchestration
- transaction-safe checkout design
- frontend integration against live backend APIs
- room for buyer, admin, and seller experiences in separate apps
- production-style patterns such as idempotency, stock protection, and outbox processing

The main value is not only that the marketplace works, but that the architecture is being expanded in a realistic direction.
