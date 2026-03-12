# Real Project Codebase (AI-Editable Workspace)

Each Webu project has a **real code workspace** so that AI can read and modify actual project files (Lovable/Codex-style).

## Location

- **Path:** `storage/workspaces/{project_id}/`
- Created when the site is provisioned (or via API).

## Structure

```
workspaces/{project_id}/
├── src/
│   ├── pages/
│   │   ├── home/
│   │   │   └── Page.tsx
│   │   ├── shop/
│   │   │   └── Page.tsx
│   │   └── contact/
│   │       └── Page.tsx
│   ├── components/
│   │   ├── Header.tsx
│   │   └── Footer.tsx
│   ├── sections/
│   │   ├── HeroSection.tsx
│   │   ├── ProductGridSection.tsx
│   │   └── ...
│   ├── layouts/
│   │   └── SiteLayout.tsx
│   └── styles/
│       └── globals.css
├── public/
└── cms/
    └── pageStructure.json   # Snapshot of pages/sections for reference
```

## How It Works

1. **Provisioning:** When a site is provisioned (`SiteProvisioningService::provisionForProject`), `ProjectWorkspaceService::initializeProjectCodebase` is called. It:
   - Creates the workspace directory
   - Seeds the template (minimal React structure)
   - Generates `src/pages/{slug}/Page.tsx` from current CMS pages and sections
   - Writes section components under `src/sections/`
   - Writes `cms/pageStructure.json`

2. **AI / Editor:** Use the workspace API to read/write/delete files:
   - `POST /panel/projects/{project}/workspace/initialize` – (re)generate codebase from CMS
   - `GET /panel/projects/{project}/workspace/parsed-pages` – get page structure parsed from code
   - `GET /panel/projects/{project}/workspace/file?path=src/pages/home/Page.tsx` – read file
   - `POST /panel/projects/{project}/workspace/file` – write file (body: `path`, `content`)
   - `DELETE /panel/projects/{project}/workspace/file?path=...` – delete file

3. **Builder sync:** `ProjectCodeParserService::parseAllPages` reads `src/pages/*/Page.tsx` so project-edit/code-edit flows can inspect the mirrored workspace structure. For the visual builder, CMS `PageRevision` remains authoritative; parsed workspace code is auxiliary context only.

4. **Preview:** After file changes, trigger a build (e.g. `POST /builder/projects/{project}/build`) so the preview is rebuilt. When using local workspace fallback, `BuilderService::copyWorkspaceToPreview` copies from `storage/workspaces/{project_id}` to `storage/app/previews/{project_id}`.

## Safe Editing

- AI should only modify files under the project workspace.
- When appending a section: add the import and the JSX tag; do not break existing imports or layout.
- Section type → component name mapping is in `ProjectWorkspaceService::SECTION_TYPE_TO_COMPONENT` (and frontend `sectionTagMap.ts`).

## Relation to Builder (Go)

- The **external Builder** service may hold its own copy of the workspace. File operations in the panel (`/builder/projects/{project}/file`) go to the Builder API.
- The **workspace API** above reads/writes the **Laravel** workspace at `storage/workspaces/{project_id}`. Use it when the Builder is offline or when you want to guarantee edits hit the same disk the app uses for `copyWorkspaceToPreview`.
