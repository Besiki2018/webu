# Phase 12 — Final Stability Validation

This document provides step-by-step instructions to validate the Webu AI system using the required test prompts. Use it after implementation to confirm all phases work together.

---

## Prerequisites

- Webu installed and running (e.g. `php artisan serve` or deployed).
- At least one project with workspace initialized (has `src/pages`, `src/sections`, etc.).
- AI provider configured (OpenAI or Claude) in Admin → Integrations / System settings (`internal_ai_provider_id` or `default_ai_provider_id`).
- Authenticated user with access to the project.

---

## Test 1: Create SaaS landing page

**Prompt:** `Create SaaS landing page`

**How to run:**

- **Option A (backend single entry):**  
  `POST /panel/projects/{project}/ai-project-edit`  
  Body: `{ "message": "Create SaaS landing page" }`

- **Option B (frontend flow):**  
  1. `scanCodebase({ projectId })`  
  2. `generateSitePlan(projectId, "Create SaaS landing page")`  
  3. For each section in plan, `ensureSectionExists(projectId, sectionName, context)`  
  4. Update `src/pages/home/Page.tsx` (or create) with sections from plan via `executeTool('updateFile', ...)`  
  5. `executeTool('reloadPreview', {}, context)` or rely on `onReloadPreview` after file change  

**Expected:**

1. **Understand the project:** Scanner runs (structure returned); AI receives pages/sections list.
2. **Reuse components:** Plan uses existing section names (e.g. HeroSection, FeaturesSection, CTASection) when present.
3. **Generate missing:** If a section (e.g. PricingSection) is in the plan and missing, it is generated (backend in ai-project-edit, or frontend via ensureSectionExists).
4. **Modify files:** Page file(s) under `src/pages/` created or updated with imports and section order; new sections under `src/sections/` if generated.
5. **Update preview:** After file writes, preview reload is triggered (onReloadPreview or reloadPreview tool).

**Design rules:** Generated layout uses container (max-width 1290px), section spacing (e.g. 80px), typography scale. No fixed widths on sections.

**Verify:** Open project workspace; confirm `src/pages/home/Page.tsx` (or equivalent) exists and contains section components in a logical order (Hero → Features → CTA). Check `cms/ai_project_edit_log.jsonl` in project workspace for an entry with your request and listed changes.

---

## Test 2: Create restaurant website

**Prompt:** `Create restaurant website`

**How to run:** Same as Test 1, replace prompt with `Create restaurant website`.

**Expected:**

- Plan includes pages such as: home, about, menu, gallery, contact (or similar).
- Sections per page follow landing/business structure (Hero, Features/Services, Gallery, Testimonials, Contact).
- Existing sections reused; missing ones (e.g. TestimonialsSection, TeamSection) generated when in plan.
- Files created/updated under `src/pages/` and optionally `src/sections/`.
- Preview reflects changes.

**Verify:** List `src/pages/` and `src/sections/`; confirm new or updated pages and any new section files. Log entry in `cms/ai_project_edit_log.jsonl`.

---

## Test 3: Add testimonials section

**Prompt:** `Add testimonials section`

**How to run:**

- **Option A:**  
  `POST /panel/projects/{project}/ai-project-edit`  
  Body: `{ "message": "Add testimonials section" }`  
  (Backend may generate TestimonialsSection if missing and add to a page.)

- **Option B:**  
  1. `ensureSectionExists(projectId, "TestimonialsSection", context)`  
  2. If `result.created`, section file exists at `src/sections/TestimonialsSection.tsx`.  
  3. Update target page (e.g. home) to import and render `<TestimonialsSection />` via `updateFile`.  
  4. Preview reload (automatic if onReloadPreview in context).

**Expected:**

- If TestimonialsSection did not exist: component generated and written to `src/sections/TestimonialsSection.tsx` (design rules: section + container).
- Section inserted into the chosen page; preview shows the new section.

**Verify:** File `src/sections/TestimonialsSection.tsx` exists; at least one page imports and renders it. Preview updates.

---

## Test 4: Change hero text

**Prompt:** `Change hero text`

**How to run:**

- `POST /panel/projects/{project}/ai-project-edit`  
  Body: `{ "message": "Change hero text to Welcome to our site" }`  
  Or use a more specific instruction (e.g. "Update the hero title to X").

**Expected:**

- AI produces an edit plan (e.g. updateFile on the page or the hero section component).
- File operations are under allowed paths (src/pages, src/sections, etc.).
- Modified file content reflects the new text.
- ExecutionLogger records the request and changes.
- Preview reflects the change after reload.

**Verify:** Open the modified file; confirm hero title/subtitle (or relevant props) updated. Log entry present.

---

## Success Criteria (all phases)

| Requirement | How to confirm |
|-------------|----------------|
| AI understands the project | Scanner runs before plan/edit; structure (pages, sections) visible in backend flow or frontend scanCodebase. |
| Reuse components | Plan and execution use existing section names from scan; no duplicate section file created when one exists. |
| Generate missing components | When plan or user asks for a section not in project, Component Generator runs; new file in `src/sections/` with design rules. |
| Modify project files | readFile/writeFile/createFile/updateFile/deleteFile only under PathRules; changes visible in workspace. |
| Update preview | After create/update/write/delete, onReloadPreview is called (frontend); builder preview iframe or app refreshes. |
| Logging | Every project edit: `cms/ai_project_edit_log.jsonl` has user_request, summary, changes, timestamp. Every tool run: Laravel log (channel single) has project_id, tool, timestamp, success, path. |

---

## Quick checklist

- [ ] Test 1 — Create SaaS landing page: plan + pages/sections + preview.
- [ ] Test 2 — Create restaurant website: multiple pages + sections + preview.
- [ ] Test 3 — Add testimonials section: component created if missing, inserted, preview.
- [ ] Test 4 — Change hero text: file updated, log entry, preview.
- [ ] Execution log file exists and contains entries for the above.
- [ ] No path violations (all paths under src/pages, src/sections, src/components, src/layouts, src/styles, public).
- [ ] Preview reloads after file changes when onReloadPreview is provided.

Once all items pass, Phase 12 validation is complete and the Webu AI system is confirmed as a fully functional AI development agent within the project workspace.
