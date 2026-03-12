# Part 10 — Editable Output

Generated pages (from **prompt** via `generateSiteFromPrompt()` or from **design** via `generateLayoutFromDesign()`) are **fully editable**. The same builder state and pipelines used for manually added sections apply.

## Requirements

| Capability | How it works |
|------------|--------------|
| **Change titles** | Props `title`, `subtitle`, `headline`, etc. are in section schema. Sidebar and raw JSON edit them; updates go through `updateComponentProps` / `applyBuilderUpdatePipeline` → `applyMutationState`. |
| **Replace images** | Image props (`image`, `backgroundImage`) are in schema; user or chat sets `path: 'image'`, `value: url`. Same pipeline as text. |
| **Change layout** | Layout is driven by `variant` (e.g. hero-1, hero-2). Changing `props.variant` or sidebar variant control swaps layout. Same for features, CTA, cards, grid. |
| **Add sections** | Library + `addSectionByKey(sectionKey, 'library', { insertIndex })` inserts new sections into `sectionsDraft`. |
| **Delete sections** | `handleRemoveSection(localId)` removes by `localId`; same as manual sections. |
| **Edit colors** | Color props (`backgroundColor`, `textColor`) are in schema and sidebar; same update pipeline. |
| **Drag sections** | Sections are in `sectionsDraft` with stable `localId`. Cms uses SortableContext + useSortable; reorder updates the same state. |
| **Chat editing** | Chat sends tool `updateComponentProps` with `componentId` (section `localId`) and `path` / `value`. Frontend `handleToolResult` calls `updateComponentProps` and `applyMutationState`. |

## Generated section format

Generated sections use the same shape as manually added ones:

- **localId** — Stable id (e.g. `header-1`, `hero-2`, `features-3`). Used for selection, drag, and chat targeting.
- **type** — Registry component key (e.g. `webu_general_hero_01`). Drives schema and controls.
- **props** / **propsText** — Editable props; sidebar and chat update these.

So **chat editing works immediately**: the backend can send `updateComponentProps` with `componentId: "hero-2"` (the hero’s `localId`) and `path: "title"`, `value: "New headline"`.

## Chat examples

| User says | Backend / tool | Frontend |
|-----------|----------------|----------|
| **Change hero title** | `updateComponentProps` with `componentId: "<hero localId>"`, `path: "title"`, `value: "..."` | Same pipeline as Sidebar; state updates, canvas re-renders. |
| **Add testimonials section** | `addSection`-style tool or user adds via UI; or backend returns new structure. | `addSectionByKey('webu_general_cards_01', 'library', { insertIndex })` (or equivalent). |
| **Replace hero image** | `updateComponentProps` with `componentId: "<hero localId>"`, `path: "image"`, `value: "https://..."` | Same pipeline; hero `image` prop updated. |

## Design-generated pages (generateLayoutFromDesign)

Design-to-builder output uses the **same section format** as prompt-generated and manual sections. After `setSectionsDraft(result.sectionsDraft)`:

- **Change titles** — Hero/CTA/features props include `title`, `subtitle`; editable in sidebar.
- **Replace images** — Hero/background `image` and `backgroundImage` props; replace via sidebar or chat.
- **Change layout** — Each section has `variant` in props; change to switch layout (e.g. hero-2 → hero-3).
- **Add sections** — Use “Elements” library to add more sections; same `addSectionByKey` flow.
- **Delete sections** — Select section → Remove; or chat “remove the testimonials section”.
- **Edit colors** — `backgroundColor`, `textColor` (and schema-driven style fields) are editable.

No special handling: design-generated sections are plain `sectionsDraft` items with `localId`, `type`, `props`, `propsText`.

## Implementation notes

- **generateSiteFromPrompt** and **generateLayoutFromDesign** both return `sectionsDraft` from `treeToSectionsDraft(tree)`. Each item has `localId`, `type`, `props`, `propsText` — identical to sections added from the library.
- **Cms** applies `setSectionsDraft(result.sectionsDraft)` and `setProjectType(result.projectType)`. No special handling is required for “generated” vs “manual” sections.
- **Chat** uses `componentId` / `sectionLocalId` to target a section; generated sections use ids like `hero-2`, so the backend must resolve “hero” to that `localId` (e.g. first section with `type === 'webu_general_hero_01'` or a known id scheme).
