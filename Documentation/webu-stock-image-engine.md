# Webu Stock Image Engine

## Purpose

Webu now has a unified stock image engine that lets AI generation, the CMS-oriented visual builder, and code-backed project workflows all use the same server-side image provider layer.

The engine is designed around three rules:

1. Provider credentials stay on the backend.
2. Imported stock images become local project assets, not hotlinked URLs.
3. CMS-managed content keeps working after image replacement.

## Environment Setup

Add these variables to the runtime environment:

```dotenv
UNSPLASH_ACCESS_KEY=
UNSPLASH_SECRET_KEY=
PEXELS_API_KEY=
FREEPIK_API_KEY=
```

Configured in [services.php](/Users/besikiekseulidze/web-development/webu/Install/config/services.php):

- `services.unsplash.access_key`
- `services.unsplash.secret_key`
- `services.pexels.key`
- `services.freepik.key`

The frontend never receives raw provider keys. All provider traffic goes through backend routes exposed by [AssetProviderController.php](/Users/besikiekseulidze/web-development/webu/Install/app/Http/Controllers/AssetProviderController.php).

## Provider Architecture

Provider abstraction lives under [app/Services/Assets](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets):

- [StockImageProviderInterface.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/StockImageProviderInterface.php) defines the search contract.
- [UnsplashProvider.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/UnsplashProvider.php) handles `search/photos`.
- [PexelsProvider.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/PexelsProvider.php) handles `v1/search`.
- [FreepikProvider.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/FreepikProvider.php) handles Freepik search and download resolution.
- [ImageSearchService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/ImageSearchService.php) fans out to providers, dedupes, and ranks.
- [ImageRankingService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/ImageRankingService.php) scores results for builder/AI use.
- [ImageImportService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/ImageImportService.php) downloads, validates, optimizes, stores, and records provenance.

All providers normalize to one result shape:

- `provider`
- `id`
- `title`
- `preview_url`
- `full_url`
- `download_url`
- `width`
- `height`
- `author`
- `license`

This keeps the frontend provider-agnostic and makes future providers additive instead of invasive.

## Search Flow

Search request path:

1. Builder or code-mode UI calls [stockImageClient.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/stockImageClient.ts).
2. Request hits `POST /api/assets/search`.
3. [AssetProviderController.php](/Users/besikiekseulidze/web-development/webu/Install/app/Http/Controllers/AssetProviderController.php) validates query, limit, and orientation.
4. [ImageSearchService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/ImageSearchService.php) queries Unsplash, Pexels, and Freepik.
5. Results are deduped and passed into [ImageRankingService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/ImageRankingService.php).
6. Ranked results are returned to the modal/UI with provider tags and scoring metadata.

Default provider strategy:

- Unsplash: primary photo source
- Pexels: fallback/secondary photo source
- Freepik: illustration/design asset source

## Ranking Algorithm

[ImageRankingService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/ImageRankingService.php) combines several heuristics:

- query relevance against title/metadata
- orientation fit for the requested context
- resolution and usable asset size
- brightness heuristic from provider color metadata when available
- clarity heuristic from preview/full image availability and dimensions
- provider priority for deterministic fallback behavior

Current orientation bias:

- hero / CTA: landscape
- team / testimonials: portrait
- gallery / generic blocks: square unless caller specifies otherwise

The ranking layer is intentionally isolated so later providers or model-assisted ranking can be added without changing UI contracts.

## Import Flow

Import request path:

1. User or AI selects a normalized stock result.
2. UI calls `POST /api/assets/import`.
3. [AssetProviderController.php](/Users/besikiekseulidze/web-development/webu/Install/app/Http/Controllers/AssetProviderController.php) validates provider, URL, and project access.
4. [ImageImportService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/ImageImportService.php):
   - verifies provider-specific download hosts
   - resolves Freepik API download URLs when needed
   - downloads the binary server-side
   - runs upload safety checks
   - optimizes the image with GD when available
   - stores the file on the `public` disk
   - creates a CMS `media` record
   - records stock provenance in `meta_json`
5. Response returns the new local asset and public asset URL.

Storage target:

- `storage/app/public/projects/{project_id}/assets/images/...`

Imported files are served through the existing CMS asset route:

- `public.sites.assets`

## CMS and Builder Integration

The stock engine is layered into the current CMS-oriented builder instead of replacing it.

Primary integration points:

- [Cms.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/Pages/Project/Cms.tsx)
- [CmsMediaFieldControl.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/cms/CmsMediaFieldControl.tsx)
- [imageSearchModal.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/imageSearchModal.tsx)
- [imageSelector.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/imageSelector.ts)
- [Media.php](/Users/besikiekseulidze/web-development/webu/Install/app/Models/Media.php)

Behavior:

- image-capable inspector fields can open stock search directly
- selected stock images are imported into the CMS media library first
- builder field values are updated with the local asset URL, not the remote provider URL
- CMS-bound media fields remain revision-backed and survive reload/reopen cycles
- preview refresh continues to use the existing CMS + preview bridge flow

This keeps image replacement compatible with:

- Hero images
- Gallery items
- Feature cards
- CTA backgrounds
- Team/profile imagery
- Generic image/media CMS fields

## AI Generation Integration

AI generation uses the stock engine through [GenerateWebsiteProjectService.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/AiWebsiteGeneration/GenerateWebsiteProjectService.php).

During initial site generation Webu now:

1. detects image-capable generated sections
2. infers a section role such as hero, gallery, team, testimonials, features, or CTA
3. builds a targeted search query from website brief, page title, and section content
4. searches the unified provider layer
5. imports the best-ranked candidate locally
6. patches generated section settings with the local asset URL before CMS revision creation

Result:

- the initial generated site is not left with dead external image links
- CMS-backed sections can still edit/replace imagery later
- preview/build output sees the same local asset paths that the CMS sees

## Provenance and Audit Data

Every imported stock image stores provenance in `media.meta_json`, including:

- `asset_origin`
- `stock_provider`
- `stock_image_id`
- `stock_author`
- `stock_license`
- `stock_query`
- `imported_by`
- `project_id`
- `section_local_id`
- `component_key`
- `page_slug`
- `page_id`
- `imported_at`

This supports later audit, merge, CMS binding, and AI edit context.

## Frontend State Layer

Frontend stock image state lives under [resources/js/builder/assets](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets):

- [stockImageTypes.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/stockImageTypes.ts)
- [stockImageClient.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/stockImageClient.ts)
- [imageImportState.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/imageImportState.ts)
- [imageSelector.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/imageSelector.ts)
- [imageSearchModal.tsx](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/imageSearchModal.tsx)

The modal remains a thin UI layer. Provider selection, auth, and file writes all stay server-side.

## Future Expansion

The current architecture is intended to support more sources without breaking the API contract:

- AI-generated image providers
- Midjourney/Stable Diffusion adapter layers
- private brand asset libraries
- tenant-scoped design systems
- provider-specific licensing filters
- ML-assisted visual matching against screenshot/reference analysis

The extension seam is the provider interface plus the ranking/import services, not the builder UI.

## Tests

Coverage added for the current rollout:

- [AssetProviderControllerTest.php](/Users/besikiekseulidze/web-development/webu/Install/tests/Feature/Cms/AssetProviderControllerTest.php)
- [imageSelector.test.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/__tests__/imageSelector.test.ts)
- [imageImportState.test.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/__tests__/imageImportState.test.ts)
- [stockImageClient.test.ts](/Users/besikiekseulidze/web-development/webu/Install/resources/js/builder/assets/__tests__/stockImageClient.test.ts)
- [GenerateWebsiteProjectWorkspaceInitializationTest.php](/Users/besikiekseulidze/web-development/webu/Install/tests/Feature/Cms/GenerateWebsiteProjectWorkspaceInitializationTest.php)

These cover:

- provider merge/ranking behavior
- safe import/storage behavior
- frontend search/import client contract
- selector/query heuristics
- CMS/workspace initialization continuity
