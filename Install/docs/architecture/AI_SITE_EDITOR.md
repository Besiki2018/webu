# Webu AI Site Editor (Lovable/Codex-style)

The AI Site Editor turns Webu Chat into a **real AI editing agent**: it analyzes page structure, generates an action plan, executes changes via the builder/CMS only, and updates the preview in real time. No fake text confirmations—only real execution and structured result summaries.

## Execution pipeline

```
User prompt
    ↓
Page structure analysis (getCurrentPageStructure)
    ↓
Target detection + action plan (interpret → ChangeSet)
    ↓
Action execution (AiAgentExecutorService)
    ↓
Builder state update (revision + theme_settings)
    ↓
Preview re-render (client)
    ↓
Execution result (action_log + highlight_section_ids)
```

1. **Analyze** – GET `/panel/projects/{project}/ai-site-editor/analyze` returns **pages** (id, slug, title, sections with id/type/label, editable_fields) and **global_components** (header, footer with editable_fields from theme_settings.layout). This is the only way the AI learns the current page structure before acting.
2. **Interpret** – POST `/panel/projects/{project}/ai-interpret-command` with `instruction` and `page_context` returns a **ChangeSet** (`operations` + `summary`) without applying.
3. **Execute** – POST `/panel/projects/{project}/ai-site-editor/execute` with `change_set` and optional `page_id`/`page_slug`. **AiAgentExecutorService** applies site-level ops (theme, header/footer) then section ops (page content), then returns `action_log`, `applied_changes`, `highlight_section_ids`. On failure returns `error` and `error_code` (e.g. `page_not_found`, `site_operations_failed`, `change_set_failed`, `validation_failed`, `patcher_failed`) for transparency.

## ChangeSet operations (supported in executor)

**Page/section ops** (applied to page content via `AiChangeSetToContentMergeService` + `AiContentPatchService`):

- **updateSection** – `sectionId` (localId) + `patch` object merged into section props.
- **insertSection** – `sectionType` + optional `afterSectionId`; inserts a new section with default payload.
- **deleteSection** – `sectionId`; removes the section.
- **reorderSection** – `sectionId` + `toIndex`; moves the section.
- **replaceImage** – `sectionId` + optional `image_url` or `patch`; sets image URL on the section (merged as section patch).
- **updateButton** – `sectionId` + `patch`; applied as `updateSection`.

**Site-level ops** (applied via `AiSiteEditorSiteOpsService` to `site.theme_settings`):

- **updateTheme** – `patch` object merged into `theme_settings` (e.g. design tokens, typography).
- **updateGlobalComponent** – `component`: `"header"` or `"footer"`, `patch` merged into `theme_settings.layout.header_props` or `theme_settings.layout.footer_props`.

Other ops (e.g. `translatePage`, `generateContent`, `addProduct`, `removeProduct`) are accepted by the interpreter but may not be fully applied by the executor yet.

## Agent behavior (Lovable/Codex-style)

The chat behaves as an action-driven agent, not a text-only bot:

1. **Visible progress** – While the builder or "Apply to site" runs, the UI shows the exact execution states: **Analyzing page…** → **Locating component…** → **Preparing change…** → **Applying change…** → **Updating preview…** → **Completed**. No silent delays.
2. **Planned changes before apply** – Clicking "Apply to site" first runs analyze + interpret and shows a "Planned changes" strip (interpret summary). The user can **Cancel** or **Confirm and apply**; execution runs only after confirm.
3. **Execute response** – The execute API returns `action_log` (human-readable list of what was done, including old → new where applicable), `applied_changes`, and `highlight_section_ids` so the client can show a result summary and highlight changed sections in the preview.
4. **Action log in chat** – After a successful apply, the assistant message includes an action log block ("Completed changes: …") so the user sees exactly what changed.
5. **Highlight after apply** – The first section in `highlight_section_ids` is highlighted in the preview for a few seconds so the user sees where the change was applied.
6. **No fake confirmations** – Success is only reported when the change set was actually applied (DB + revision); errors are returned with a clear message and `error_code` for user-facing copy.

## Frontend

- **Chat** – "Apply to site" button: runs `interpretAndExecute()` (analyze → interpret → execute), shows agent phases, then adds an assistant message with `action_log` and triggers preview refresh + section highlight.
- **AgentProgressInline** – Shows current step (connecting / analyzing / locating / editing / updating_preview / completed / failed) from builder progress or AI Site Editor phases.
- **useAiSiteEditor(projectId)** – hook exposing `analyze()`, `interpret()`, `execute()`, `interpretAndExecute()`, and busy/phase state.
- **Session memory** – After a successful apply, the client stores the action log summary and sends it as `page_context.recent_edits` on the next interpret. The AI prompt includes "Recent edits in this session (use to resolve 'it', 'the title', 'make it shorter', etc.)" so follow-up commands like "make the title shorter" can target the last-edited content.

## AI tools layer

The agent may only modify the site through these logical operations (implemented by the executor and analyze endpoints):

| Tool | Implementation |
|------|----------------|
| getCurrentPageStructure | GET analyze → pages + global_components |
| getPageComponents | From analyze response (sections per page) |
| getEditableParameters(componentId) | From section editable_fields or global_components |
| updateComponentParameter | updateSection / updateButton op |
| createSection | insertSection op |
| deleteSection | deleteSection op |
| replaceImage | replaceImage op |
| updateGlobalHeader | updateGlobalComponent(header) op |
| updateGlobalFooter | updateGlobalComponent(footer) op |
| updateTheme | updateTheme op |
| refreshPreview | Client triggers after execute |
| highlightComponent | Client uses highlight_section_ids from execute response; InspectPreview scrolls to section and highlights 2–3s |
| scrollToComponent | Same as highlight: scrollIntoView in preview iframe when highlightSectionLocalId is set |
| uploadMedia | Not implemented: replaceImage sets image URL only; no AI-triggered file upload yet |

All modifications go through the ChangeSet → executor; the AI never touches component source code, builder core logic, or system configuration.

## Safety

- **AI may only modify:** component parameters (section props), page content (sections), CMS entries, theme_settings (design tokens, layout header/footer).
- **AI must never modify:** component source code, builder core logic, system configuration.
- All changes go through the existing CMS: page revisions, schema validation (`AiOutputSchemaValidator`), and section bindings. Site-level ops only merge into `theme_settings`; no arbitrary keys outside this structure are written.

## Activity logging

- Every execute is logged via `OperationLogService` (`ai_site_editor_execute`) with:
  - **context:** `page_id`, `page_slug`, `revision_id`, `instruction` (user command), and timestamp via `occurred_at`.
  - **applied_changes:** array of `{ op, section_id?, component?, summary, old_value?, new_value? }` for each applied operation. For updateSection/updateButton/replaceImage, `old_value` and `new_value` are included (key–value for section props, or single value for replaceImage) so the log contains what changed.
- AI revision history is stored by `AiRevisionService` when the patch is applied.

## Failure transparency

- If execution fails, the API returns `success: false`, `error` (message), and `error_code` so the client can show a specific reason (e.g. "Page not found", "Theme/header update failed", "Validation failed"). The system never pretends success when a change was not applied.

## Files

- **Backend:** `App\Services\AiSiteEditorAnalyzeService` (analyze + global_components), `App\Services\AiAgentExecutorService` (single executor for change sets), `App\Services\AiChangeSetToContentMergeService`, `App\Services\AiSiteEditorSiteOpsService`, `ProjectAiContentPatchController::analyze`, `ProjectAiContentPatchController::executeFromChangeSet`.
- **Frontend:** `resources/js/hooks/useAiSiteEditor.ts`, `resources/js/Pages/Chat.tsx` (Apply to site button), `resources/js/lib/aiSiteEditorTools.ts` (named tools facade: `createAgentTools(projectId, api)` → getCurrentPageStructure, getPageComponents, getEditableParameters, applyInstruction).
- **Routes:** `GET panel/projects/{project}/ai-site-editor/analyze`, `POST panel/projects/{project}/ai-site-editor/execute`.

## Example commands

- "Change homepage title to Welcome"
- "Update the hero headline"
- "Add a pricing section after the hero"
- "Remove the testimonials section"
- "Fix grammar in the hero section"

The interpreter maps these to ChangeSet operations; the executor applies them to the current page content and saves a new revision.
