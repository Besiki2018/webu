# Phase 1 — Component Registry Integration Verification

## Registry file location

- **Central registry (full schema-driven components):**  
  `Install/resources/js/builder/centralComponentRegistry.ts`

- **Main registry (all builder section types):**  
  `Install/resources/js/builder/componentRegistry.ts`  
  (object: `REGISTRY`; keys = registry IDs, e.g. `webu_header_01`)

---

## Central registry structure

Each entry in `centralComponentRegistry.ts` follows:

```ts
componentRegistry = {
  header: {
    component: Header,        // React component
    schema: HeaderSchema,      // ComponentSchemaDef
    defaults: HEADER_DEFAULTS, // Record<string, unknown>
    mapBuilderProps?: (p) => P // optional: builder props → component props
  },
  // footer, hero ...
}
```

- **component:** React component that renders from props only.
- **schema:** Schema (ComponentSchemaDef) for sidebar/editing.
- **defaults:** Default props; merged with saved props by the canvas.
- **mapBuilderProps:** Optional; maps builder prop names to component API.

`REGISTRY_ID_TO_KEY` maps registry ID → short key (e.g. `webu_header_01` → `'header'`).

---

## Verified: components in central registry

| Registry ID             | Key    | Component | Schema | Defaults |
|-------------------------|--------|-----------|--------|----------|
| `webu_header_01`        | header | Header    | ✓      | ✓        |
| `webu_footer_01`        | footer | Footer    | ✓      | ✓        |
| `webu_general_hero_01`  | hero   | Hero      | ✓      | ✓        |

Header includes navigation; there is no separate “Navigation” entry.

---

## Verified: components in main REGISTRY (schema + defaults via normalization)

All of the following are in the main `REGISTRY` and have:

- **Schema:** from explicit `schema` (Header, Footer, Hero) or from `normalizeSchema(definition)` (parameters + foundation fields).
- **Defaults:** from `getDefaultProps(registryId)` (schema.defaultProps or built from parameters).
- **Runtime entry:** `getComponentRuntimeEntry(id)` returns `{ component, schema, defaults }`; component is either the central one or a `Builder*CanvasSection`.

| Registry ID                   | Name / role        | In central? | Canvas component              |
|------------------------------|--------------------|-------------|-------------------------------|
| webu_header_01               | Header             | ✓           | Header (central)              |
| webu_footer_01               | Footer             | ✓           | Footer (central)              |
| webu_general_hero_01         | Hero               | ✓           | Hero (central)                |
| webu_general_cta_01          | CTA                | No          | BuilderCTACanvasSection       |
| webu_general_heading_01       | Feature / Heading  | No          | BuilderHeadingCanvasSection   |
| webu_general_card_01         | Card               | No          | BuilderCardCanvasSection      |
| webu_ecom_product_grid_01     | Product Grid       | No          | BuilderCollectionCanvasSection|
| webu_general_button_01       | Button             | No          | BuilderButtonCanvasSection    |
| … (all other REGISTRY ids)   | …                  | No          | Builder*CanvasSection         |

CTA, Feature (Heading), Card, and Grid are **not** in the central registry because they do not yet have a single unified React component with dedicated schema and defaults files (like Header/Footer/Hero). They are fully wired in the **main** registry: schema and defaults come from parameters + normalization, and the canvas renders them via the corresponding Builder*CanvasSection. Adding them to the central registry later would follow the same pattern: add component + schema + defaults, then register in `REGISTRY_ID_TO_KEY` and `componentRegistry`.

---

## Runtime wiring

- **BuilderCanvas** (`builder/visual/BuilderCanvas.tsx`):  
  Uses `getComponentRuntimeEntry(section.type)` and `getCentralRegistryEntry(section.type)`.  
  If central entry exists → renders `<Component {...componentProps} />` with `ensureFullComponentProps(defaults, mapBuilderProps(props))`.  
  Else → renders the runtime `CanvasComponent` (Builder*CanvasSection) with `resolveComponentProps(section.type, section.props)`.

- **Sidebar / parameters:** Use `getComponentSchema(registryId)` and `getDefaultProps(registryId)` from the main registry (normalized schema applies to all section types).

- **Update pipeline:** Same for all section types; uses schema for validation.

---

## Tests

- `builder/__tests__/registryIntegration.test.ts` — Asserts central registry structure (component, schema, defaults), REGISTRY_ID_TO_KEY, main REGISTRY presence and runtime entry for Header, Footer, Hero, CTA, Feature, Card, Grid.
