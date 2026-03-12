# Webu Chat — Full Cycle (Lovable-Level)

This document describes how the Webu chat works end-to-end and how it compares to a Lovable-style flow.

---

## 1. Where the chat lives

- **Page:** `resources/js/Pages/Chat.tsx` (project chat / workspace).
- **Backend entry:** `POST /panel/projects/{project}/ai-project-edit` (body: `{ message }`).
- **Controller:** `ProjectAiProjectEditController` → `AiProjectFileEditService::run()`.

---

## 2. Full cycle (user sends a message)

1. **User** types a message in the chat (e.g. "Create website for restaurant", "Add testimonials section") and sends.
2. **Frontend (Chat.tsx):**
   - Adds user message to the thread.
   - If no element is selected: tries **interpret path** (analyze page structure → interpret command → execute change set on CMS). If that fails or has no ops, calls **tryAiProjectEdit()**.
   - **tryAiProjectEdit()** sends `POST …/ai-project-edit` with `{ message }`.
3. **Backend (AiProjectFileEditService::run()):**
   - **Scan:** `CodebaseScanner::getScanFromIndex()` or `scan()` so the AI sees pages, sections, components, layouts.
   - **Full-site?** If the message matches full-website triggers (e.g. "create a website", "website for", "landing page", "saas landing", "restaurant website", "portfolio website", …), runs **runFullSiteGeneration()**:
     - **Plan:** AI returns a site plan (pages + sections per page) with design rules.
     - **Ensure sections:** For any section in the plan that does not exist, **ComponentGeneratorService** generates the TSX and writes the file.
     - **Execute:** Writes/updates each `src/pages/{slug}/Page.tsx` with the planned sections.
     - **Log:** ExecutionLogger records user request, summary, and changes.
   - **Otherwise:** **plan()** (multi-file refactor or single-page edit) → AI returns operations → **execute** (create/update/delete files) → **log**.
4. **Response** to frontend: `{ success, summary, changes, files_changed?, error?, no_change_reason? }`.
5. **Frontend:**
   - If **success:** shows assistant message with summary and change list; if **files_changed** → `setPreviewRefreshTrigger(Date.now())` and `setFileRefreshTrigger` so **preview and file tree refresh**.
   - If **!success:** shows **backend error** (or no_change_reason) in the chat so the user sees e.g. "AI is not configured" or "Ensure workspace is initialized".
   - On **network/server error:** shows a clear message (e.g. workspace not initialized, AI not configured) instead of failing silently.

---

## 3. What was improved (Lovable-level)

| Area | Before | After |
|------|--------|--------|
| **Full-site triggers** | Only phrases like "create a website", "website for". | Added: "landing page", "saas landing", "saas startup", "restaurant website", "portfolio website", "e-commerce", etc., so "Create SaaS landing page" and "Create restaurant website" trigger full-site generation. |
| **Error visibility** | On ai-project-edit failure, frontend returned false and fell through; user often saw a generic or wrong message. | On **success: false**, the backend **error** (or no_change_reason) is shown in the chat. On **catch**, a clear message is shown (including 422 body or "workspace initialized / AI configured"). |
| **Preview + files** | Already refreshed when `files_changed` was true. | Unchanged; backend sends `files_changed: true` when `changes` is non-empty. |
| **Component generator** | Used only when frontend called ensureSectionExists. | Backend **runFullSiteGeneration** now calls **ensurePlannedSectionsExist** so missing sections (e.g. PricingSection, TestimonialsSection) are generated and written before building pages. |

---

## 4. How to test the chat manually

1. Open a project that has **workspace initialized** (e.g. has `src/pages`, `src/sections`).
2. Ensure **AI is configured** (Admin → Integrations: OpenAI or Claude).
3. In the project chat, try:
   - **"Create website for restaurant"** → expect site plan + pages (home, about, menu, gallery, contact) and new/updated Page.tsx files; preview refreshes.
   - **"Create SaaS landing page"** → expect full-site or plan with Hero, Features, CTA; preview refreshes.
   - **"Add testimonials section"** → if TestimonialsSection is missing, it is generated and used; preview refreshes.
4. If something fails, the chat should show the **exact error** (e.g. "Could not read project structure. Ensure workspace is initialized.", "AI is not configured.").

---

## 5. Automated test

- **Tests:** `tests/Feature/AiProjectEditEndpointTest.php`
  - Requires auth.
  - Returns valid JSON shape (success, summary, changes).
  - Covers full-website trigger phrases (SaaS, restaurant, agency, portfolio).
- Run: `php artisan test tests/Feature/AiProjectEditEndpointTest.php`.

---

## 6. Comparison with Lovable

- **Single entry:** One message → one backend call (ai-project-edit) that does scan → plan → generate missing sections → execute → log. Same idea as a single "build" request.
- **Preview sync:** After any file change, `files_changed` triggers preview and file tree refresh.
- **Errors:** User sees backend errors in the chat instead of silent or generic failure.
- **Full-site and sections:** Full-website prompts trigger full-site generation; missing sections are generated automatically by the backend so the builder is not limited to a fixed library.

The system is stable, uses the existing architecture (no new AI stack), and is ready for production use with the chat as the main interface.
