# NAMING.md

Part of [Phase 0 — Architecture Foundation](./PHASE-0.md).

## 2.1 Naming Principle

Use simple, common, short English words.

Code must be easy to read for developers whose first language is not English.

Prefer clear names over technical or impressive names.

## 2.2 Class Names

Good:

```text
CreateOrder
CancelOrder
OrderData
StoreOrderRequest
UpdateOrderRequest
OrderController
OrderPageController
OrderResource
OrderPolicy
ListOrders
OrderService
OrderStatus
```

Names `make:crud Orders Order` generates:

| Class | Name |
|---|---|
| Model | `Order` |
| Requests | `StoreOrderRequest`, `UpdateOrderRequest` |
| Data | `OrderData` |
| Actions | `CreateOrder`, `UpdateOrder`, `DeleteOrder` |
| Query | `ListOrders` |
| API controller | `OrderController` |
| Web (Blade) controller | `OrderPageController` |
| Resource | `OrderResource` |
| Policy | `OrderPolicy` |
| Factory | `OrderFactory` |

Avoid:

```text
ExecuteOrderCreationWorkflow
OrderPayloadTransferObject
OrderPersistenceOrchestrationManager
```

## 2.3 Method Names

Good:

```php
create()
update()
delete()
cancel()
search()
find()
enabled()
handle()
```

Avoid:

```php
executeCreationProcess()
performOrderMutation()
orchestrateWorkflow()
```

## 2.4 Variable Names

Good:

```php
$order
$items
$user
$tenant
$settings
$features
$quantity
```

Avoid:

```php
$orderEntityInstance
$payloadCollection
$tenantContextObject
$q
$tmp
```

## 2.5 Rules

1. Prefer one or two words.
2. Use common English words.
3. Do not use unclear abbreviations.
4. Do not repeat the folder name in a class name without a reason.
5. Use singular names for one record and plural names for collections.
6. Use Laravel naming conventions.
7. Use descriptive names when a short name would be unclear.

Example:

```text
Actions/
├── CreateOrder.php
├── CancelOrder.php
└── ShipOrder.php
```

Do not add `Action` to every class name when the folder already explains its type.

## 2.5.1 Permission Names

Permissions are `<group>.<ability>`, all lowercase. The group is the CRUD's URI (`products`, `purchase-orders`); the abilities are `view`, `create`, `update`, `delete`, and `any` for owned records (see PERMISSIONS.md). Role patterns may use `<group>.*` and `*`. Names live in the definitions class only.

## 2.6 Route Names

| Route | Name | Example |
|---|---|---|
| API | `api.<uri>.*` | `api.orders.index` |
| Web | `<uri>.*` | `orders.index` |
| Central module API (tenant-saas) | `central.api.<uri>.*` | `central.api.plans.index` |
| Central module web (tenant-saas) | `central.<uri>.*` | `central.plans.index` |

`<uri>` is the plural, kebab-case entity name (`OrderItem` → `order-items`).
