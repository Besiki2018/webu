# Builder Component Ecosystem Audit

**Date:** 2026-03-11  
**Scope:** `/Install` directory

---

## 1. Component ID Sources

### 1.1 getAvailableComponents() — componentRegistry.ts REGISTRY

Returns `Object.keys(REGISTRY)`. **27 components:**

| # | Component ID |
|---|--------------|
| 1 | webu_general_hero_01 |
| 2 | webu_general_heading_01 |
| 3 | webu_general_text_01 |
| 4 | webu_general_image_01 |
| 5 | webu_general_button_01 |
| 6 | webu_general_spacer_01 |
| 7 | webu_general_section_01 |
| 8 | webu_general_newsletter_01 |
| 9 | webu_general_cta_01 |
| 10 | webu_general_features_01 |
| 11 | webu_general_cards_01 |
| 12 | webu_general_grid_01 |
| 13 | webu_general_navigation_01 |
| 14 | webu_general_card_01 |
| 15 | webu_general_form_wrapper_01 |
| 16 | webu_ecom_product_grid_01 |
| 17 | webu_ecom_featured_categories_01 |
| 18 | webu_ecom_category_list_01 |
| 19 | webu_ecom_cart_page_01 |
| 20 | webu_ecom_product_detail_01 |
| 21 | webu_general_video_01 |
| 22 | webu_header_01 |
| 23 | webu_footer_01 |

### 1.2 REGISTRY_ID_TO_KEY — registry/componentRegistry.ts

**8 entries** (schema-driven registry):

| Registry ID | Key |
|-------------|-----|
| webu_header_01 | header |
| webu_footer_01 | footer |
| webu_general_hero_01 | hero |
| webu_general_features_01 | features |
| webu_general_cta_01 | cta |
| webu_general_navigation_01 | navigation |
| webu_general_cards_01 | cards |
| webu_general_grid_01 | grid |

### 1.3 control-definitions/*.json

**36 JSON files.** Component IDs from `"type"` field (some use different IDs):

| File | type (component ID) |
|------|---------------------|
| webu_header_01.json | webu_header_01 |
| webu_footer_01.json | webu_footer_01 |
| webu_general_heading_01.json | webu_general_heading_01 |
| webu_general_text_01.json | webu_general_text_01 |
| webu_general_card_01.json | webu_general_card_01 |
| webu_general_newsletter_01.json | webu_general_newsletter_01 |
| webu_general_spacer_01.json | webu_general_spacer_01 |
| webu_general_offcanvas_menu_01.json | webu_general_offcanvas_menu_01 |
| webu_general_testimonials_01.json | webu_general_testimonials_01 |
| hero.json | hero |
| header.json | webu_ekka_header_01 |
| product_detail.json | webu_ecom_product_detail_01 |
| product_grid.json | webu_ecom_product_grid_01 |
| cart_page.json | webu_ecom_cart_page_01 |
| webu_ecom_*.json | webu_ecom_* |
| faq_accordion_plus.json | faq_accordion_plus |
| map_contact_block.json | map_contact_block |
| contact_split_form.json | contact_split_form |
| checkout_form.json | checkout_form |
| banner.json | banner |
| hero_split_image.json | hero_split_image |

**Note:** control-definitions overlap partially with REGISTRY. Some control-definition IDs (e.g. `webu_general_offcanvas_menu_01`, `webu_general_testimonials_01`, `faq_accordion_plus`) are **not** in `getAvailableComponents()`.

---

## 2. Per-Component Matrix

| component_id | has_schema | has_itemFields | has_target_markers | in_schema_registry | editable_level |
|--------------|------------|----------------|--------------------|--------------------|----------------|
| webu_general_hero_01 | ✅ | ✅ (statAvatars) | ✅ (design-system) | ✅ | full |
| webu_general_heading_01 | ❌ (legacy) | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_general_text_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_general_image_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_general_button_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_general_spacer_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_general_section_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_general_newsletter_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_general_cta_01 | ✅ (CtaSchema) | ❌ | ❌ (no markers in Cta1) | ✅ | partial |
| webu_general_features_01 | ✅ (FeaturesSchema) | ✅ (items) | ❌ (no markers in Features1) | ✅ | partial |
| webu_general_cards_01 | ✅ (CardsSchema) | ✅ (items) | ❌ (no markers in Cards) | ✅ | partial |
| webu_general_grid_01 | ✅ (GridSchema) | ✅ (items) | ❌ (no markers in Grid) | ✅ | partial |
| webu_general_navigation_01 | ✅ (NavigationSchema) | ✅ (links via infer) | ❌ | ✅ | partial |
| webu_general_card_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_general_form_wrapper_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_ecom_product_grid_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | weak |
| webu_ecom_featured_categories_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | weak |
| webu_ecom_category_list_01 | ❌ | ❌ | ❌ | ❌ | weak |
| webu_ecom_cart_page_01 | ❌ | ❌ | ❌ | ❌ | weak |
| webu_ecom_product_detail_01 | ❌ | ❌ | ❌ | ❌ | weak |
| webu_general_video_01 | ❌ | ❌ | ✅ (registryComponents) | ❌ | partial |
| webu_header_01 | ✅ (HEADER_SCHEMA) | ✅ (menu_items via infer) | ✅ (design-system) | ✅ | full |
| webu_footer_01 | ✅ (FOOTER_SCHEMA) | ✅ (links, socialLinks via infer) | ✅ (design-system) | ✅ | full |
| webu_general_testimonials_01 | ✅ (registry) | ✅ (items) | ✅ (design-system) | ✅ | full |
| faq_accordion_plus | ✅ (registry) | ✅ (items) | ✅ (design-system) | ✅ | full |
| webu_general_banner_01 | ✅ (registry) | N/A | ✅ (design-system) | ✅ | full |
| webu_general_offcanvas_menu_01 | ✅ (registry) | ✅ (menu_items) | ✅ (design-system) | ✅ | full |

---

## 3. Components Using type: 'collection' or type: 'string' for Menu/Links (Should Use menu/repeater + itemFields)

### 3.1 componentRegistry.ts parameters (REGISTRY)

| Component | Field | Current Type | Issue |
|-----------|-------|--------------|-------|
| webu_header_01 | menu_items | **string** | Should be `menu` with itemFields (label, url). Schema has `menu`; parameters override to `string`. |
| webu_footer_01 | links | **string** | Should be `menu` with itemFields. Schema has `menu`; parameters override to `string`. |
| webu_footer_01 | socialLinks | **string** | Should be `menu` with itemFields. Schema has `menu`; parameters override to `string`. |
| webu_general_features_01 | items | **collection** | Should be `repeater` with itemFields (icon, title, description). Schema has `repeater`; parameters use `collection`. |
| webu_general_cards_01 | items | **collection** | Should be `repeater` with itemFields. Schema has `repeater`; parameters use `collection`. |
| webu_general_grid_01 | items | **collection** | Should be `repeater` with itemFields. Schema has `repeater`; parameters use `collection`. |
| webu_general_navigation_01 | links | **collection** | Should be `menu` with itemFields. Schema has `menu`; parameters use `collection`. |
| webu_ecom_product_grid_01 | productsSource | **collection** | Data binding source; `collection` may be intentional. |
| webu_ecom_featured_categories_01 | categoriesSource | **collection** | Data binding source; `collection` may be intentional. |

### 3.2 control-definitions/*.json

| File | Field | Current | Issue |
|------|-------|---------|-------|
| webu_header_01.json | menu_items | `type: "array"` with items.properties | Uses JSON Schema array; no explicit `menu`/`itemFields`. Acceptable if consumed correctly. |
| webu_header_01.json | strip_right_links, top_bar_social_links, department_menu_items | `type: "array"` | Same pattern. |
| webu_footer_01.json | menus | `type: "object"` with additionalProperties array | Non-standard; should use `menu` with itemFields for consistency. |
| webu_general_offcanvas_menu_01.json | menu_items | `type: "array"` with items.properties | Uses array; should align with `menu` + itemFields if in builder. |

---

## 4. Schema vs Parameters Mismatch

- **Header/Footer:** Schema (Header.schema.ts, Footer.schema.ts) uses `type: 'menu'` for menu_items/links/socialLinks. REGISTRY `parameters` use `type: 'string'` (JSON). The schema wins for sidebar/chat; parameters are legacy.
- **Features/Cards/Grid/Navigation:** Schema uses `repeater`/`menu` with `itemFields`. REGISTRY `parameters` use `type: 'collection'`. `buildLegacySchema` is not used when schema exists; schema fields are used. `inferLegacyFieldType` maps `collection` → `menu` for legacy-only components.

---

## 5. data-webu-field Marker Coverage

| Component | Rendered By | Markers |
|-----------|-------------|---------|
| webu_header_01 | design-system/webu-header | logoText, logo_url, menu_items, ctaText, ctaLink |
| webu_footer_01 | design-system/webu-footer | newsletterHeading, newsletterCopy, contactAddress, newsletterButtonLabel, links, copyright |
| webu_general_hero_01 | design-system/webu-hero | eyebrow, title, subtitle, buttonText, buttonLink, ctaSecondaryLabel, ctaSecondaryUrl, image |
| webu_general_heading_01 | registryComponents | eyebrow, headline, subtitle |
| webu_general_text_01 | registryComponents | body |
| webu_general_image_01 | registryComponents | image_url, caption |
| webu_general_button_01 | registryComponents | button, button_url |
| webu_general_spacer_01 | registryComponents | height |
| webu_general_card_01 | registryComponents | title, body, image_url, linkLabel, link_url |
| webu_general_form_wrapper_01 | registryComponents | title, subtitle, submit_label |
| webu_general_newsletter_01 | registryComponents | title, subtitle, buttonText |
| webu_general_section_01 | registryComponents | title, body |
| webu_general_video_01 | registryComponents | video_url, caption |
| webu_general_features_01 | WebuFeatures (design-system) | ❌ No markers in Features1 |
| webu_general_cards_01 | Cards (sections) | ❌ No markers |
| webu_general_grid_01 | Grid (sections) | ❌ No markers |
| webu_general_cta_01 | Cta1 (design-system) | ❌ No markers |
| webu_general_navigation_01 | Navigation | links.${i} scope, label, url per link |
| webu_general_testimonials_01 | WebuTestimonials | title, items.${i} scope, text, user_name, avatar, rating |
| faq_accordion_plus | WebuFaq | title, items.${i} scope, question, answer |
| webu_general_banner_01 | WebuBanner | title, subtitle, ctaLabel, ctaUrl |
| webu_general_offcanvas_menu_01 | WebuOffcanvasMenu | trigger_label, title, subtitle, menu_items.${i} scope, footerLabel, footerUrl |
| webu_ecom_product_grid_01 | BuilderCollectionCanvasSection | title, subtitle, image, cta_label |
| Other ecom | Cms.tsx placeholders | Various (title, description, etc.) |

---

## 6. Editable Level Definitions

- **full:** Schema with fields, itemFields for repeater/menu, data-webu-field markers in rendered component, in schema-driven registry.
- **partial:** Has schema or legacy parameters, some markers or canvas fallback; missing itemFields or markers in some variants.
- **weak:** Legacy parameters only, no schema, or placeholder-only rendering.

---

## 7. Recommendations

1. **Header/Footer parameters:** Align REGISTRY `parameters` with schema: use `menu` type (or remove parameters in favor of schema) for menu_items, links, socialLinks.
2. **Features/Cards/Grid/Navigation parameters:** Change `items`/`links` from `collection` to `repeater`/`menu` in REGISTRY parameters for consistency (schema already correct).
3. **Add data-webu-field to schema-driven components:** Features, Cards, Grid, CTA, Navigation variants need `data-webu-field` and `data-webu-field-scope` for repeater items to enable click-to-edit in preview.
4. **control-definitions alignment:** Ensure control-definitions JSON for builder components use `menu`/`repeater` with itemFields where applicable; webu_footer_01 `menus` structure is non-standard.
5. **webu_general_offcanvas_menu_01:** Not in REGISTRY; add to getAvailableComponents() if it should appear in builder library.
