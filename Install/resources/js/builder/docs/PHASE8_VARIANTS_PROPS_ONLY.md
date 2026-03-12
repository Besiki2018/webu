# Phase 8 — Prevent Breaking Existing Variants

Variants (Hero1, Hero2, Hero3, Header1–6, Footer1–4, etc.) must **remain unchanged in layout**. Only modify them to accept props: replace hardcoded content with props.

## Rule

- **Do:** Replace literal content with props.
  - Example: `<h1>Title</h1>` → `<h1>{props.title}</h1>` (or `{resolvedHeadline}` where headline/title are both supported).
- **Do not:** Change layout, remove sections, or restructure JSX.

## Verification

All design-system variants use **props only** for user-facing content:

| Component   | Variants   | Content source |
|------------|------------|----------------|
| **Hero**   | hero-1 … hero-7 | `resolvedHeadline` (= headline \|\| title), `resolvedSubheading`, `ctaLabel`, `ctaUrl`, `imageUrl`, `imageAlt` / `imageAltFallback`, `eyebrow`, `badgeText`, etc. No literal "Title" or "Get Started" in JSX. |
| **Header** | header-1 … header-6 | `logo` / `logoFallback`, `menu`, `ctaLabel`, `navAriaLabel`, `searchAriaLabel`, `menuTriggerAriaLabel`, etc. Fallbacks (e.g. 'Logo') are default parameters, not inline literals. |
| **Footer** | footer-1 … footer-4 | `logo` / `logoFallback`, `menus`, `copyright`, `newsletterHeading`, `newsletterPlaceholder`, `newsletterButtonLabel`, `paymentsAriaLabel`, `footerNavAriaLabel`. |
| **Features** | features-1 | `title`, `items` (item.title, item.description, item.icon). |
| **CTA**    | cta-1       | `title`, `subtitle`, `buttonLabel`, `buttonUrl`. |

Layout (structure, class names, order of sections) is unchanged from the original variants.

## Default parameter values

Variants may use **default parameter values** for when a prop is missing (e.g. `imageAltFallback = 'Hero'`, `logoFallback = 'Logo'`). Those are part of the component API and are overridable via props/schema; they are not hardcoded content in the JSX.
