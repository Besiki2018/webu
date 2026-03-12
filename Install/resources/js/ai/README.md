# Webu AI Editing Command Library

Converts natural-language user instructions into structured **ChangeSet** operations for editing websites. The same codebase provides **project workspace AI**: site planning, component generation, and agent tools for real file edits.

## Webu AI system workflow (project workspace)

End-to-end flow for building or modifying a project from a single prompt:

1. **User prompt** — e.g. "Create SaaS landing page", "Add testimonials section".
2. **Codebase scanner** — Run before any AI operation. `scanCodebase({ projectId })` or `GET panel/projects/{project}/workspace/structure`. Supplies pages, sections, components, layouts so the AI understands the project.
3. **Site planner** — Optional. `generateSitePlan(projectId, prompt)` returns `{ siteName, pages }` with sections per page. Design rules are injected on the backend.
4. **Component analysis** — For each section in the plan, `ensureSectionExists(projectId, sectionName, context)` so missing sections (e.g. PricingSection) are generated and created via `createFile`.
5. **Agent tool execution** — `executeTool('readFile'|'writeFile'|'createFile'|'updateFile'|'deleteFile'|'listFiles'|'searchFiles'|'reloadPreview', args, context)`. All file ops are inside the project workspace; path rules enforced.
6. **File modification** — Pages updated (e.g. `updateFile` on `src/pages/home/Page.tsx`) to import and render the chosen sections. Scan cache invalidated after writes.
7. **Preview reload** — Call `context.onReloadPreview()` or `executeTool('reloadPreview', {}, context)` so the builder preview reflects changes.

Backend single-entry alternative: `POST panel/projects/{project}/ai-project-edit` runs scan → plan → **generate missing sections** (Component Generator) → execute → log in one request. When the plan references a section that does not exist (e.g. PricingSection), the backend generates it and then inserts it into the pages.

### Quick reference (routes and entry points)

| Action | Backend route (Laravel name) | Frontend |
|--------|------------------------------|----------|
| Full project edit | `panel.projects.ai-project-edit` (POST) | — |
| Execute one tool | `panel.projects.ai-tools.execute` (POST) | `executeTool(name, args, context)` |
| Project structure | `panel.projects.workspace.structure` (GET) | `scanCodebase({ projectId })` |
| Site plan | `panel.projects.ai.site-plan` (POST) | `generateSitePlan(projectId, prompt)` |
| Generate component | `panel.projects.ai.generate-component` (POST) | `ensureSectionExists(projectId, sectionName, context)` |

Preview reload: pass `onReloadPreview` in `AiToolContext`; it is called automatically after any successful createFile/updateFile/writeFile/deleteFile in `executeTool`.

## Paths (deliverables)

- **Command interpreter:** `src/ai/commands/interpretCommand.ts` (path alias from `resources/js/ai/commands/interpretCommand.ts`)
- **ChangeSet schema:** `src/ai/changes/changeSet.schema.ts` (path alias from `resources/js/ai/changes/changeSet.schema.ts`)

## Usage

```ts
import { interpretCommand, type PageContext, type AiCompleteFn } from '@/ai';

const pageContext: PageContext = {
  pageSlug: 'home',
  sections: [{ id: 'hero-1', type: 'hero' }],
  componentTypes: ['hero', 'pricing', 'testimonials', 'faq', 'contact'],
  theme: { primary: '#3b82f6' },
  selectedSectionId: 'hero-1',
  locale: 'en',
};

const aiComplete: AiCompleteFn = async (system, user) => {
  const res = await fetch('/api/ai/complete', {
    method: 'POST',
    body: JSON.stringify({ system, user }),
  });
  const data = await res.json();
  return data.text ?? null;
};

const result = await interpretCommand('Rewrite this section shorter', pageContext, aiComplete);
if (result.success) {
  console.log(result.changeSet.operations, result.changeSet.summary);
} else {
  console.error(result.error);
}
```

## Command categories

- Content Editing (rewrite, shorten, tone, CTA, etc.)
- Layout Editing (move, duplicate, delete, spacing, center)
- Theme Editing (primary color, dark theme, buttons, fonts)
- Section Management (add hero, pricing, FAQ, testimonials, contact, gallery)
- E-commerce (add products, categories, discount banner)
- SEO (meta title/description, keywords, headings)
- Language (translate page, bilingual, rewrite in locale)

## Safety

- All AI output is validated with **Zod** against `changeSetSchema`.
- On validation failure the interpreter **retries** (default up to 2 retries).
- No raw HTML/CSS in operations; use structured props and theme tokens.

## Undo

Use `createPageStateSnapshot` before applying a ChangeSet and `pushUndo` so each change is reversible. Restore from `previousState` when the user undoes.

## Quick command suggestions

Use the `<CommandSuggestions />` component next to the instruction input; it exposes buttons: Rewrite text, Add section, Change color, Translate page, Improve SEO.

## Backend & CMS integration

- **Backend:** `POST /panel/projects/{project}/ai-interpret-command` — accepts `instruction` and `page_context` (sections, component_types, theme, locale, etc.), returns `{ success, change_set, summary }`. Implemented in `App\Services\AiInterpretCommandService` and `ProjectAiContentPatchController::interpretCommand()`.
- **CMS (Cms.tsx):** Apply patch dialog has **Run command** (calls the backend, applies ChangeSet to sections and theme via `applyChangeSetToSections` + `setThemeSettingsBase` for `updateTheme`), **Undo** (restores sections and theme from last snapshot). Undo stack is cleared when the user switches to another page.

**Applied operations:** `applyChangeSetToSections` applies locally: `updateSection`, `insertSection`, `deleteSection`, `reorderSection`, `updateText`, `replaceImage`, `updateButton`, and (in Cms) `updateTheme` is applied to theme state. `addProduct`, `removeProduct`, `translatePage`, `generateContent` require backend or broader context.

## AI Agent Tools (project workspace)

The AI can perform **real project file operations** via a unified tools layer (Cursor/Lovable-style).

- **Directory:** `src/ai/tools/` (path alias from `resources/js/ai/tools/`)
- **Executor:** `src/ai/toolExecutor.ts` — receives `(toolName, args, context)`, runs the tool, returns `{ success, error?, data? }`.
- **Logs:** `src/ai/logs/executionLog.ts` — in-memory session log; backend also logs each execution.

### Tools

| Tool | Args | Description |
|------|------|-------------|
| `readFile` | `path` | Read file from workspace |
| `writeFile` | `path`, `content` | Create or overwrite file |
| `createFile` | `path`, `content` | Create new file (same as writeFile) |
| `updateFile` | `path`, `content` | Update existing file (same as writeFile) |
| `deleteFile` | `path` | Delete file |
| `listFiles` | `max_files?` | List files in allowed dirs |
| `searchFiles` | `keyword`, `max_results?` | Search by path/name |
| `reloadPreview` | — | Request preview refresh (call `onReloadPreview` when provided) |

### Allowed scope

All tools operate only inside the active project workspace. Allowed paths: `src/pages`, `src/components`, `src/sections`, `src/layouts`, `src/styles`, `public`. Forbidden: `system`, `builder-core`, `node_modules`, `server`.

### Usage

```ts
import { executeTool, getExecutionLog, type AiToolContext } from '@/ai';

const context: AiToolContext = {
  projectId: project.id,
  userPrompt: 'Add testimonials section',
  onReloadPreview: () => window.dispatchEvent(new Event('preview-reload')),
};

const result = await executeTool('readFile', { path: 'src/pages/home/Page.tsx' }, context);
if (result.success && result.data) {
  console.log(result.data.content);
}

await executeTool('createFile', { path: 'src/sections/TestimonialsSection.tsx', content: '...' }, context);
await executeTool('reloadPreview', {}, context);
```

### Backend

- **Endpoint:** `POST /panel/projects/{project}/ai-tools/execute` — body: `{ tool, args, user_prompt? }`. Implemented in `App\Services\AiTools\AiToolExecutorService` and `ProjectAiToolsController::execute()`.
- **Preview sync:** After create/update/delete, call `reloadPreview` or pass `onReloadPreview` in context so the builder preview refreshes.

## Project Codebase Scanner

Analyzes the current project workspace and returns structured data (pages, sections, components, layouts, styles, page imports) for AI context. **Run before executing user edit requests** so the AI understands the project.

- **Directory:** `src/ai/codebaseScanner/` (path alias from `resources/js/ai/codebaseScanner/`)
- **Main file:** `codebaseScanner.ts`

### Allowed / forbidden

- **Allowed:** `src/pages`, `src/components`, `src/sections`, `src/layouts`, `src/styles`, `public`
- **Forbidden:** `builder-core`, `system`, `node_modules`, `server`

### Output shape

- `pages` — page slugs (e.g. `home`, `about`)
- `sections` — section component names (e.g. `HeroSection`, `Testimonials`)
- `components` — UI component names (e.g. `Header`, `Footer`)
- `layouts` — layout names (e.g. `MainLayout`)
- `styles` — style file paths
- `public` — public asset paths
- `page_structure` — `{ [pageSlug]: string[] }` imports per page (e.g. `home: ['HeroSection', 'FeaturesGrid']`)

### Usage

```ts
import { scanCodebase, structureToContextSummary, invalidateScanCache } from '@/ai';

const result = await scanCodebase({ projectId: project.id });
if (result.success) {
  const summary = structureToContextSummary(result.structure);
  // Send summary + result.structure to AI before planning edits
}
// After file create/update/delete, cache is auto-invalidated by toolExecutor; or call invalidateScanCache(projectId)
```

### Caching

- Results are cached in memory (5 min). Cache is invalidated when any write/create/update/delete tool runs for that project.
- Backend uses `.webu/index.json` with TTL; rescan runs when index is missing or stale.

### Backend

- **Endpoint:** `GET /panel/projects/{project}/workspace/structure` — returns full scan (pages, sections, components, layouts, styles, public, page_structure, imports_sample, file_contents). Implemented in `App\Services\WebuCodex\CodebaseScanner` and `ProjectWorkspaceController::structure()`.
- **Workflow:** Before handling a user request like "Add testimonials section", call `scanCodebase`, build context from `result.structure`, then send context to the AI so it can plan file changes correctly.

## AI Site Planner

Generates a **full website structure** (site name, pages, sections per page) from a single user prompt. Uses the codebase scanner and the active AI provider (OpenAI, Claude). **Does not execute file changes** — the plan is consumed by the tools/execution pipeline (e.g. create pages, insert sections, reload preview).

- **Directory:** `src/ai/sitePlanner/` (path alias from `resources/js/ai/sitePlanner/`)
- **Main file:** `sitePlanner.ts`

### Output shape

- `siteName` — short title for the site
- `pages` — array of `{ name, title, sections }` (name = URL slug; sections = existing Webu component names, e.g. `HeroSection`, `FeaturesSection`, `CTASection`)

### Usage

```ts
import { generateSitePlan, type SitePlan } from '@/ai';

const result = await generateSitePlan(project.id, 'Create website for restaurant');
if (result.success) {
  const { plan, fromFallback } = result;
  // plan.siteName, plan.pages (each with name, title, sections)
  // Use plan with execution pipeline: create pages, insert sections, reload preview
} else {
  // result.plan is still the fallback plan; result.error describes the failure
}
```

### Integration

- Backend runs **CodebaseScanner** (index or full scan) before calling the AI, so the plan reuses existing section components.
- On AI/parse failure the backend returns a **fallback plan** (e.g. home + contact with Hero, Features, CTA, ContactForm).
- Execution (create/update page files with sections) is done by the existing AI tools or `AiProjectFileEditService::executeSitePlan()`; the planner only produces the plan.

### Backend

- **Endpoint:** `POST /panel/projects/{project}/ai/site-plan` — body: `{ prompt }`. Implemented in `App\Services\AiTools\SitePlannerService` and `ProjectSitePlannerController::store()`.
- **Response:** `{ success, plan: { siteName, pages }, from_fallback }`.
- The Site Planner prompt includes **Webu Design System** rules (container, spacing, typography, section composition) so generated plans follow a unified design language.

## Design Intelligence System

Ensures all AI-generated websites follow professional layout, spacing, and typography. The module defines **design rules** that the AI must follow when generating or modifying pages; it does not replace the core builder.

- **Directory:** `src/ai/designSystem/` (path alias from `resources/js/ai/designSystem/`)
- **Main file:** `designRules.ts`

### Rules summary

| Area | Rules |
|------|--------|
| **Containers** | Desktop max-width 1290px, tablet 1024px, mobile 100%. All sections use `<section><div class="container">...</div></section>`. Header/footer same. |
| **Spacing** | Section 80px top/bottom; medium 60px; small 40px. |
| **Typography** | H1 48/36/28px, H2 36/28/24px, H3 24px, paragraph 16px, line-height 1.5. |
| **Grid** | 12-column desktop; feature 3 or 4 cols; product 4 cols; mobile single column. |
| **Breakpoints** | Tablet 1024px, mobile 768px; below tablet 2-col → 1-col. |
| **Section composition** | Landing: Hero → Features → Social Proof → CTA. Business: Hero → Services → Gallery → Testimonials → Contact. |

### Usage

```ts
import { getDesignRulesForPrompt, CONTAINER_WIDTHS, SPACING, TYPOGRAPHY } from '@/ai';

// Include in any prompt that generates or modifies layout/code
const systemPrompt = getDesignRulesForPrompt();

// Programmatic use (e.g. builder or validation)
const desktopMax = CONTAINER_WIDTHS.desktop; // '1290px'
const sectionPadding = SPACING.section;      // { top: '80px', bottom: '80px' }
```

### Integration

- **Site Planner:** Backend injects design rules into the planning prompt so page/section plans follow container and section-composition rules. Workflow: User prompt → AI Site Planner + Design System rules → section planning → component placement.
- **Code generation:** `AiProjectFileEditService` (project edit and full-site generation) includes the same rules so generated Page.tsx and section markup use the container structure and avoid random widths. Example pattern: `<section class="section"><div class="container">...</div></section>`.
- **Backend:** `App\Services\AiTools\DesignRulesService::getPromptFragment()` returns the rules text; used by `SitePlannerService` and `AiProjectFileEditService`.

### Testing / verification

Generate sites with prompts such as "Create restaurant website", "Create SaaS landing page", "Create photography portfolio", then verify generated pages follow: container width rules (max-width 1290/1024/100%), spacing (80/60/40px), typography scale (H1/H2/H3/paragraph), and 12-column grid with single column on mobile. All sections should use the inner `container` div; no fixed section widths (e.g. `width: 1600px`).

## AI Component Generator

Generates new reusable section components when the AI determines a required component does not exist (e.g. user says "Add pricing section" but no `PricingSection`). Generated files go in `src/sections/`, follow Design Intelligence rules, and are created via the existing Agent Tools (`createFile`). The builder then sees new sections after the next scan (scan cache is invalidated after create).

- **Directory:** `src/ai/componentGenerator/` (path alias from `resources/js/ai/componentGenerator/`)
- **Main file:** `componentGenerator.ts`

### Workflow

1. User prompt (e.g. "Add testimonials section") or Site Planner plan includes a section that may not exist.
2. **Component check:** Call `ensureSectionExists(projectId, sectionName, context)`. Backend checks scan; if section already exists, returns `{ reused: true }` and no file is created.
3. **If missing:** Backend generates TSX (Design System compliant, content hints by type: testimonials, pricing, team, services, etc.), returns content; frontend calls `createFile` with that content.
4. Scan cache is invalidated so the builder registry includes the new component.
5. Caller then inserts the section into the page (e.g. update Page.tsx) and calls `reloadPreview` (or passes `onReloadPreview` in context).

### Usage

```ts
import { ensureSectionExists, executeTool, type AiToolContext } from '@/ai';

const context: AiToolContext = {
  projectId: project.id,
  userPrompt: 'Add pricing section',
  onReloadPreview: () => window.dispatchEvent(new Event('preview-reload')),
};

const result = await ensureSectionExists(project.id, 'PricingSection', context);
if (result.created) {
  // Section file created; next add it to the page and reload preview
}
if (result.reused) {
  // Section already existed; use result.path to add to page
}
if (!result.created && !result.reused) {
  console.error(result.error);
}
```

### Naming and template

- Sections use PascalCase and must end with `Section` (e.g. `HeroSection`, `PricingSection`). Use `normalizeSectionName('pricing')` → `PricingSection`.
- Generated components follow the standard template: `<section className="section"><div className="container">...</div></section>`; no inline layout widths.

### Integration with Site Planner

When executing a site plan, for each section in the plan call `ensureSectionExists` before building page files. Then update each page (e.g. via `updateFile` on `src/pages/home/Page.tsx`) to import and render the sections, then `reloadPreview`.

### Backend

- **Endpoint:** `POST /panel/projects/{project}/ai/generate-component` — body: `{ section_name, user_prompt? }`. Implemented in `App\Services\AiTools\ComponentGeneratorService` and `ProjectComponentGeneratorController::store()`.
- **Response:** `{ success, already_exists, path, content? }`. If `already_exists`, do not overwrite; reuse existing component.
- **Tool executor:** After `writeFile`/`deleteFile`, the backend invalidates the codebase index so the next scan (e.g. for Site Planner or builder) includes the new file.

### Testing

Test prompts: "Add testimonials section", "Add pricing section", "Add team section", "Add services section". Verify: component is created under `src/sections/`, section is inserted into the page (by your flow), preview reloads.

## Final stability validation (Phase 12)

Use these prompts to verify the full AI pipeline:

| Prompt | Expected behavior |
|--------|-------------------|
| Create SaaS landing page | Site plan or project edit produces pages (e.g. home) with Hero, Features, Social Proof, CTA; container/spacing/typography follow design rules. |
| Create restaurant website | Plan includes home, about, menu, gallery, contact; sections reuse existing components or generate missing ones. |
| Add testimonials section | If TestimonialsSection missing, component generator creates it; section is inserted into the target page; preview reloads. |
| Change hero text | Project edit or content patch updates the hero section text; file changes and preview reflect the edit. |

Checks: (1) AI understands the project (scanner ran). (2) Components are reused when present. (3) Missing sections are generated and created. (4) Project files are modified correctly. (5) Preview reflects changes (reload called).

For detailed step-by-step validation (how to run each prompt, what to verify, success criteria), see **docs/WEBU_AI_PHASE12_VALIDATION.md** in the Install project.

## Tests

- **Frontend (Vitest):** `npm run test:run -- resources/js/ai/__tests__` — ChangeSet schema, `applyChangeSetToSections`, undo support (20 tests).
- **Backend (PHPUnit):** `tests/Feature/Cms/AiInterpretCommandTest.php` — interpret endpoint validation, auth, and mocked success/error responses.
