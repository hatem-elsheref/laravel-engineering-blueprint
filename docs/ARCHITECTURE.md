# ARCHITECTURE.md

Part of [Phase 0 — Architecture Foundation](./PHASE-0.md).

## 1.1 Main Principles

The application must follow these principles:

1. Simple and readable code.
2. One clear responsibility per class.
3. Use Laravel native features whenever possible.
4. Keep business logic outside controllers.
5. Keep modules independent.
6. Avoid unnecessary abstractions.
7. Use short and clear English names.
8. Make features configurable per tenant.
9. Keep tenant data isolated.
10. Write tests for important business operations.

## 1.2 Application Structure

```text
app/
├── Modules/
│   ├── Orders/
│   ├── Products/
│   ├── Inventory/
│   ├── Warehouses/
│   └── Users/
│
├── Shared/
│   ├── Features/
│   ├── Settings/
│   ├── Support/
│   └── Contracts/
│
├── Console/
│   └── Commands/
│
└── Providers/
```

Each module contains only the folders it needs.

Example:

```text
app/Modules/Orders/
├── Actions/
├── Data/
├── Requests/
├── Resources/
├── Models/
├── Queries/
├── Services/
├── Policies/
├── Jobs/
├── Events/
├── Controllers/
├── routes/
└── Provider.php
```

Do not create empty folders or unnecessary classes.

## 1.3 Class Responsibilities

| Class | Responsibility |
|---|---|
| Controller | Receive request and return response |
| Request | Authorization and validation |
| Data | Transfer typed data |
| Action | Perform one business operation |
| Service | Reusable business capability |
| Query | Read and search data |
| Model | Eloquent data and relationships |
| Resource | Format API response |
| Policy | Check user permissions |
| Job | Run background work |
| Event | Announce something that happened |

## 1.4 Module Rules

- Every module owns its business logic.
- Every module owns its models.
- Every module owns its routes when needed.
- Database migrations are stored in Laravel's normal migration paths.
- A module must not depend on another module's controllers or requests.
- Cross-module communication uses public actions, contracts, or events.
- Circular dependencies are not allowed.
- Shared code must have a real reason to exist.

## 1.5 Request Flow

```text
Route
  ↓
Middleware
  ↓
Request
  ↓
Data
  ↓
Controller
  ↓
Action / Query
  ↓
Model / Service
  ↓
Resource / View
```

Not every operation needs every layer. Use only what the operation needs.

## 1.6 Frontend Rules

- Blade is the default for normal pages.
- Livewire is used for server-driven interactions.
- Vue is used for complex interactive screens.
- Business rules stay in the backend.
- All frontend options must use the same backend Actions and Queries.
