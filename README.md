# Polyglot Microservices Marketplace

A portfolio marketplace project built to demonstrate a production-style polyglot architecture with one storefront, one API gateway, and several supporting services written in different stacks. The system is evolving from simple service separation toward more realistic cross-service checkout coordination and inventory handling.

## Recent Progress Snapshot

- `frontend-nextjs` now provides a working buyer flow with product listing, detail, cart, checkout success, and order history.
- `admin-vue-dashboard` now provides a usable catalog operations surface through the Laravel gateway.
- `seller-service-rails` is now the active product and inventory service, including reservation-based stock handling.
- `core-api-laravel` now acts as a gateway and checkout orchestrator rather than owning product persistence directly.
- The checkout path includes reservation, confirmation, release-on-failure, and outbox-backed retry for follow-up recovery work.

This keeps the project grounded as a portfolio repo while showing a more realistic move toward coordinated service boundaries.

---

## Current Workspace

### User-facing apps
- `frontend-nextjs` - buyer storefront built with Next.js App Router
- `admin-vue-dashboard` - admin dashboard for catalog operations

### Core platform
- `core-api-laravel` - API gateway and marketplace orchestration layer
- `seller-service-rails` - product and inventory service
- `recommendation-ai-django` - recommendation service
- `auth-service-go` - authentication service
- `chat-service-node` - realtime chat service
- `search-service-rust` - search service prototype

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
  |-------------------------------\
  |                                \
PostgreSQL                          Rails Seller Service
                                    (products, inventory, reservations)
                                    Django Recommendation Service
                                    Go Authentication Service
                                    Node.js Chat Service
                                    Rust Search Service (prototype)

Admin -> Vue Dashboard -> Laravel API Gateway
```

Laravel remains the central orchestrator for:

- product gateway endpoints
- cart operations
- coordinated checkout and order creation
- idempotent requests
- outbox-driven follow-up work
- recommendation calls

Rails now owns:

- product data
- inventory state
- stock reservation, confirmation, and release

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
- Product gateway CRUD foundations
- Search and pagination
- Product image upload
- Cart add/view/remove
- Transaction-safe checkout
- Reservation-based stock handling
- Coordinated checkout with confirm and release steps
- `Idempotency-Key` support
- Outbox event persistence and retry processing
- Order history API
- Recommendation service integration with caching

### Supporting services
- Rails seller service handles product and inventory lifecycle, including reserve, confirm, and release flows
- Django recommendation endpoint integrated into checkout
- Go auth service present in workspace
- Node chat service present in workspace
- Rust search service bootstrapped and runnable
- Vue admin dashboard connected for catalog management

---

## Checkout Flow

The current checkout path keeps Laravel focused on orchestration while Rails owns inventory behavior:

1. Laravel asks Rails to reserve stock for the cart items.
2. Laravel creates the order and order items inside its own database transaction.
3. If the transaction succeeds, Laravel confirms the reservation.
4. If the transaction fails, Laravel releases the reservation.
5. If confirm or release cannot be completed immediately, Laravel records an outbox event and retries it asynchronously.

This reduces inconsistency during partial failures without pushing product ownership back into Laravel.

---

## Backend Engineering Patterns Already Present

- request validation
- reduced transaction scope
- reservation pattern for stock handling
- coordinated checkout with confirm and release steps
- compensation for failed checkout steps
- idempotent checkout
- outbox pattern
- retry + timeout for seller-service calls
- basic circuit breaker for seller-service failures
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

### Seller service coordination

These endpoints are used by Laravel when coordinating checkout with the Rails seller service:

```text
POST   /products/reserve
POST   /products/confirm
POST   /products/release
```

---

## Testing

The repo includes coverage for the main marketplace workflows and the newer cross-service checkout behaviors, including:

- product listing, creation, search, and pagination
- product detail, update, and delete
- cart add/view/remove
- checkout success flow
- reservation, confirm, and release behavior
- failure simulation and release fallback
- retry behavior for seller-service calls
- idempotent checkout replay
- outbox event creation and processing
- recommendation caching and fallback
- Rails reservation logic, stock locking, and insufficient stock handling
- product image upload and persistence

Run Laravel tests with:

```bash
cd core-api-laravel
php artisan test
```

Run Rails seller-service tests with:

```bash
cd seller-service-rails
RAILS_ENV=test bin/rails test
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

Seller service notes:

- owns product and inventory behavior
- exposes reservation endpoints used by Laravel during checkout

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

This repo is evolving into a stronger backend and full-stack portfolio project because it demonstrates:

- polyglot service boundaries across PHP, Python, Go, Node.js, Rust, Ruby, and TypeScript
- API gateway orchestration across multiple services
- coordinated checkout with reservation-based inventory handling
- failure recovery through release flows and outbox-backed retry
- frontend integration against live backend APIs
- room for buyer, admin, and supporting service experiences in separate apps
- practical resilience patterns such as idempotency, retry, timeout, logging, and a basic circuit breaker

The main value is not only that the marketplace works, but that the architecture is being expanded in a realistic direction.
