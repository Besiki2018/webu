# Builder Page Model (Phase 15)

The builder page is a **serializable data structure**. The canvas renders only from this model; there is no hidden UI state in the tree.

## Node shape

Each node in the tree has the form:

```json
{
  "id": "hero-1",
  "componentKey": "hero",
  "variant": "center",
  "props": {},
  "children": []
}
```

- **id** (string) — unique instance id.
- **componentKey** (string) — registry key (e.g. `webu_general_hero_01`).
- **variant** (string, optional) — variant key; can also live in `props.variant` but `node.variant` is canonical.
- **props** (object) — component props; merged with registry defaults at render time.
- **children** (array, optional) — nested `BuilderPageNode[]` for container components.

Optional fields:

- **responsive** — per-breakpoint overrides.
- **metadata** — builder-only metadata (not passed to the component).

## Page model

- **BuilderPageModel** = array of root nodes (`BuilderPageNode[]`).
- **BuilderPageNode** = `BuilderComponentInstance` (same shape as above).

The store’s `componentTree` is a `BuilderPageModel`. The canvas receives `componentTree` and passes it to `CanvasRenderer`; the renderer looks up each node in the registry, merges defaults with `node.props`, and renders. No direct component imports; everything is driven by the serializable tree.

## Serialization

- **`serializePageModel(model)`** — `BuilderPageModel` → JSON string (for save/export).
- **`parsePageModel(json)`** — JSON string → `BuilderPageModel` (for load/import).
- **`toSerializableNode(node)`** — normalizes a node to a strictly serializable shape (e.g. before save).

Defined in `builder/core/pageModel.ts` and re-exported from `builder/core`.
