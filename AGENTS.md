# Store Order & Inventory Mini-System

## Project

This repository contains a Store Order & Inventory Mini-System developed as a Laravel Developer take-home assignment.

The repository contains:

- `backend/` - Laravel 13 REST API
- `frontend/` - Vue 3 + TypeScript frontend
- `docs/` - project architecture and technical documentation
- `prompts/` - screenshots of actual AI-assisted development prompts
- `README.md` - project setup, usage, assumptions, and submission documentation

## Technology Stack

### Backend

- Laravel 13
- PHP 8.3+
- MySQL
- Eloquent ORM
- REST API
- Laravel validation / Form Requests
- API Resources
- Queue Jobs

### Frontend

- Vue 3
- TypeScript
- Vite
- Axios

## Architecture

The backend uses a pragmatic Domain-Driven Design approach combined with:

- Actions / Use Cases
- Eloquent ORM
- Form Requests
- DTOs where appropriate
- API Resources
- Domain/Application Services where justified
- Database transactions
- Queue Jobs

The architecture must remain pragmatic and appropriate for the size of this assignment.

Do not introduce enterprise-level abstractions unless there is a clear requirement.

## Global Development Rules

- Follow the existing architecture before introducing new patterns.
- Inspect the existing implementation before creating new files.
- Reuse existing classes and functionality whenever appropriate.
- Do not duplicate existing functionality.
- Keep changes focused on the requested task.
- Do not modify unrelated files.
- Do not change established architecture without a clear reason.
- Prefer Laravel conventions where they do not conflict with the documented architecture.
- Keep controllers thin.
- Keep business logic out of controllers.
- Put application use cases in Actions.
- Put reusable calculation/domain logic in Services only when justified.
- Use database transactions for operations that must be atomic.
- Prefer simple solutions over unnecessary abstractions.

## Backend Architecture

The backend application is located in `backend/`.

### Domain Models

Domain models must be located under:

`backend/app/Domain/{Domain}/Models/`

Examples:

```text
backend/app/Domain/Customer/Models/Customer.php
backend/app/Domain/Product/Models/Product.php
backend/app/Domain/Order/Models/Order.php
backend/app/Domain/Order/Models/OrderItem.php