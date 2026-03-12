# Final result — AI website generation

---

## Create pricing section (FINAL RESULT)

**User writes:**  
*Create pricing section*

**Webu automatically:**

1. **Creates component** — Generates `Pricing.tsx` (and `Pricing.variants.ts`, `index.ts`) in `components/sections/Pricing/`.
2. **Creates schema** — Generates `Pricing.schema.ts` with `editableFields` so the builder knows which props are editable.
3. **Creates defaults** — Generates `Pricing.defaults.ts` with default title, plans, and CTA so the section renders with content.
4. **Registers component** — Registers the new section in the builder registry (`registerGeneratedComponent`) so it appears in the library and canvas can resolve it.
5. **Adds it to canvas** — Appends the new section to the page (or at chosen index) via `addGeneratedSectionToCanvas(addSectionByKey, registryId)`.

**User can immediately edit it.**  
The new section uses the same schema-driven editing as any other section: select it in the canvas or sidebar, change title/plans/price/features/CTA via controls or JSON; drag to reorder; duplicate or remove. No special handling for “just created” vs “manual” sections.

### Pipeline (implementation)

- **Detection** — `detectComponentRequest("Create pricing section")` → `normalizedSlug: "pricing_table"`, `shouldTriggerGenerator: true` (no existing dedicated pricing component in registry).
- **Spec** — `generateComponentSpecWithDuplicateCheck(...)` → `PricingSection` spec (layout variants: cards, horizontal, minimal). If an equivalent already exists → `action: 'addVariant'` (do not create duplicate).
- **Folder** — `generateComponentFolder(spec)` → TSX, schema, defaults, variants, index (in-memory).
- **Validation** — `validateGeneratedComponentFolder(folder)` → schema exists, defaults exist, component compiles, etc. On failure, caller may regenerate.
- **Write** — Caller writes `folder.files` to disk (e.g. `components/sections/Pricing/*`).
- **Register** — Caller dynamically imports the new component and calls `registerGeneratedComponent(registryId, key, entry)`.
- **Canvas** — Caller calls `addGeneratedSectionToCanvas(addSectionByKey, registryId)` so the section appears on the page.

Orchestration: `runCreateComponentPipeline({ prompt: "Create pricing section" })` returns `{ action, spec, folder, registryId, validation }`. Caller then writes files, registers, and adds to canvas. Helper: `addCreatedComponentToCanvas(addSectionByKey, registryId)` to add the section to the canvas after registration.

---

## Design upload — user uploads design screenshot

**User uploads** a design screenshot (or image URL) via **Import Design** in the builder.

**Webu automatically generates a full page with:**

| Section          | Registry component           |
|------------------|------------------------------|
| Header           | webu_header_01               |
| Hero             | webu_general_hero_01          |
| Feature sections | webu_general_features_01     |
| Testimonials     | webu_general_cards_01         |
| CTA              | webu_general_cta_01           |
| Footer           | webu_footer_01               |

**Fully editable in the builder:** titles, images, layout/variants, colors, add/remove/reorder sections — same as manually added sections. See [EDITABLE_OUTPUT.md](./EDITABLE_OUTPUT.md).

*(If layout detection returns fewer blocks, the pipeline uses the full default structure so the page always has these six sections.)*

---

## Acceptance: “Create a modern SaaS landing page”

**User writes:**  
*Create a modern SaaS landing page*

**Webu generates:**

| Section       | Registry component           | Content / layout / props                          |
|---------------|------------------------------|---------------------------------------------------|
| Header        | webu_header_01               | Logo, nav, CTA; variant from tone                 |
| Hero          | webu_general_hero_01          | Title, subtitle, CTA; optional AI image          |
| Features      | webu_general_features_01     | Feature items; optional AI content                |
| Pricing       | webu_general_features_01     | Pricing/plans block; optional AI content         |
| Testimonials  | webu_general_cards_01        | Cards/reviews; optional AI content                |
| CTA           | webu_general_cta_01          | Call to action; optional AI content              |
| Footer        | webu_footer_01               | Links, newsletter, copyright                     |

**With:**

- **Real content** — When a content provider is passed to `generateSiteFromPrompt()`, hero title/subtitle/CTA, features items, and CTA copy are generated and merged into section props.
- **Images** — When an image provider is passed, a hero image (and optionally other section images) can be generated and injected into props.
- **Layout** — Variants are chosen from the component registry by tone and project type (e.g. modern → hero-2, features-2).
- **Props** — Each section gets registry defaults merged with generated content and any overrides; all props are editable in the builder.

**Inside builder canvas:**  
The generated site is applied as **section drafts** (`setSectionsDraft(result.sectionsDraft)`) and **project type** (`setProjectType(result.projectType)`). The canvas renders the same sections as manually added ones.

**User can immediately edit everything:**  
Same pipeline as manual sections: select a section in the canvas or sidebar, change text/colors/images via schema-driven controls or raw JSON; drag to reorder; add/remove sections. No special handling for “AI-generated” vs “manual” — see [EDITABLE_OUTPUT.md](./EDITABLE_OUTPUT.md).

---

## Flow

1. **Prompt** → `analyzePrompt(prompt)` → `projectType`, `tone`, `requiredSections`, etc.
2. **Sections** → `planSite(analysis)` → ordered list of registry component keys + variants (safety: only registry components; fallback default hero/features/footer).
3. **Variants** → `applyVariantSelection(sections, context)` → one variant per section (tone-aware, no duplicate layouts).
4. **Content** (optional) → `generateContent()` per hero/features/cta → `contentToHeroProps` / `contentToFeaturesProps` / `contentToCtaProps` → `propsByIndex`.
5. **Images** (optional) → `generateImageFromContext()` for hero → URL injected into hero props.
6. **Tree** → `sectionPlanToComponentTree(plan, { propsByIndex })` → `BuilderComponentInstance[]`.
7. **Apply** → `treeToSectionsDraft(tree)` → `setSectionsDraft(sectionsDraft)` + `setProjectType(projectType)`.

To enable **real content** and **images** in the Cms UI, pass `contentProvider` and/or `imageProvider` to `generateSiteFromPrompt()` (e.g. from project AI settings or env-backed API). Without them, sections still get full **layout** and **registry props** and are fully editable.
