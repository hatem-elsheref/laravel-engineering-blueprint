# Module dependency graph (golden fixture)

Used by `ModuleDependencyGraphTest` and PHPStan boundary tests. Represents [MODULE-BOUNDARIES.md](../../../docs/MODULE-BOUNDARIES.md) enforcement.

## Modules

- **Orders** — consumer
- **Inventory** — supplier

## Allowed cross-module edges (`use` imports)

| From | To | Segment | Example |
|------|-----|---------|---------|
| Orders | Inventory | Actions | `Inventory\Actions\ReserveStock` |

## Forbidden cross-module edges (must fail scanner + PHPStan)

| From | To | Segment | Fixture file |
|------|-----|---------|----------------|
| Orders | Inventory | Models | `Orders/Actions/CrossModuleUse.php` |
| Orders | Inventory | Models | `Orders/Actions/CrossModuleStatic.php` (static call; PHPStan) |

The CLI scanner (`engineering:architecture`) flags **`use`** lines only. PHPStan extension covers static/`new`/hierarchy without imports.
