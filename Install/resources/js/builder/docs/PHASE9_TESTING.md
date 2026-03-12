# Phase 9 — Testing

Basic validation tests for the schema-driven builder architecture.

## Test file

**`builder/__tests__/phase9ArchitectureValidation.test.tsx`**

## Tests (7)

| # | Test | What it validates |
|---|------|-------------------|
| 1 | **Component registry integrity** | Every registered id has schema, runtime entry, and defaults (`getAvailableComponents` → `getComponentSchema`, `getComponentRuntimeEntry`, `getDefaultProps`). |
| 2 | **Schema/defaults consistency** | Defaults from schema; `resolveComponentProps` merges overrides (migrated central IDs: Header, Footer, Hero). |
| 3 | **Component render-from-props** | Canvas renders a section using only props from state (Hero with custom title/subtitle/buttonText); DOM reflects those values. |
| 4 | **Sidebar field generation** | Schema has `fields` with path/type/group; `getComponentSchemaJson` returns `properties` for control generation. |
| 4b | **Every registry id has schema for sidebar** | `getComponentSchemaJson(id)` returns usable schema for every `getAvailableComponents()` id (controls can be generated for any section on page). |
| 5 | **Prop update rerender** | `applyBuilderUpdatePipeline` set-field updates `sectionsDraft`; `resolveComponentProps(section)` reflects the new value (state that drives rerender). |
| 5b | **Prop update flows to canvas** | Pipeline `result.state.sectionsDraft` passed to BuilderCanvas renders the updated value in the DOM (real rerender path). |
| 6 | **Variant rendering** | Variant in section props; schema has variants; `mapBuilderProps` passes variant through for central components. |
| 7 | **Legacy compatibility** | Legacy section types (e.g. heading, CTA, card) have schema + runtime entry and `resolveComponentProps(id, {})` returns a valid props object. |

## Related tests

- **Registry / central entries:** `registryIntegration.test.tsx`, `architectureValidation.test.ts`
- **Update pipeline:** `state/__tests__/updatePipeline.test.ts`
- **Canvas rendering:** `visual/__tests__/BuilderCanvas.test.tsx`
- **Runtime flow:** `runtimeVerification.test.tsx`
- **Legacy detection (no direct imports):** `legacyDetection.test.ts`

Phase 9 consolidates the seven architecture validations in one suite; the related files above cover the same flows in more detail (e.g. chat change sets, schema rejection, hover targets).
