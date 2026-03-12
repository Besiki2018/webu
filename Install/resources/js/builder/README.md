# Builder module (Task 6 boundaries)

State ownership and boundaries for the visual builder and CMS editor.

## Final result — schema-driven builder architecture

The system meets the following requirements with a **stable, reusable architecture** (no temporary hacks):

| # | Requirement | How it is met |
|---|-------------|----------------|
| 1 | All existing components converted to builder components | Header, Footer, Hero + all section types (Heading, Button, Card, Grid, etc.) are registry-driven; canvas resolves via `getComponentRuntimeEntry` / `getCentralRegistryEntry`. |
| 2 | All versions converted into variants | Header1–6, Footer1–4, Hero1–7 are variants under single Header/Footer/Hero; selected by `props.variant`. |
| 3 | No hardcoded editable values in JSX | Variants and canvas components render from props only; defaults come from schema/defaults and `resolveComponentProps`. |
| 4 | Each component has schema + defaults | Header/Footer/Hero: `Component.schema.ts` + `Component.defaults.ts`. Others: normalized schema + defaults from REGISTRY (parameters + buildFoundationFields). |
| 5 | All components registered in component registry | `componentRegistry.ts` REGISTRY; central registry for Header, Footer, Hero; every section type has a runtime entry. |
| 6 | Canvas renders components using registry | `BuilderCanvas` uses only registry (central or runtime); no ad-hoc component maps. |
| 7 | Sidebar parameters driven by schema | `getComponentSchema` / `getComponentSchemaJson` feed sidebar and section library; BuilderCanvasSectionSurface uses `runtimeEntry.schema.fields`. |
| 8 | Chat edits component props safely | All edits through `applyBuilderUpdatePipeline` / `applyBuilderChangeSetPipeline`; paths validated against schema. |
| 9 | Ready for hundreds of future components | Add entry to REGISTRY (and optionally central registry); same pattern, no canvas/sidebar/chat changes. |

**Full architecture and “Adding a new component” pattern:** see [ARCHITECTURE.md](./ARCHITECTURE.md).

## Global layout rules (enforced by output)

- **Sections cannot exceed container width** — all section content is wrapped in `.webu-container` (max-width from `layoutConfig.ts` / `tokens.css`).
- **Components must stay inside container** — builder output uses the structure `Section > .webu-container > Component`; no component should render outside this container.
- **Header and footer are global** — the site has one global header and one global footer (theme layout); editing them updates all pages.
- **Container width is consistent** — desktop 1290px, laptop 1140px, tablet 960px, mobile 100% with 16px padding; values live in `resources/js/config/layoutConfig.ts` and `resources/css/design-system/tokens.css`.

## State and logic ownership

| Concern | Owner | Location |
|--------|--------|----------|
| **Canvas state & selection** | `useBuilderCanvasState` | `builder/state/useBuilderCanvasState.ts` |
| **Tree mutations** | Pure functions | `builder/visual/treeUtils.ts` (removeSection, moveSection, duplicateSection, updateSectionProps, getInsertIndex) |
| **Drag/drop types** | Types | `builder/visual/types.ts` |
| **Canvas renderer** | `BuilderCanvas` | `builder/visual/BuilderCanvas.tsx` |
| **Structure panel (floating)** | `StructurePanel` | `builder/visual/StructurePanel.tsx` |
| **Header/footer layout form** | `HeaderFooterLayoutForm` | `builder/layout/HeaderFooterLayoutForm.tsx` |
| **Transport & polling** | `useBuilderChat` | `hooks/useBuilderChat.ts` |
| **Reconnection** | `useSessionReconnection` | `hooks/useSessionReconnection.ts` |
| **Backend status (read)** | `BuilderStatusController` | `app/Http/Controllers/BuilderStatusController.php` (throttle:builder-status) |
| **Backend mutations** | `BuilderProxyController` | `app/Http/Controllers/BuilderProxyController.php` (throttle:builder-operations) |
| **Component registry** | `componentRegistry.ts` | `builder/componentRegistry.ts` — single source of truth: component IDs, categories, parameter schema, metadata; used by builder panel (future), AI (analyze returns `available_components`), and backend validation (`allowed_section_keys`). |

## Extracted vs in-Cms

- **Structure panel**: `builder/visual/StructurePanel.tsx` — floating draggable panel (position, drag, collapse, header, scroll area). Cms passes position/collapsed state and children (layers list with BuilderCanvasDropZone + SortableContext). Cms keeps clamp for init/resize only.
- **Header/footer layout form**: `builder/layout/HeaderFooterLayoutForm.tsx` — site-level form (header/footer variant, menu sources, footer contact, popup, Edit Header/Footer). Cms owns `builderLayoutForm` state and passes it + callbacks.
- **Selected section inspector panel**: `builder/inspector/SelectedSectionEditableFields.tsx` — selected-section editable state rendering (invalid/no-fields/no-controls/nested banner + grouped field content shell). Cms still owns schema collection and field-control callbacks.
- **Embedded builder bridge**: `builder/cms/useEmbeddedBuilderBridge.ts` — embedded `postMessage` bridge for state sync, selection sync, library/structure snapshots, and incoming builder commands. Cms passes the concrete mutation callbacks and save/change-set handlers.
- **Shared bridge contract + workspace sync helpers**: `builder/cms/embeddedBuilderBridgeContract.ts`, `builder/cms/workspaceBuilderSync.ts`, `builder/cms/pageHydration.ts` — page-scoped bridge identity helpers, chat workspace snapshot/code-page sync helpers, and CMS hydration fallback rules. This keeps `Chat.tsx` from treating `builderCodePages[0]` as authoritative and keeps legacy unrevised pages hydrating through the correct template fallback path.
- **Authority rule**: CMS `PageRevision` is authoritative for visual-builder state. Workspace code mirrors CMS for AI code-edit context and project-edit flows, but the visual builder must always hydrate from and persist back to CMS revisions.
- **In Cms.tsx**: Draft persistence orchestration (debounce + API), preview iframe DOM sync/highlighting, fixed-section edit panel (when header/footer selected), layers list (SortableCanvasSectionCard, BuilderCanvasDropZone), and schema/control generation remain in place.
- **Draft persist scheduler**: `builder/cms/scheduleDraftPersist.ts` provides `createScheduleDraftPersist({ debounceMs })` for raf+debounce. **Cms.tsx** uses `useDraftPersistSchedule(250)` for structural draft persist; the actual save still calls `saveDraftRevisionInternal` in Cms.
- **Preview refresh scheduler**: `builder/cms/schedulePreviewRefresh.ts` provides `createSchedulePreviewRefresh()` with `schedule(run, delayMs)` (variable delay per call; each call cancels pending). **Cms.tsx** uses `usePreviewRefreshSchedule()` for immediate (0ms) and auto-save (1500ms) preview refresh.

## Cms.tsx size

Cms.tsx remains the main control center, but the embedded builder bridge and selected-section inspector shell now live under `builder/`. Further extractions to reduce size: preview iframe DOM sync and the remaining schema/control-generation helpers.

## Chat editing compatibility (Phase 10)

Schema props are editable through chat commands. All chat-driven edits go through the **unified update pipeline** (`builder/state/updatePipeline.ts`):

1. Chat (or backend interpret) produces a **ChangeSet** with `operations`.
2. `buildBuilderUpdateOperationsFromChangeSet(changeSet)` converts each op into pipeline ops (`set-field`, `merge-props`, `insert-section`, etc.).
3. `applyBuilderChangeSetPipeline(initialState, changeSet, options)` applies them; paths are validated against component schema.

**Supported operations (chat → pipeline):**

| Chat op | Pipeline | Example command |
|--------|----------|------------------|
| `updateText` | set-field | "Change hero title" → path `title`, value |
| `setField` | set-field | Any schema prop: path + value (e.g. padding, backgroundColor) |
| `replaceImage` | merge-props | "Replace hero image" → patch `image` / `backgroundImage`, `imageAlt` |
| `updateSection` | merge-props | "Change header background", "Add menu item", "Increase section padding" → patch with schema prop names |
| `updateButton` | merge-props | Button label/href/variant → patch `buttonText`, `buttonLink` |
| `insertSection` / `deleteSection` / `reorderSection` | structural ops | Add/remove/reorder sections |

**Schema prop names:** Chat and AI must use **schema prop names** from each component’s `editableFields` / `chatTargets` (e.g. Hero: `title`, `subtitle`, `image`, `backgroundImage`, `alignment`; Header: `logoText`, `menu_items`, `backgroundColor`, `alignment`; padding/spacing as defined in schema). These are available via `getComponentSchema(registryId).chatTargets` / `getComponentSchemaJson(registryId).editable_fields`.

## Design system & tokens (Parts 6–13)

- **Tokens:** `builder/designSystem/tokens.ts` exports `designTokens` (colors, typography, spacing, radius, shadows, buttons). Use tokens instead of raw values so the whole site follows the same design language.
- **Part 9 — Builder integration:** Prefer token references over raw CSS: e.g. `padding: spacing.md`, `color: colors.primary` (or `var(--spacing-md)`, `var(--color-primary)`). Avoid hardcoded values like `padding: 20px` or `color: #5B6CFF`. The design system applier (`designSystemApplier.ts`) normalizes section props to token refs (e.g. hero background → `colors.background`, buttons → `button.primary`).
- **Shadow/button generators:** `builder/ai/shadowGenerator.ts`, `builder/ai/buttonStyleGenerator.ts` produce token scales; `builder/designSystem/tokens.ts` aggregates them.
- **Design System panel:** In the builder sidebar, "Design System" lets users edit primary color, fonts, spacing scale, radius; changes propagate via CSS variables. "Regenerate Design System" (Part 13) runs the AI brand generator and applies the result so components adapt automatically.

## Component validation (Phase 13)

After refactor, required components are validated in `builder/__tests__/componentValidation.test.tsx`:

- **Header, Footer, Hero, Feature (Heading), CTA (Button/CTA), Cards, Grids** — each has schema with fields (sidebar can load parameters), runtime entry (canvas can resolve component), and central registry for Header/Footer/Hero with `mapBuilderProps`.
- **Canvas renders** — BuilderCanvas renders each section type; section surfaces have `data-builder-section-id`.
- **Props update rerenders** — `resolveComponentProps` merges defaults and overrides; changing section props and rerendering canvas updates the displayed content (e.g. Hero title/button).

## Testing

All tests below are included in the baseline gate (`npm run baseline:gate`).

- `builder/__tests__/componentValidation.test.tsx` — Phase 13: schema, runtime, central registry, canvas render, props update.
- `builder/state/useBuilderCanvasState.test.ts` — canvas state and selection.
- `builder/visual/__tests__/treeUtils.test.ts` — tree mutations.
- `builder/cms/__tests__/scheduleDraftPersist.test.ts` — raf+debounce scheduler.
- `builder/cms/__tests__/useDraftPersistSchedule.test.ts` — draft persist schedule hook.
- `builder/cms/__tests__/schedulePreviewRefresh.test.ts` — variable-delay preview refresh scheduler.
- `hooks/__tests__/useBuilderChat.test.ts` — transport, 429 backoff, no rerender repoll.
- `hooks/__tests__/useSessionReconnection.test.ts` — reconnect and 429 backoff.
