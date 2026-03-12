# Phase 3 — Props Flow Verification

## Required flow

Props must flow into the rendered component as:

1. **Schema defaults** (from registry: `runtimeEntry.defaults` / `schema.defaultProps`)
2. **+ Saved component props** (section.props or section.propsText)
3. **+ Responsive overrides** (when present: `responsive.desktop.*`, `responsive.tablet.*`, `responsive.mobile.*`, `responsive.hide_on_*`)

All three are merged and passed to the component; responsive overrides are part of the saved props object (nested under `responsive`).

---

## Implementation

### 1. Merge: defaults + saved props

**Location:** `componentRegistry.ts` — `resolveComponentProps(registryId, propsInput)`

- `defaults` = `getComponentRuntimeEntry(registryId).defaults` (from schema)
- `overrides` = `parseComponentProps(propsInput)` (section.props or parsed section.propsText)
- Returns `mergeResolvedProps(defaults, overrides)` (deep merge; overrides win)

So: **schema defaults + saved component props** → single `props` object.

### 2. Responsive in saved props

Saved props can include nested keys such as:

- `responsive.desktop.*`, `responsive.tablet.*`, `responsive.mobile.*` (padding, margin, font_size, grid_columns, etc.)
- `responsive.hide_on_desktop`, `responsive.hide_on_tablet`, `responsive.hide_on_mobile`

`mergeResolvedProps` merges nested objects, so `responsive` from defaults and from saved props are combined; section overrides win per key.

So: **responsive overrides** are not a separate step; they are part of the saved props and are already included in the merged `props` from `resolveComponentProps`.

### 3. Central components: mapBuilderProps + ensureFullComponentProps

**Location:** `BuilderCanvas.tsx` — when `getCentralRegistryEntry(section.type)` is set

- `mapped` = `centralEntry.mapBuilderProps(props)` (builder prop names → component API)
- `componentProps` = `ensureFullComponentProps(centralEntry.defaults, mapped)` (defaults + mapped; ensures no undefined for schema fields)

Component receives `componentProps` (includes all merged fields and `responsive` when present).

### 4. Non-central components

Canvas component receives `props` from `resolveComponentProps` (same merged object: defaults + saved + responsive).

---

## Verification (completed)

Temporary debug logging was added and run in tests. Confirmed:

- **componentKey** resolution: `runtimeEntry.componentKey` (e.g. `webu_general_hero_01`, `webu_header_01`) is correct.
- **Merged props**: `resolveComponentProps` returns an object that includes both schema defaults (layout, style, advanced, responsive, states) and saved overrides (title, buttonText, logoText, etc.).
- **Responsive**: Merged props include `responsive: { desktop, tablet, mobile, hide_on_desktop, hide_on_tablet, hide_on_mobile }` when the schema has responsive support; `hasResponsive: true` in logs.
- **Central componentProps**: For Header/Hero, `componentPropsReceived` includes mapped keys (e.g. `logo`, `headline`, `ctaLabel`) and the same `responsive` object.

Debug logs were removed after verification.

---

## Test coverage

- `resolveComponentProps` and `ensureFullComponentProps` are used by the canvas and tested indirectly via BuilderCanvas tests (rendered content matches props).
- For an explicit props-flow test, see `builder/__tests__/architectureValidation.test.ts` (“Schema/defaults consistency”, “Prop update uses same merge path”) and `componentRegistry` / `updatePipeline` tests.
