# Phase 1 — Component Registry Integration Verification

## Registry file location

- **Canonical registry (all builder section types + full-fidelity helper subset):**
  `Install/resources/js/builder/componentRegistry.ts`
  (object: `REGISTRY`; keys = registry IDs, e.g. `webu_header_01`)

---

## Canonical registry structure

The canonical registry file owns:

- `REGISTRY`
- schema lookup
- default props
- runtime render entry lookup
- full-fidelity helper subset for real React components

The full-fidelity helper subset follows this shape:

```ts
renderEntry = {
  header: {
    component: Header,        // React component
    schema: HeaderSchema,      // ComponentSchemaDef
    defaults: HEADER_DEFAULTS, // Record<string, unknown>
    mapBuilderProps?: (p) => P // optional: builder props → component props
  },
  // footer, hero ...
}
```

---

## Verified: full-fidelity helper entries in canonical registry

| Registry ID             | Key    | Component | Schema | Defaults |
|-------------------------|--------|-----------|--------|----------|
| `webu_header_01`        | header | Header    | ✓      | ✓        |
| `webu_footer_01`        | footer | Footer    | ✓      | ✓        |
| `webu_general_hero_01`  | hero   | Hero      | ✓      | ✓        |

Header includes navigation; there is no separate “Navigation” entry.

---

## Verified: components in REGISTRY (schema + defaults via normalization)

All of the following are in the main `REGISTRY` and have:

- **Schema:** from explicit `schema` (Header, Footer, Hero) or from `normalizeSchema(definition)` (parameters + foundation fields).
- **Defaults:** from `getDefaultProps(registryId)` (schema.defaultProps or built from parameters).
- **Runtime entry:** `getComponentRuntimeEntry(id)` returns `{ component, schema, defaults }`; component is either the central one or a `Builder*CanvasSection`.

| Registry ID                   | Name / role        | In central? | Canvas component              |
|------------------------------|--------------------|-------------|-------------------------------|
| webu_header_01               | Header             | ✓           | Header (full-fidelity helper) |
| webu_footer_01               | Footer             | ✓           | Footer (full-fidelity helper) |
| webu_general_hero_01         | Hero               | ✓           | Hero (full-fidelity helper)   |
| webu_general_cta_01          | CTA                | No          | BuilderCTACanvasSection       |
| webu_general_heading_01       | Feature / Heading  | No          | BuilderHeadingCanvasSection   |
| webu_general_card_01         | Card               | No          | BuilderCardCanvasSection      |
| webu_ecom_product_grid_01     | Product Grid       | No          | BuilderCollectionCanvasSection|
| webu_general_button_01       | Button             | No          | BuilderButtonCanvasSection    |
| … (all other REGISTRY ids)   | …                  | No          | Builder*CanvasSection         |

CTA, Feature (Heading), Card, and Grid do not use the full-fidelity helper subset because they do not yet have a dedicated real-component render entry. They are fully wired in the canonical registry: schema and defaults come from parameters + normalization, and the canvas renders them via the corresponding Builder*CanvasSection.

---

## Runtime wiring

- **BuilderCanvas** (`builder/visual/BuilderCanvas.tsx`):  
  Uses `getComponentRuntimeEntry(section.type)` and `getCentralRegistryEntry(section.type)`.  
  If the canonical full-fidelity helper exists → renders `<Component {...componentProps} />` with `ensureFullComponentProps(defaults, mapBuilderProps(props))`.  
  Else → renders the runtime `CanvasComponent` (Builder*CanvasSection) with `resolveComponentProps(section.type, section.props)`.

- **Sidebar / parameters:** Use `getComponentSchema(registryId)` and `getDefaultProps(registryId)` from the main registry (normalized schema applies to all section types).

- **Update pipeline:** Same for all section types; uses schema for validation.

---

## Tests

- `builder/__tests__/registryIntegration.test.ts` — Asserts canonical registry structure, runtime entries, and the full-fidelity helper subset for Header, Footer, Hero, CTA, Feature, Card, Grid.
