# Webu v2 CMS Field Ownership Matrix

This matrix is specific to the current Webu repo and its runtime split between:

- CMS `PageRevision`
- page metadata on `pages`
- site-level settings on `sites.theme_settings`
- workspace files under `storage/workspaces/{project_id}`

| Field type | Owner | Persistence location | Sync direction | Repo-specific examples |
| --- | --- | --- | --- | --- |
| Section text copy | CMS | `page_revisions.content_json.sections[*].props` | CMS -> workspace mirror | Hero `title`, `subtitle`, Heading `body`, CTA copy, testimonial text |
| Section image/media selections | CMS | `page_revisions.content_json.sections[*].props` | CMS -> workspace mirror | Hero image, content image, footer logo asset URL, gallery item image |
| Header/footer content | CMS | `page_revisions.content_json.sections[*].props` plus site menus/settings where applicable | CMS -> workspace mirror | Header `logoText`, footer description, nav labels, footer link labels |
| Repeater content collections | CMS | `page_revisions.content_json.sections[*].props` | CMS -> workspace mirror | Features `items`, FAQ rows, testimonials, cards, generic content blocks |
| Form/contact copy | CMS | `page_revisions.content_json.sections[*].props` | CMS -> workspace mirror | Contact form title, subtitle, placeholder labels, submit label |
| Page SEO fields | CMS | `pages.seo_title`, `pages.seo_description` and mirrored page binding metadata | CMS -> workspace mirror | SEO title/description edited from current page meta flow |
| Page title | CMS | `pages.title` | CMS -> workspace mirror | Page list label, current page title in CMS |
| Page slug / route identity | Builder structure | `pages.slug` | CMS revision/page meta -> workspace route mirror | `home`, `about`, campaign page slug |
| Section order and page composition | Builder structure | `page_revisions.content_json.sections[*]` | CMS revision -> workspace mirror | Reorder hero/features/CTA, add/remove testimonial section |
| Section visual/layout props | Builder structure | `page_revisions.content_json.sections[*].props` | CMS revision -> workspace mirror | `variant`, spacing, alignment, layout tokens, header/footer variant overrides |
| Site layout globals | Builder structure | `sites.theme_settings.layout` | CMS settings -> workspace mirror | `header_section_key`, `footer_section_key`, footer menu column sources |
| Safe mixed content+layout edits | Mixed | CMS revision first, then workspace manifest/projection mirror | CMS -> workspace plus guarded mirror | Hero copy + variant change in one builder action |
| Custom component logic | Code | Workspace files (`src/components`, `src/sections`, `src/layouts`) | Workspace only unless explicitly content-safe | Interactive component logic, custom React behavior |
| Route/file scaffold | Code | Workspace files (`src/pages`, route/layout files, utilities) | Workspace only | Route file changes, custom utilities, generated shared hooks |
| External integrations / behavior | Code | Workspace files and service code | Workspace only | API clients, webhook handlers, booking/ecommerce adapters |
| Form submission logic | Code | Workspace files | Workspace only | submit handler, provider wiring, endpoint selection |
| AI scaffold defaults before user edits | Mixed | CMS revision payload + workspace scaffold | AI -> CMS + workspace during initial generation | New project creation, image-import scaffold, ready-state preview build |

## Notes for this repo

- The visual builder still hydrates from `latest_revision.content_json` or `published_revision.content_json` via `Install/resources/js/builder/cms/pageHydration.ts`.
- Builder inspector controls still come from `Install/resources/js/builder/componentRegistry.ts`.
- Builder mutations still flow through `Install/resources/js/builder/state/updatePipeline.ts`.
- Workspace mirroring is auxiliary and is implemented through `Install/app/Services/ProjectWorkspace/ProjectWorkspaceService.php` and `Install/resources/js/builder/codegen/workspaceBackedBuilderAdapter.ts`.
- CMS ownership metadata is stored in:
  - `content_json.webu_cms_binding`
  - `sections[*].binding.webu_v2`
  - `.webu/workspace-manifest.json`

## Default rule of thumb

- If the user expects to edit it in the current CMS/builder UI, it should be CMS-owned or builder-structure-owned.
- If changing it requires real implementation logic, integration wiring, or scaffold code, it should be code-owned.
- If one action affects both copy and implementation-bearing artifacts, treat it as mixed and mirror carefully instead of overwriting silently.
