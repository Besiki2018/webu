# Webu v2 Image-to-Site

## Goal

Webu now has a dedicated image-import lane for screenshot-driven generation that feeds the new code-first architecture instead of stopping at temporary builder state.

Target flow:

`image/screenshot -> design extraction -> layout inference -> component matchmaking -> project graph -> workspace plan/ops -> preview build`

## New frontend modules

New image-import domain files live under:

- `Install/resources/js/builder/image-import/types.ts`
- `Install/resources/js/builder/image-import/designExtractionContract.ts`
- `Install/resources/js/builder/image-import/layoutInference.ts`
- `Install/resources/js/builder/image-import/componentMatchmaking.ts`
- `Install/resources/js/builder/image-import/imageToProjectGraph.ts`
- `Install/resources/js/builder/image-import/imageImportState.ts`

These modules sit beside the v2 codegen runtime and reuse:

- `componentRegistry.ts` for component metadata and matching
- `projectGraph.ts` / `workspacePlan.ts` for code-first output
- `projectGraphToBuilderModel.ts` for builder-compatible mapping
- `aiWorkspaceOps.ts` for canonical workspace write intent

## Responsibilities

### 1. Design extraction

`designExtractionContract.ts` defines the realistic contract for upstream image analysis.

It supports two modes:

- backend provider mode: future API can return structured blocks/signals
- legacy local fallback mode: current repo reuses existing helpers such as:
  - `builder/ai/layoutDetector.ts`
  - `builder/ai/designStyleAnalyzer.ts`
  - `builder/ai/designContentExtractor.ts`
  - `builder/ai/designImageProcessor.ts`

The extraction result is intentionally coarse. It does not claim OCR-perfect fidelity.

It captures:

- visual blocks like navbar, hero, card-grid, gallery, form, CTA, footer
- style direction
- optional raw function signals
- warnings when the lane is heuristic-only

### 2. Layout inference

`layoutInference.ts` converts extracted design evidence into semantic page structure.

This stage explicitly separates:

- visual/design structure
- likely functional modules

Examples of inferred modules:

- `ecommerce`
- `booking`
- `newsletter`
- `contact_form`
- `blog`

Reference-only mode and recreate mode diverge here:

- `reference` mode normalizes noisy designs into cleaner Webu-compatible structure and dedupes repeated core sections
- `recreate` mode preserves more hierarchy/spacing intent and allows more exact block sequencing

### 3. Component matchmaking

`componentMatchmaking.ts` maps inferred layout nodes onto the registry first.

Rules:

- prefer existing registry components when the match is strong enough
- prefer domain-specific registry entries when function inference supports them
  - example: ecommerce product areas -> `webu_ecom_product_grid_01`
  - example: booking/newsletter/contact -> `webu_general_form_wrapper_01`
- fall back to generated section components only when registry fit is below threshold

This is the key boundary between:

- builder metadata authority
- image-derived structure

The registry remains the authority for semantic component choice and later inspector compatibility, but not for raw screenshot extraction.

## Code-first output

`imageToProjectGraph.ts` turns the inferred design into:

- `GeneratedProjectGraph`
- `WorkspacePlan`
- canonical `AiWorkspaceOperation[]`
- builder-compatible page models

The generated workspace set includes:

- `src/App.tsx`
- `src/pages/{slug}/Page.tsx`
- `src/layouts/SiteLayout.tsx`
- `src/styles/globals.css`
- imported section files under `src/sections/imported/*`
- a design reference snapshot under `public/imports/*-design-reference.json`

The graph is emitted in a pre-preview state (`writing_files` / preview blocked). This is deliberate: the lane plans the code-first project and the canonical workspace operations, then the runtime can execute scaffold/write/build through the existing workspace control layer.

## Builder compatibility

The lane does not bypass the visual builder.

`projectGraphToBuilderModel.ts` was expanded so image-import output can still map into current inspector/canvas-friendly sections for:

- grids
- cards
- testimonials
- forms
- FAQ
- ecommerce product grids

Current UI integration point:

- `Install/resources/js/Pages/Project/Cms.tsx`

The existing `DesignImportPanel` now routes through the new image-import pipeline in `reference` mode, then hydrates the current builder with the derived builder model. That preserves the current UX while aligning design import with the new code-first architecture.

## Backend contract

No OCR-heavy backend implementation was added in this task.

Instead, `designExtractionContract.ts` defines the provider contract for a future endpoint/service that can return:

- block structure
- style direction
- raw function signals
- warnings / metadata

That means backend work can be added later without changing the downstream layout/component/codegen pipeline.

## Exact recreation vs reference-only

Reference-only mode:

- preserve direction, hierarchy intent, tone, and rough layout semantics
- normalize into cleaner Webu sections
- avoid pretending to be pixel-perfect

Exact recreation mode:

- preserve ordering and spacing intent more aggressively
- use generated section components sooner when registry fit is weak
- still remains best-effort, not OCR/CSS-perfect cloning

## Verification

Validated with:

- `npm run typecheck`
- `npm run test:run -- resources/js/builder/image-import/__tests__/layoutInference.test.ts resources/js/builder/image-import/__tests__/componentMatchmaking.test.ts resources/js/builder/image-import/__tests__/imageImportState.test.ts resources/js/builder/image-import/__tests__/imageToProjectGraph.test.ts resources/js/builder/codegen/__tests__/projectGraphMappings.test.ts`
