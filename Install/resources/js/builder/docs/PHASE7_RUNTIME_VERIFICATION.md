# Phase 7 — Runtime Verification

Runtime checks ensure the builder behaves correctly end-to-end: selection, sidebar, edits, rerender, state sync, variant, and responsive overrides.

---

## Automated verification (Phase 7 tests)

**File:** `builder/__tests__/runtimeVerification.test.tsx`

| # | Check | What the test does |
|---|--------|---------------------|
| 1 | Select a component on canvas | Asserts state has `selectedSectionLocalId` and `selectedBuilderTarget` for the section |
| 2 | Sidebar loads parameters dynamically | Asserts `getComponentSchema(componentKey)` returns schema with fields (sidebar can build controls from schema) |
| 3 | Change a value in Sidebar | Applies set-field via pipeline; asserts `result.ok` and `result.changed`; parsed section props contain new value |
| 4 | Component rerenders | After pipeline, asserts `resolveComponentProps(section.type, section.props)` returns updated value (state that drives rerender) |
| 5 | Props update in builder state | Uses `updateComponentProps`; asserts `sectionsDraft[0]` and `selectedBuilderTarget.props` both have the new value |
| 6 | Variant switching works | Sets `variant` via set-field; asserts merged props and `mapBuilderProps` output include new variant |
| 7 | Responsive overrides work | Sets `responsive.desktop.padding_top` via pipeline (with no target scope so path is allowed); asserts state and merged props contain the override |
| — | Canvas renders from state | Renders `BuilderCanvas` with a section; asserts displayed text matches section props |

Run: `npm run test -- --run resources/js/builder/__tests__/runtimeVerification.test.tsx`

---

## Manual verification (browser)

When running the app (e.g. project chat with visual builder), you can confirm the same flow in the UI:

1. **Select a component on canvas** — Click a section (e.g. Hero). Confirm the sidebar switches to “Settings” and shows the section’s name.
2. **Sidebar loads parameters dynamically** — Confirm the sidebar shows controls for that section’s fields (title, button, variant, etc.) without a full page reload. Fields should match the registry schema for that section type.
3. **Change a value in Sidebar** — Edit e.g. “Title” or “Button text”. Confirm no error toast and the input accepts the change.
4. **Component rerenders** — Confirm the canvas preview updates to show the new title or button text immediately.
5. **Props update in builder state** — Confirm the change persists (e.g. refresh or switch page and back, or inspect draft state). Saving and reloading should show the same values.
6. **Variant switching** — Change “Variant” or “Design” (e.g. Hero 1 → Hero 2). Confirm the section’s layout/style updates in the canvas.
7. **Responsive overrides** — If the section has responsive controls (e.g. “Desktop padding”), change a value. Confirm it is stored (e.g. in section props under `responsive.desktop.*`) and, if the preview has a breakpoint selector, that switching breakpoint shows the override when applicable.

---

## Notes

- Test 7 uses state with `selectedBuilderTarget: null` so the pipeline does not enforce “selected element scope” on the path; the responsive path is then validated only against the schema. In the UI, selecting the section (or a responsive field) ensures the sidebar shows the right scope and the same pipeline applies.
- Responsive overrides are implemented: schema includes `responsive.desktop.*`, etc.; props flow includes `props.responsive`; merge and pipeline support nested paths.
