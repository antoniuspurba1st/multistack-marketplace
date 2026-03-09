# Polyglot Microservices Marketplace

A full‑stack marketplace backend built using a **polyglot microservices architecture**. This project demonstrates how multiple technologies can work together in a scalable system.

---

# Tech Stack

## Frontend

- Next.js

## Core Backend

- Laravel (PHP)

## Microservices

- Go — Authentication Service
- Node.js — Realtime Chat Service
- Django — AI Recommendation Service

## Database

- PostgreSQL

---

# Architecture

Next.js acts as the frontend that communicates with multiple backend services.

```
                Next.js
                   |
        -----------------------
        |                     |
     Laravel               Node.js
   Core API               Chat Service
        |
     PostgreSQL

Other services
- Go → Authentication
- Django → AI Recommendation
```

Laravel serves as the **main marketplace API** that handles business logic and database operations.

---

# Features

## Product System

- Create products
- List products
- Product details

## Cart System

- Add product to cart
- View cart items

## Checkout System

- Convert cart into orders
- Store order items
- Calculate total price

## Order System

- Order creation
- Order history

---

# Database Schema

Main tables used in the marketplace:

```
users
products
carts
cart_items
orders
order_items
```

Relationships:

```
User
 └ Cart
     └ CartItems
          └ Product

User
 └ Orders
     └ OrderItems
          └ Product
```

---

# API Endpoints

## Products

```
GET    /api/products
POST   /api/products
GET    /api/products/{id}
```

## Cart

```
POST   /api/cart/add
GET    /api/cart/{user_id}
DELETE /api/cart/item/{id}
```

## Orders

```
POST   /api/checkout
GET    /api/orders/{user_id}
```

---

# Example Checkout Flow

```
User
 ↓
Browse products
 ↓
Add to cart
 ↓
Checkout
 ↓
Order created
```

---

# Running the Project (Development)

Start each service individually.

## Laravel API

```
php artisan serve
```

## Go Authentication Service

```
go run main.go
```

## Django AI Service

```
python manage.py runserver 8002
```

## Node Chat Service

```
node server.js
```

## Next.js Frontend

```
npm run dev
```

---

# Purpose of This Project

This project was built to explore **polyglot microservices architecture** and demonstrate how different technologies can be combined to build a scalable marketplace system.

It also serves as a **portfolio project for backend architecture and microservices design**.

---

# LinkedIn Caption (Optional)

Just finished building a polyglot microservices marketplace backend.

Tech stack used:

- Laravel
- Next.js
- Go
- Node.js
- Django
- PostgreSQL

Implemented features:

- Product system
- Cart system
- Checkout flow
- Order history
- Microservices architecture

This project was a fun way to explore how different technologies can work together in a scalable backend system.

Next step: building the frontend and adding real-time features.

#backend #microservices #laravel #nodejs #golang #django
