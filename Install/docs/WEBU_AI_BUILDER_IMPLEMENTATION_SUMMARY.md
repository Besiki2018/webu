# Webu AI Builder — Implementation Summary

Paths below refer to the Laravel/Vite project root (e.g. `Install/`); frontend code lives under `resources/js/` (task doc may use logical `src/`).

This document summarizes the Webu AI Builder extensions: component parameterization, element selection, DOM mapping, layout refiner, autopilot, memory, and real project code generation.

## 1. Component parameter metadata & registry

- **`resources/js/builder/componentParameterMetadata.ts`**: Maps section keys to display names and editable fields; `buildElementId(sectionKey, param)` → `"HeroSection.title"`; `resolveElementIdToParameter(elementId, sections)` for AI `updateText`.
- **`resources/js/builder/componentRegistry.ts`**: Header has `logo_alt`, `ctaText`, `ctaLink`; Footer has `socialLinks`. All builder components expose editable parameters in the sidebar.

## 2. DOM Mapper & element selection

- **`resources/js/builder/domMapper/domMapper.ts`**: `buildDOMMap(doc)` scans iframe for `[data-webu-section]` and `[data-webu-field]`; `getElementAtPoint(doc, x, y, map)` returns `MappedElement` (elementId, parameterName, selector). **Cache**: `buildDOMMapCached(doc, options?)` caches the map by document fingerprint (section count, field count, ordered section keys); `invalidateDOMMapCache()` is called from CMS when sections are added/removed/reordered (`sectionOrderKey` in `Cms.tsx`). **Debug**: `getDOMMapDebugInfo(map)` and `getDOMMapDebugOverlays(doc)` return section/element bounds and labels for optional debug overlays.
- **`resources/js/components/Preview/InspectPreview.tsx`**: `buildElementMention` adds `parameterName` and `elementId` when the click target is inside `[data-webu-field]`. Hover overlay uses `outline: 2px dashed #6366f1` (task spec).
- **`resources/js/Pages/Project/Cms.tsx`**: Injected iframe styles use `#6366f1` for section hover and selected outline; `invalidateDOMMapCache` on section list change.
- **`resources/js/types/inspector.ts`**: `ElementMention` has `parameterName`, `elementId`.

## 3. AI chat element targeting

- **`resources/js/ai/commands/context.ts`**: `PageContext.selectedElement` with `sectionId`, `parameterPath`, `elementId`; prompt summary includes “Selected element: … Use updateText with this sectionId and path.”
- **Chat flow**: On submit with a selected element, `selected_element: { section_id, parameter_path, element_id }` is sent to `POST /panel/projects/{id}/ai-project-edit`. Backend injects “Selected element …” into the plan prompt so the AI updates the correct prop/file.

## 4. Layout Refiner

- **`resources/js/ai/layoutRefiner/layoutRefiner.ts`**: `runLayoutRefiner({ sections, defaultSpacing })` returns a ChangeSet of `updateSection` ops (padding, containerClass, headlineSize). `applyLayoutRefinement(sections, defaultSpacing)` runs the refiner and applies it in one shot.
- **CMS**: The Structure panel has an “Optimize layout” (Sparkles) button; it calls `handleOptimizeLayout`, which maps `sectionsDraft` to `SectionItem[]`, runs `applyLayoutRefinement`, then maps back and updates draft state. Full-website file generation uses design rules in the backend prompt instead.

## 5. AI Autopilot

- **`resources/js/ai/autopilot/autopilot.ts`**: `runAutopilot({ projectId, prompt, onReloadPreview })` runs scan → generateSitePlan (with design memory hints) → POST ai-project-edit (with `design_pattern_hints` when applicable) → invalidate cache → reload preview. Returns `{ success, summary, log, changes }`.

## 6. AI Memory & design learning

- **`resources/js/ai/memory/designMemory.ts`**: `loadDesignMemory(projectId)` / `saveDesignMemory(projectId, record)` (e.g. localStorage); `getDesignPatternsForType(projectId, websiteType)`; `inferWebsiteTypeFromPrompt(prompt)` (restaurant, saas, portfolio, etc.). After a successful **runAutopilot** run (with a plan and pages), the autopilot calls `saveDesignMemory` so future plans can reuse the pattern.
- **Site Planner integration**: Frontend `generateSitePlan(projectId, prompt, { designPatternHints })` and Autopilot send `design_pattern_hints` to the backend. Backend `ProjectSitePlannerController` and `SitePlannerService::generate(..., $designPatternHints)` inject hints into the planner prompt. `ai-project-edit` accepts optional `design_pattern_hints` and passes them into `runFullSiteGeneration` → planner.

## 7. Real project code generation

- **Backend**: `AiProjectFileEditService::run()` → `runFullSiteGeneration()` → `executeSitePlan()` and `ensurePlannedSectionsExist()` write real files under `storage/workspaces/{project_id}/` (e.g. `src/pages/home/Page.tsx`, `src/sections/*.tsx`, `src/layouts/SiteLayout.tsx`) via `FileEditor::writeFile` / `ProjectWorkspaceService::writeFile`. This workspace is authoritative for project-edit/code-edit flows, but the visual builder itself must hydrate from and persist back to CMS `PageRevision`; workspace files are only a mirrored projection for preview/code context.

## 8. Claude provider

- **`app/Models/AiProvider.php`**: `TYPE_CLAUDE` and default models for Claude. `InternalAiService::callProviderWithMaxTokens()` routes `TYPE_CLAUDE` to `callAnthropicWithTokens()`. No extra code required; add a provider of type “Claude” in Admin → Integrations.

## 9. Backend fixes

- **`app/Services/AiTools/SitePlannerService.php`**: `$availableSections` is now defined before use when AI is not configured; `generate(..., $designPatternHints)` and `buildPrompt(..., $designPatternHints)` add design memory hints to the planner prompt.

## Optional / future

- **Phase 10 Builder Integration (sidebar focus)**: Task: “Selected element → Sidebar focuses the corresponding parameter.” Currently the Chat page sends `selected_element` to the backend and shows selection in context; the CMS builder selects sections on preview click but does not set a “focused parameter” or scroll the sidebar to a specific field. To implement: when the builder supports element-level selection from the preview (e.g. click on `[data-webu-field]`), set a `focusedParameterPath` and have the sidebar scroll/focus that parameter input (e.g. via refs and `scrollIntoView`).

## Key files

| Area              | Path |
|-------------------|------|
| Parameter metadata | `resources/js/builder/componentParameterMetadata.ts` |
| DOM Mapper        | `resources/js/builder/domMapper/` (buildDOMMap, buildDOMMapCached, invalidateDOMMapCache, getDOMMapDebugOverlays) |
| Layout Refiner    | `resources/js/ai/layoutRefiner/` |
| Autopilot         | `resources/js/ai/autopilot/` |
| Memory            | `resources/js/ai/memory/` |
| AI exports        | `resources/js/ai/index.ts` |
| Site plan API     | `app/Http/Controllers/ProjectSitePlannerController.php`, `app/Services/AiTools/SitePlannerService.php` |
| Project edit API  | `app/Http/Controllers/ProjectAiProjectEditController.php`, `app/Services/AiProjectFileEditService.php` |
