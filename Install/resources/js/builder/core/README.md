# Builder Core Architecture

Foundational folder structure for the schema-driven builder. Architecture only; no UI implementation here yet.

## Structure

| Folder      | Purpose |
|------------|---------|
| **core**   | Base types and interfaces |
| **registry** | Component registry |
| **schema** | Component schemas |
| **renderer** | Canvas renderer |
| **store**  | Builder state |
| **inspector** | Sidebar inspector |
| **updates** | Update pipeline |
| **utils**  | Helper functions |

## Location

Under `Install/resources/js/builder/`:

```
builder/
  core/       → base types and interfaces
  registry/   → component registry
  schema/     → component schemas
  renderer/   → canvas renderer
  store/      → builder state
  inspector/  → sidebar inspector
  updates/    → update pipeline
  utils/      → helper functions
```

Existing builder code (e.g. `componentRegistry.ts`, `state/`, `visual/`) can later be aligned or migrated to this layout as needed.
