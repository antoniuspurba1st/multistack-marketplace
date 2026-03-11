# Polyglot Microservices Marketplace

A full-stack marketplace project built to showcase a production-style polyglot microservices architecture.

The system combines:
- `Next.js` for the frontend
- `Laravel` as the core API gateway and marketplace backend
- `Django` for AI recommendations
- `Go` for authentication
- `Node.js` for realtime chat
- `PostgreSQL` as the primary database

---

# Tech Stack

## Frontend
- Next.js

## Core Backend
- Laravel (PHP)

## Microservices
- Go - Authentication Service
- Node.js - Realtime Chat Service
- Django - AI Recommendation Service

## Database
- PostgreSQL

---

# Architecture

The frontend sends requests to the Laravel API Gateway, which handles marketplace business logic and communicates with supporting microservices via HTTP.

```text
Client
  |
Next.js
  |
Laravel API Gateway
  |------------------------------\
  |                               \
Marketplace Logic                  \
  |                                 \
PostgreSQL                      Django Recommendation Service
                                Go Authentication Service
                                Node.js Chat Service
```

Laravel remains the main entry point for:
- product management
- cart operations
- checkout and order creation
- stock protection
- database transactions
- orchestration with other services

---

# Core Backend Features

## Product System
- Create products
- List products
- Search products
- Pagination
- Upload product images

## Cart System
- Add items to cart
- View cart items
- Remove cart items

## Checkout System
- Convert cart into order
- Create order items
- Atomically decrement stock
- Support idempotent checkout with `Idempotency-Key`
- Create outbox events for asynchronous processing
- Fetch recommendation results from Django service

## Order System
- Order history

---

# Production-Style Backend Improvements

The Laravel API Gateway has been refactored to better resemble real-world backend architecture.

## 1. Request Validation
Checkout now validates incoming requests before any business logic runs.

Example:

```php
$request->validate([
    'user_id' => 'required|integer',
]);
```

## 2. Reduced Transaction Scope
The checkout transaction is limited to database-only operations:
- cart lookup
- order creation
- order item creation
- atomic stock decrement
- outbox event creation
- idempotency response storage

External microservice calls are executed only after the transaction commits.

## 3. Atomic Stock Protection
Stock is updated using an atomic conditional decrement to reduce race-condition risk during concurrent checkouts.

## 4. Idempotent Checkout
Checkout supports the `Idempotency-Key` header:
- duplicate requests return the previously stored response
- repeated checkout calls do not create duplicate orders

## 5. Outbox Pattern
Checkout writes `OrderCreated` events into the `outbox_events` table for asynchronous processing.

## 6. Structured Logging
Important backend events are now logged, including:
- insufficient stock
- checkout completion
- recommendation service failure
- outbox event processing

## 7. Outbox Event Processor
A Laravel Artisan command processes pending outbox events:

```bash
php artisan outbox:process
```

The command:
- fetches unprocessed events
- logs their payload
- marks them as processed

## 8. Scheduler Integration
The outbox processor is registered in Laravel's scheduler and runs every minute.

## 9. Recommendation Service Caching
Recommendation responses from the Django AI service are cached for 5 minutes to reduce repeated network calls and improve resilience.

---

# Database Schema

Main marketplace tables:

```text
users
products
carts
cart_items
orders
order_items
idempotency_keys
outbox_events
```

Key supporting tables:
- `idempotency_keys` stores replayable checkout responses
- `outbox_events` stores domain events awaiting processing

Relationships:

```text
User
 +- Cart
    +- CartItems
       +- Product

User
 +- Orders
    +- OrderItems
       +- Product
```

---

# API Endpoints

## Products

```text
GET    /api/products
POST   /api/products
```

## Cart

```text
POST   /api/cart/add
GET    /api/cart/{user_id}
DELETE /api/cart/item/{id}
```

## Orders

```text
POST   /api/checkout
GET    /api/orders/{user_id}
```

### Checkout Notes
- Accepts `user_id` in the request body
- Supports `Idempotency-Key` in request headers
- Returns `message`, `order`, and `recommendations`

---

# Checkout Flow

```text
User
  |
Browse products
  |
Add items to cart
  |
POST /api/checkout
  |
Laravel validates request
  |
Laravel creates order transactionally
  |
Stock is decremented atomically
  |
Outbox event is stored
  |
Transaction commits
  |
Laravel requests recommendations from Django
  |
Response returned to client
```

---

# Testing

The Laravel API includes PHPUnit test coverage for core marketplace workflows and backend hardening behavior.

Covered scenarios include:
- product listing, creation, search, and pagination
- cart add/view/remove
- checkout success flow
- order item creation
- atomic stock reduction
- validation failure
- idempotent checkout replay
- rollback on insufficient stock
- outbox event creation
- outbox processor command
- scheduler registration
- recommendation service caching and fallback
- product image upload and persistence

Example test files:
- `core-api-laravel/tests/Feature/ProductTest.php`
- `core-api-laravel/tests/Feature/CartTest.php`
- `core-api-laravel/tests/Feature/CheckoutTest.php`
- `core-api-laravel/tests/Feature/ProcessOutboxEventsCommandTest.php`
- `core-api-laravel/tests/Unit/RecommendationServiceTest.php`

Run tests:

```bash
cd core-api-laravel
php artisan test
```

Current result:
- `24 passed`

---

# Running the Project

Start each service individually in development mode.

## Laravel API Gateway

```bash
cd core-api-laravel
php artisan serve
```

## Django Recommendation Service

```bash
cd recommendation-ai-django
python manage.py runserver 8002
```

## Go Authentication Service

```bash
go run main.go
```

## Node.js Chat Service

```bash
node server.js
```

## Next.js Frontend

```bash
cd frontend-nextjs
npm run dev
```

## Optional Scheduled Processing

To run Laravel scheduler locally:

```bash
cd core-api-laravel
php artisan schedule:work
```

To process outbox events manually:

```bash
cd core-api-laravel
php artisan outbox:process
```

---

# Why This Project Matters

This project is designed as a backend engineering portfolio project to demonstrate:
- polyglot microservices communication
- API gateway orchestration
- transaction-safe checkout design
- idempotent API behavior
- atomic inventory protection
- outbox pattern implementation
- scheduled background processing
- structured logging
- resilient microservice integration with caching

It is intended to show not only CRUD functionality, but also backend design patterns that are common in production systems.
