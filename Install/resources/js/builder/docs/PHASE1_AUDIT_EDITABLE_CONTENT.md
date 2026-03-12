# Phase 1 — Audit: Editable Content in Webu Components

**Goal:** Identify every visual value inside JSX that should become an editable prop so Chat, Sidebar, and future AI can edit it without changing component structure or design.

**Constraints:** Do not delete components, remove variant files, or change visual design. Only extract editable values into props and schema.

**Folders scanned:**
- `resources/js/components` (layout, sections, design-system)
- `resources/js/components/layout` (Header, Footer, Navigation)
- `resources/js/components/sections` (Hero, Features, CTA, Cards, Grid)
- `resources/js/components/design-system/webu-*` (hero, header, footer, features, cta, etc.)

---

## Summary table

| Category | Examples | Status in variants |
|----------|----------|--------------------|
| Titles / headlines | h1, h2, section titles | Mostly from props; some fallbacks hardcoded |
| Subtitles / paragraphs | subtitle, description, copy | From props where present |
| Button labels | CTA text, Subscribe, Search | Some props; fallbacks like "Subscribe", "Logo" hardcoded |
| Images / alt text | imageUrl, imageAlt | Props exist; alt fallback `'Hero'` hardcoded in hero variants |
| Icons | Lucide icons (header-4) | Icon choice hardcoded by label (e.g. CELL PHONES → Smartphone) |
| Background / text colors | backgroundColor, textColor | From props |
| Spacing / alignment | padding, alignment, textAlign | From props where defined |
| Links | menu, ctaUrl, logoUrl | From props |
| Badges / labels | payment chips, promo badge | From props or menu; some aria-labels hardcoded |
| Aria-labels / placeholders | nav aria-label, input placeholder | Many default strings hardcoded in JSX |
| Footer newsletter | placeholder, button label | Fallbacks "Your email", "Subscribe" hardcoded |
| Logo fallback | When no logo text | "Logo", "Store" hardcoded |

---

## 1. Hero variants (`design-system/webu-hero/variants/`)

### hero-1.tsx
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Image alt fallback | `img` alt | `imageAlt \|\| resolvedHeadline \|\| 'Hero'` | Add prop `imageAltFallback` (e.g. default `'Hero'`) and use in schema. |
| Pagination dots | 3 dots (aria-hidden) | Presentational only | Optional: make count or visibility a prop if ever editable. |

All other content: headline, eyebrow, badgeText, subtitle, ctaLabel, ctaUrl, ctaSecondaryLabel/Url, imageUrl — from props.

### hero-2.tsx, hero-3.tsx, hero-4.tsx, hero-5.tsx
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Image alt fallback | `img` alt | `imageAlt \|\| resolvedHeadline \|\| 'Hero'` | Same: add `imageAltFallback` prop (or use shared default in schema). |

### hero-6.tsx
Same image alt pattern as above.

### hero-7.tsx
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Image alt fallback | `img` alt | `imageAlt \|\| resolvedHeadline \|\| 'Hero'` | Add `imageAltFallback`. |
| Overlay image alt | `img` alt | `overlayImageAlt \|\| 'Overlay'` | Add to types/schema or use prop `overlayImageAlt` with default in schema. |

---

## 2. Header variants (`design-system/webu-header/variants/`)

### header-1.tsx
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Logo fallback | logo display | `logo \|\| 'Logo'` | Add prop `logoFallback` (default `'Logo'`) for editability. |
| Logo img alt | `img` alt | `logo \|\| 'Logo'` | Use same fallback prop. |
| Nav aria-label | `nav` aria-label | `navAriaLabel = 'Main navigation'` | Already a prop; ensure in schema with default. |

### header-2.tsx
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Logo fallback | logo + img alt | `logo \|\| 'Logo'` | Add `logoFallback` prop. |
| Nav aria-label | `nav` | `navAriaLabel = 'Main navigation'` | Already prop; in schema. |

### header-4.tsx (Machic-style)
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Logo fallback | aria-label, img alt, wordmark | `logo \|\| 'Logo'` | Add `logoFallback`. |
| Nav aria-label | default | `navAriaLabel = 'Main navigation'` | Already prop. |
| searchAriaLabel | default | `'Search'` | Already prop; ensure schema. |
| accountAriaLabel | default | `'Account'` | Already prop. |
| cartAriaLabel | default | `'Cart'` | Already prop. |
| wishlistAriaLabel | default | `'Wishlist'` | Already prop. |
| menuTriggerAriaLabel | default | `'Open departments menu'` | Already prop. |
| Search scope label | button text | `searchCategoryLabel \|\| 'All'` | Already prop. |
| Search placeholder | input | `searchPlaceholder \|\| 'Search'` | Already prop. |
| Search submit label | button | `searchButtonLabel \|\| 'Search'` | Already prop. |
| Account label | copy | `accountLabel \|\| 'Account'` | Already prop. |
| Cart label | copy | `cartLabel \|\| 'Cart'` | Already prop. |
| Department trigger label | button | `departmentLabel \|\| 'Menu'` | Already prop. |
| Dropdown description | drawer item | `'Highlighted navigation item'` or `'Open page'` | Add prop e.g. `menuItemDefaultDescription` / `menuItemHighlightDescription` if we want editable. |
| Search scope button aria-label | button | `searchAriaLabel \|\| 'Select category'` | From props. |

Header-4 is already well covered by props; only fallback strings are in code — ensure all have schema defaults so they are editable.

### header-3, header-5, header-6
Audit similarly: any `'Logo'`, `'Main navigation'`, or other literal used as fallback or aria-label should be a prop (or schema default) so builder/chat can override.

---

## 3. Footer variants (`design-system/webu-footer/variants/`)

### footer-1.tsx
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Newsletter placeholder | input | `newsletterPlaceholder \|\| 'Your email'` | Already prop; ensure default in schema. |
| Newsletter button | button text | `newsletterButtonLabel \|\| 'Subscribe'` | Already prop; ensure default in schema. |
| Payments section | div | `aria-label="Payment methods"` | Add prop `paymentsAriaLabel` (default `'Payment methods'`) for editability. |
| Copyright | fallback | `© ${year} ${logo}` or `© ${year}` | From props; resolved in code — keep as is. |

### footer-2.tsx
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Logo default | prop default | `logo = 'Logo'` | Move to schema default; component can keep default for backward compat. |
| Copyright default | prop default | `` `© ${new Date().getFullYear()}` `` | Move to schema default. |
| Nav aria-label | `nav` | `aria-label="Footer"` | Add prop `footerNavAriaLabel` (default `'Footer'`). |

### footer-3.tsx, footer-4.tsx
| Value | Location | Current | Action |
|-------|----------|---------|--------|
| Logo fallback | display | `logo ?? 'Store'` | Add prop `logoFallback` (default `'Store'`) for editability. |
| Copyright fallback | display | `copyright ?? \`© ${year}\`` | Already common; ensure in schema. |

---

## 4. Features (`design-system/webu-features/`)

### features-1.tsx
All content from props: `title`, `items[].icon`, `items[].title`, `items[].description`. No hardcoded visual text. Ensure schema has these fields (already in Features.schema).

---

## 5. CTA (`design-system/webu-cta/`)

### cta-1.tsx
All from props: `title`, `subtitle`, `buttonLabel`, `buttonUrl`. No hardcoded visual text. Schema already covers.

---

## 6. Sections (wrapper layer)

- `components/sections/Hero/Hero.tsx` — delegates to variants; no extra hardcoded content.
- `components/sections/Features/Features.tsx` — delegates; no hardcoded content.
- `components/sections/CTA/CTA.tsx` — delegates; no hardcoded content.
- `components/sections/Cards/Cards.tsx`, `Grid/Grid.tsx` — use props; no hardcoded content.

---

## 7. Layout (wrapper layer)

- `components/layout/Header/Header.tsx`, `Footer/Footer.tsx`, `Navigation/Navigation.tsx` — delegate to design-system or local implementation; no hardcoded content in wrappers.

---

## 8. Values to extract (checklist)

These are the **visual values that must become editable via props + schema** (either already props with hardcoded fallbacks, or new props):

### Titles / subtitles / paragraphs
- [x] Hero: title, subtitle, eyebrow, badgeText — props exist.
- [x] Features: title, items[].title, items[].description — props exist.
- [x] CTA: title, subtitle — props exist.
- [x] Header: logo (text) — prop; fallback `'Logo'` → make editable via `logoFallback` or schema default.
- [x] Footer: newsletterHeading, newsletterCopy, copyright — props exist; fallbacks "Your email", "Subscribe" → schema defaults.

### Button labels
- [x] Hero: ctaLabel, ctaSecondaryLabel — props.
- [x] CTA: buttonLabel — prop.
- [x] Header: ctaLabel, searchButtonLabel, departmentLabel, accountLabel, cartLabel — props (header-4).
- [x] Footer: newsletterButtonLabel — prop; default "Subscribe" in schema.

### Images
- [x] Hero: imageUrl, imageAlt; overlayImageUrl, overlayImageAlt (hero-7) — props.
- [ ] **Image alt fallback:** All hero variants use `'Hero'` when imageAlt and headline empty → add `imageAltFallback` prop (default `'Hero'`) to types + schema.
- [ ] **Hero-7 overlay alt:** `'Overlay'` → add to schema default for `overlayImageAlt`.

### Icons
- Header-4: icons (Smartphone, Headphones, etc.) are chosen by menu label (e.g. "CELL PHONES", "HEADPHONES"). To make "icon per item" editable without breaking design: add optional `icon` or `iconKey` to menu item type and schema; variant uses it when present, else keeps current label-based logic.

### Background / text colors
- [x] Hero, Header, Footer: backgroundColor, textColor — props.

### Spacing / alignment
- [x] Hero: alignment — prop. Padding/spacing in types; ensure in schema where needed.

### Links
- [x] Menu items, logoUrl, ctaUrl, etc. — from props.

### Badges / feature lists
- [x] Footer payment badges — paymentMethods[].label; feature items — items[].title, description, icon.

### Aria-labels and placeholders (must be editable)
- [ ] **header-1, header-2:** `navAriaLabel` default `'Main navigation'` — already prop; in schema.
- [ ] **footer-1:** `aria-label="Payment methods"` — add prop `paymentsAriaLabel`.
- [ ] **footer-2:** `aria-label="Footer"` — add prop `footerNavAriaLabel`.
- [ ] **header-4:** All aria defaults (Search, Account, Cart, Wishlist, Open departments menu) — already props; ensure in schema with these defaults.

### Logo / brand fallbacks
- [ ] **Header:** `logo || 'Logo'` in all variants → add `logoFallback` prop (default `'Logo'`) or rely on schema default only (so builder sets "Logo" as default and user can change).
- [ ] **Footer-3, Footer-4:** `logo ?? 'Store'` → add `logoFallback` (default `'Store'`).

---

## 9. Recommended next steps (no deletion, no design change)

1. **Hero variants:** Add optional prop `imageAltFallback?: string` (default `'Hero'`) to `WebuHeroProps` and schema; use in all hero variants instead of literal `'Hero'`. Hero-7: ensure `overlayImageAlt` in schema with default `'Overlay'`.
2. **Header types/schema:** Add optional `logoFallback?: string` (default `'Logo'`). Ensure all aria-label and search/account/cart/department labels are in schema with current defaults.
3. **Footer types/schema:** Add optional `logoFallback?: string` (default `'Store'` for footer-3/4), `paymentsAriaLabel?: string` (footer-1), `footerNavAriaLabel?: string` (footer-2). Ensure newsletter placeholder and button label defaults in schema.
4. **Registry/defaults:** In each component’s defaults and builder schema, set the above defaults so Sidebar and Chat can edit every visible string, including fallbacks and aria-labels.
5. **Header-4 menu descriptions:** Optionally add `menuItemDefaultDescription` and `menuItemHighlightDescription` (or per-item `description`) if we want dropdown descriptions to be AI-editable; else leave as-is.

After these changes, every visual string in the audited Webu components will be editable via props and schema without removing variants or changing layout/design.
