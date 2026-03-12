# Webu AI System — Full Architecture Report (Phase 1)

**Generated:** 2026-03-08  
**Scope:** Backend + Frontend AI modules, builder, preview, workspace, providers.

---

## 1. Core Modules

| Module | Location | Responsibility |
|--------|----------|----------------|
| **InternalAiService** | `app/Services/InternalAiService.php` | Central AI gateway: provider resolution, `completeForProjectEdit()`, suggestions, cache. Uses `AiProvider` model and SystemSetting for default/internal provider. |
| **AiProjectFileEditService** | `app/Services/AiProjectFileEditService.php` | Full project edit pipeline: scan → plan (or full-site plan) → execute file ops → ExecutionLogger. Uses CodebaseScanner, InternalAiService, FileEditor, DesignRulesService. |
| **AiToolExecutorService** | `app/Services/AiTools/AiToolExecutorService.php` | Executes single tools (readFile, writeFile, createFile, updateFile, deleteFile, listFiles, searchFiles, reloadPreview) inside project workspace. Invalidates CodebaseScanner index after write/delete. |
| **SitePlannerService** | `app/Services/AiTools/SitePlannerService.php` | Generates structured site plan (siteName, pages with sections) from user prompt. Uses CodebaseScanner, InternalAiService, DesignRulesService. Returns plan only; no file changes. |
| **DesignRulesService** | `app/Services/AiTools/DesignRulesService.php` | Returns design rules as prompt fragment (container, spacing, typography, grid, breakpoints, section composition). Used by SitePlannerService and AiProjectFileEditService. |
| **ComponentGeneratorService** | `app/Services/AiTools/ComponentGeneratorService.php` | Generates new section component TSX when missing. Uses CodebaseScanner, InternalAiService, DesignRulesService. Returns content; file creation is done via Agent Tools from frontend. |
| **CodebaseScanner** | `app/Services/WebuCodex/CodebaseScanner.php` | Scans project workspace: pages, sections, components, layouts, styles, public, imports_sample, file_contents. Supports index (`.webu/index.json`) with TTL; invalidateIndex() after file changes. |
| **ExecutionLogger** | `app/Services/WebuCodex/ExecutionLogger.php` | Logs AI project edits to `cms/ai_project_edit_log.jsonl`: user_request, summary, changes (path, op, old/new content), timestamp. |
| **FileEditor** | `app/Services/WebuCodex/FileEditor.php` | Low-level file write with optional rollback. Used by AiProjectFileEditService. |
| **PathRules** | `app/Services/WebuCodex/PathRules.php` | Allowed prefixes: src/pages, src/components, src/sections, src/layouts, src/styles, public. Forbidden: builder-core, system, node_modules, server, .git. |

---

## 2. AI Pipeline (High Level)

```
User prompt
    → (optional) CodebaseScanner: getScanFromIndex() or scan() + writeIndex()
    → Site Planner: generate(project, prompt) → { siteName, pages[] }
    → (optional) Component Generator: ensureSectionExists per section
    → AiProjectFileEditService::run() OR frontend executeTool() sequence
        → plan() with design rules + project structure
        → AI returns JSON (operations or full-site pages)
        → executeSitePlan() / FileEditor writes
        → ExecutionLogger::log()
    → AiToolExecutorService::execute() for readFile/writeFile/... (frontend-driven)
    → CodebaseScanner::invalidateIndex() after write/delete
    → reloadPreview (frontend callback or tool)
```

- **Project edit (single entry):** `POST panel/projects/{project}/ai-project-edit` → AiProjectFileEditService::run() (scan → plan → **ensure planned sections exist via Component Generator** → execute → log).
- **Site plan only:** `POST panel/projects/{project}/ai/site-plan` → SitePlannerService::generate().
- **Generate component:** `POST panel/projects/{project}/ai/generate-component` → ComponentGeneratorService::generate().
- **Tool execution:** `POST panel/projects/{project}/ai-tools/execute` → AiToolExecutorService::execute().

---

## 3. File Editing Logic

- **Allowed paths:** PathRules::ALLOWED_PREFIXES (src/pages, src/components, src/sections, src/layouts, src/styles, public). Normalized, no `..`.
- **Project workspace:** ProjectWorkspaceService ensures workspace root; read/write via workspace methods.
- **Scan before edit:** AiProjectFileEditService and SitePlannerService/ComponentGeneratorService use CodebaseScanner so the AI sees current pages/sections/components.
- **Index invalidation:** AiToolExecutorService calls CodebaseScanner::invalidateIndex($project) after writeFile and deleteFile so next scan is fresh.

---

## 4. Builder & Component Registry / How Pages Are Created

- **How pages are created:** Pages are mirrored into workspace React components under `src/pages/{slug}/Page.tsx`. They are created or updated by (1) `ProjectWorkspaceService::generateFromCms()` when projecting authoritative CMS revisions into the workspace, or (2) frontend/backend file tools for project-edit/code-edit flows. For the visual builder, CMS `PageRevision` is the source of truth; the workspace file system is an auxiliary projection for code context and file editing.
- **BuilderService** (`app/Services/BuilderService.php`): AI config for user, template/builder logic. Component registry is driven by **project files**: sections live in `src/sections/*.tsx`. No separate “registry” table; discovery is via CodebaseScanner (backend) and frontend scan/structure API.
- **Project structure API:** `GET panel/projects/{project}/workspace/structure` returns scan (pages, sections, components, layouts, styles, page_structure, imports_sample, file_contents). Frontend codebaseScanner uses this and caches; builder/UI can use the same to list insertable sections.
- **New sections:** Created by Component Generator + createFile tool (or by AiProjectFileEditService::ensurePlannedSectionsExist in full-site flow); after create, scan cache/index invalidated so new section appears in next structure response.

---

## 5. Preview Reload & Preview Rendering

- **How preview rendering works:** The builder preview typically loads the project app (e.g. Vite dev server or built assets) in an iframe. The project source lives in the workspace (`src/pages/*`, `src/sections/*`, etc.). When files are changed by AI tools, the preview must reload to show the new content; there is no separate “preview build” service in the AI layer—reload is the trigger.
- **Tool:** `reloadPreview` in AiToolExecutorService returns `{ reload_requested: true }`; no server-side preview build. Frontend must call `onReloadPreview` (e.g. dispatch event or iframe reload) when provided in AiToolContext.
- **Flow:** After createFile/updateFile/writeFile/deleteFile, frontend toolExecutor automatically invokes context.onReloadPreview when present so the builder preview iframe refreshes.

---

## 6. Provider Architecture

- **AiProvider** model: types OPENAI, ANTHROPIC, CLAUDE, GROK, DEEPSEEK, ZHIPU. Base URLs, default models, pricing. Claude uses Anthropic API (TYPE_CLAUDE and TYPE_ANTHROPIC).
- **InternalAiService::getProvider():** Resolves active provider (e.g. from SystemSetting internal_ai_provider_id or default_ai_provider_id). Used for project edit and all AI completion calls.
- **Multiple providers:** Supported via existing interface; no new architecture. OpenAI and Claude (Anthropic) are integrated; other types exist in AiProvider.

---

## 7. Frontend AI Modules (`resources/js/ai/`)

| Module | Role |
|--------|------|
| **toolExecutor** | executeTool(name, args, context); invalidates scan cache after write/create/update/delete; logs to executionLog. |
| **tools/** | readFile, writeFile, createFile, updateFile, deleteFile, listFiles, searchFiles, reloadPreview — call backend ai-tools/execute. |
| **codebaseScanner** | scanCodebase(), structureToContextSummary(), invalidateScanCache(); GET workspace/structure. |
| **sitePlanner** | generateSitePlan(projectId, prompt) → POST ai/site-plan. |
| **designSystem** | getDesignRulesForPrompt(), CONTAINER_* , SPACING, TYPOGRAPHY, etc. |
| **componentGenerator** | ensureSectionExists(projectId, sectionName, context) → POST ai/generate-component then createFile tool. |
| **logs/executionLog** | In-memory session log (tool, args, success, path, userPrompt, timestamp). |
| **commands/** | interpretCommand, ChangeSet-based editing (separate from project file edit). |

---

## 8. Routes (AI & Workspace)

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| POST | panel/projects/{project}/ai-project-edit | ProjectAiProjectEditController | Full project edit (scan → plan → execute → log). |
| POST | panel/projects/{project}/ai-tools/execute | ProjectAiToolsController | Single tool execution (readFile, writeFile, ...). |
| GET  | panel/projects/{project}/workspace/structure | ProjectWorkspaceController | Codebase scan for AI/builder. |
| POST | panel/projects/{project}/ai/site-plan | ProjectSitePlannerController | Site plan generation. |
| POST | panel/projects/{project}/ai/generate-component | ProjectComponentGeneratorController | Generate section component TSX. |

---

## 9. Execution Logging (Phase 11)

- **User request:** Stored in ExecutionLogger entry as `user_request`; in tool log as `user_prompt`.
- **Files modified:** ExecutionLogger `changes[]` has `path` and `op` per change; tool log has `path`.
- **Actions executed:** ExecutionLogger `changes[].op` (createFile, updateFile, deleteFile); tool log has `tool` (readFile, writeFile, createFile, etc.).
- **Timestamp:** ExecutionLogger `timestamp` (ISO8601); tool log `timestamp`.
- **Backend:** ExecutionLogger (project edit) writes to `cms/ai_project_edit_log.jsonl` per project. AiToolExecutorService logs each tool run to Log::channel('single') (project_id, tool, timestamp, success, user_prompt, path).
- **Frontend:** executionLog.ts keeps in-memory array of ToolExecutionLogEntry (tool, args, timestamp, success, error, path, userPrompt).

---

## 10. Data Flow Summary

1. **User prompt** → Frontend or direct API.
2. **Codebase scanner** runs (GET structure or from index) before planning/generation.
3. **Site planner** (optional) returns pages + sections; design rules injected in prompt.
4. **Component generator** (optional) ensures missing sections exist (generate-component API + createFile).
5. **File operations** via AiProjectFileEditService (backend) or executeTool (frontend): readFile, writeFile, createFile, updateFile, deleteFile, listFiles, searchFiles.
6. **Index invalidation** after writes so next scan sees new files.
7. **Preview reload** via frontend onReloadPreview or reloadPreview tool.
8. **Logging:** ExecutionLogger for project-edit runs; Log for each tool execution; frontend executionLog for session.

This document serves as the structural baseline for refinement and validation (Phases 2–12).

---

## Phase 2 — Redundancy & Safety Check

- **Debug code:** No `console.log`/`dd`/`dump`/`var_dump` in AI or WebuCodex runtime code. README examples use `console.log` only in documentation snippets.
- **Duplicate logic:** `extractJson()` exists in both AiProjectFileEditService and SitePlannerService (same behavior). Optional future refactor: move to a shared helper; not removed to avoid behavior change.
- **Dead modules:** All listed AI/WebuCodex modules are referenced by controllers or other services. No dead AI modules identified.
- **Dependency check:** InternalAiService, CodebaseScanner, PathRules, ProjectWorkspaceService are core; AiTools services depend on them. No circular dependencies.
- **Conclusion:** No unsafe or obsolete code removed; system kept as-is for stability.

---

## Phase 3 — Stabilization

- **AI responses:** All AI calls use InternalAiService; provider is resolved once per request. Fallbacks: Site Planner and Component Generator return fallback plan / fallback TSX on empty or invalid AI response.
- **File operations:** PathRules restrict all writes to allowed prefixes; AiToolExecutorService and FileEditor use the same workspace layer. No path traversal.
- **Preview reload:** After createFile/updateFile/writeFile/deleteFile, frontend toolExecutor automatically calls `context.onReloadPreview?.()` when provided, so preview always reflects file changes. Documented in README.
- **Builder sync:** CodebaseScanner index is invalidated after every writeFile/deleteFile in AiToolExecutorService, so next structure request returns fresh data and builder sees new sections.

---

## Phases 4–11 — Verification Summary

| Phase | Requirement | Status |
|-------|-------------|--------|
| **4 — AI provider** | OpenAI + Claude within existing architecture | Done. AiProvider::TYPE_OPENAI, TYPE_ANTHROPIC, TYPE_CLAUDE; InternalAiService::getProvider(); no new provider abstraction. |
| **5 — Agent tools** | readFile, writeFile, createFile, updateFile, deleteFile, listFiles, searchFiles, reloadPreview | Done. AiToolExecutorService + frontend tools; path rules; integrated in pipeline. |
| **6 — Codebase scanner** | Pages, sections, components, layouts, styles; before AI; project dir only | Done. CodebaseScanner; GET workspace/structure; PathRules restrict scope. |
| **7 — Site planner** | Pages, sections per page, layout order; reuse components | Done. SitePlannerService + DesignRulesService; generateSitePlan() frontend. Full-site backend flow triggers Component Generator for missing sections before executeSitePlan. |
| **8 — Design intelligence** | Container, spacing, typography, grid, breakpoints | Done. DesignRulesService + designSystem/designRules.ts; injected in planner and project edit. |
| **9 — Component generator** | Generate missing sections; design rules; sections dir; reuse when possible | Done. ComponentGeneratorService + ensureSectionExists(); createFile via tools. |
| **10 — System integration** | Workflow: prompt → scanner → planner → component check → tools → file change → reload | Done. Documented in README and Section 2 above. |
| **11 — Execution logging** | User request, files modified, actions, timestamp | Done. ExecutionLogger (project edit log file); AiToolExecutorService::logExecution() (Log channel); frontend executionLog (session). |
| **12 — Final validation** | Test prompts: SaaS landing, restaurant, add section, change hero | Documented in README "Final stability validation" and in **docs/WEBU_AI_PHASE12_VALIDATION.md** (step-by-step validation instructions and success criteria). |

---

## Task completion checklist (verification)

Use this to confirm every required piece exists. Paths relative to `Install/`.

### Backend (PHP)

| Item | Path | Purpose |
|------|------|---------|
| InternalAiService | app/Services/InternalAiService.php | AI gateway, provider resolution |
| AiProjectFileEditService | app/Services/AiProjectFileEditService.php | Scan → plan → execute → log |
| AiToolExecutorService | app/Services/AiTools/AiToolExecutorService.php | readFile, writeFile, createFile, updateFile, deleteFile, listFiles, searchFiles, reloadPreview |
| SitePlannerService | app/Services/AiTools/SitePlannerService.php | Site plan from prompt |
| DesignRulesService | app/Services/AiTools/DesignRulesService.php | Design prompt fragment |
| ComponentGeneratorService | app/Services/AiTools/ComponentGeneratorService.php | Generate missing section TSX |
| CodebaseScanner | app/Services/WebuCodex/CodebaseScanner.php | Project structure scan, index |
| ExecutionLogger | app/Services/WebuCodex/ExecutionLogger.php | Project edit log (jsonl) |
| PathRules | app/Services/WebuCodex/PathRules.php | Allowed paths |
| FileEditor | app/Services/WebuCodex/FileEditor.php | File write/rollback |
| ProjectAiProjectEditController | app/Http/Controllers/ProjectAiProjectEditController.php | ai-project-edit |
| ProjectAiToolsController | app/Http/Controllers/ProjectAiToolsController.php | ai-tools/execute |
| ProjectSitePlannerController | app/Http/Controllers/ProjectSitePlannerController.php | ai/site-plan |
| ProjectComponentGeneratorController | app/Http/Controllers/ProjectComponentGeneratorController.php | ai/generate-component |
| ProjectWorkspaceController | app/Http/Controllers/ProjectWorkspaceController.php | workspace/structure |
| AiProvider (model) | app/Models/AiProvider.php | OpenAI, Claude, etc. |

### Routes (web.php)

| Name | Method | Path |
|------|--------|------|
| panel.projects.ai-project-edit | POST | panel/projects/{project}/ai-project-edit |
| panel.projects.ai-tools.execute | POST | panel/projects/{project}/ai-tools/execute |
| panel.projects.workspace.structure | GET | panel/projects/{project}/workspace/structure |
| panel.projects.ai.site-plan | POST | panel/projects/{project}/ai/site-plan |
| panel.projects.ai.generate-component | POST | panel/projects/{project}/ai/generate-component |

### Frontend (resources/js/ai/)

| Item | Path |
|------|------|
| toolExecutor | toolExecutor.ts |
| executionLog | logs/executionLog.ts |
| readFileTool | tools/readFileTool.ts |
| writeFileTool | tools/writeFileTool.ts |
| createFileTool | tools/createFileTool.ts |
| updateFileTool | tools/updateFileTool.ts |
| deleteFileTool | tools/deleteFileTool.ts |
| listFilesTool | tools/listFilesTool.ts |
| searchFilesTool | tools/searchFilesTool.ts |
| reloadPreviewTool | tools/reloadPreviewTool.ts |
| codebaseScanner | codebaseScanner/codebaseScanner.ts, types.ts, index.ts |
| sitePlanner | sitePlanner/sitePlanner.ts, types.ts, index.ts |
| designSystem | designSystem/designRules.ts, types.ts, index.ts |
| componentGenerator | componentGenerator/componentGenerator.ts, types.ts, index.ts |
| tools api | tools/api.ts, types.ts |

### Behaviour checks (all implemented)

- [x] Scanner runs before AI (getScanFromIndex or scan in AiProjectFileEditService, SitePlannerService, ComponentGeneratorService).
- [x] Design rules injected in Site Planner and AiProjectFileEditService prompts (DesignRulesService::getPromptFragment()).
- [x] After writeFile/deleteFile (backend), CodebaseScanner::invalidateIndex($project) is called (AiToolExecutorService).
- [x] After createFile/updateFile/writeFile/deleteFile (frontend), invalidateScanCache(projectId) and onReloadPreview?.() are called (toolExecutor.ts).
- [x] ExecutionLogger::log() called for every successful project edit (no_change, operations, full-site including generated sections).
- [x] AiToolExecutorService logs each tool run to Log::channel('single').
- [x] Full-site generation (ai-project-edit) triggers Component Generator for any section in the plan that does not exist; generated section files are included in changes and in ExecutionLogger.

**Phase 12 runbook:** See **docs/WEBU_AI_PHASE12_VALIDATION.md** for step-by-step test instructions and success criteria.

---

## Final task checklist (all deliverables)

Use this to confirm no task was left undone. Every line must be true.

**Phase 1 — Full codebase analysis**
- [x] Structural report exists (this document).
- [x] Core modules, AI pipeline, file editing logic, builder/component registry, preview reload, provider architecture documented.

**Phase 2 — Redundant/unsafe code**
- [x] Debug code checked (none in AI/WebuCodex runtime).
- [x] Duplicate/dead code noted; no breaking removals.

**Phase 3 — Stabilize AI**
- [x] AI responses use InternalAiService; fallbacks in place.
- [x] File operations use PathRules; no path traversal.
- [x] Preview reload triggered after file-changing tools when onReloadPreview provided.
- [x] Builder sync via index invalidation after write/delete.

**Phase 4 — AI provider**
- [x] OpenAI supported (AiProvider::TYPE_OPENAI).
- [x] Claude supported (TYPE_ANTHROPIC, TYPE_CLAUDE); existing interface only.

**Phase 5 — Agent tools**
- [x] readFile, writeFile, createFile, updateFile, deleteFile, listFiles, searchFiles, reloadPreview implemented (backend + frontend).
- [x] Tools operate inside project workspace only; integrated in pipeline.

**Phase 6 — Codebase scanner**
- [x] Scanner collects pages, sections, components, layouts, styles.
- [x] Scanner runs before AI in AiProjectFileEditService, SitePlannerService, ComponentGeneratorService.
- [x] Scanner restricted to project directory (PathRules).

**Phase 7 — Site planner**
- [x] SitePlannerService generates siteName, pages, sections per page, layout order.
- [x] Reuses existing components; design rules in prompt.
- [x] Full-site backend flow triggers Component Generator for missing sections.

**Phase 8 — Design intelligence**
- [x] Container width (1290/1024/100%), spacing (80/60/40), typography, grid, breakpoints defined.
- [x] DesignRulesService + designSystem/designRules.ts; injected in planner and project edit.

**Phase 9 — Component generator**
- [x] ComponentGeneratorService generates missing section TSX; design rules applied.
- [x] Generated files in src/sections/; naming (PascalCase + Section).
- [x] Existing components reused (already_exists); backend ensurePlannedSectionsExist in full-site flow.

**Phase 10 — System integration**
- [x] Workflow: User prompt → Codebase scanner → Site planner (optional) → Component analysis → Agent tool execution → File modification → Preview reload.
- [x] Documented in README and WEBU_CHAT_FULL_CYCLE.md; chat uses ai-project-edit.

**Phase 11 — Execution logging**
- [x] User request, files modified, actions executed, timestamp logged.
- [x] ExecutionLogger (project edit); AiToolExecutorService::logExecution (each tool); frontend executionLog.

**Phase 12 — Final validation**
- [x] Test prompts documented (SaaS landing, restaurant, add testimonials, change hero).
- [x] docs/WEBU_AI_PHASE12_VALIDATION.md with step-by-step instructions and success criteria.
- [x] Feature test: tests/Feature/AiProjectEditEndpointTest.php (auth, response shape, full-site triggers).

**Chat / Lovable-level**
- [x] Full-site triggers extended (landing page, SaaS, restaurant, portfolio, e-commerce).
- [x] Chat shows backend error when ai-project-edit fails (success: false or catch).
- [x] docs/WEBU_CHAT_FULL_CYCLE.md describes full cycle and improvements.

---

## Expected result (task completion)

After completing all phases, the Webu AI system is a **fully functional AI development agent** capable of building and modifying websites directly inside the project workspace. The system remains **stable, clean, and maintainable**, leveraging the **existing architecture** (InternalAiService, ProjectWorkspaceService, PathRules, builder discovery via scan). No new AI stack or replacement architecture was introduced; all improvements **refine and extend** the current system (Site Planner, Design Intelligence, Component Generator, Agent Tools, Scanner, Logging) and are integrated into the same pipeline and routes.
