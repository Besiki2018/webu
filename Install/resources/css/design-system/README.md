# Design system

Single place to edit visual styling for the Webu app. Component logic and CMS bindings stay in components; only the visual layer is controlled here.

**Location:** `resources/css/design-system/` — loaded globally via `resources/css/app.css`.  
**კომპონენტების ერთი ფოლდერი:** `resources/css/webu/` — თითო კომპონენტს აქვს საკუთარი `.css` და `.html`; `webu/global.css` ყველაფერს აერთიანებს. `design-system/components.css` იმპორტებს `webu/global.css`-ს.

| File | Purpose |
|------|--------|
| **tokens.css** | Colors, radius, shadows, fonts, container width; `--webu-token-*` for CMS builder |
| **base.css** | Typography, body font, headings, `.webu-base` |
| **layout.css** | Global layout: containers, grids, spacing (`.webu-container`, `.webu-section`, `.webu-grid`); responsive breakpoints (desktop 1290px → tablet 960px → mobile 100% + 16px padding) |
| **components.css** | All component styling (`.webu-*` classes) |
| **utilities.css** | Helper classes |
| **animations.css** | Keyframes, transitions, hover effects |

## Component naming

- Root: `webu-{component-name}` (e.g. `webu-header`, `webu-product-card`)
- Variants: `webu-{component}--{variant}` (e.g. `webu-header--minimal`, `webu-product-card--compact`)
- Elements: `webu-{component}__{element}` (e.g. `webu-cart__title`)

## Global layout system

- **Structure:** `SiteLayout > GlobalHeader > PageSections (Section > .webu-container > content) > GlobalFooter`.
- **Container:** `.webu-container` — max-width 1290px (desktop), 1140px (laptop), 960px (tablet), 100% with 16px side padding (mobile). All sections and components must render inside this container.
- **Sections:** `.webu-section` wraps each page section; content lives in a child `.webu-container`. Sections cannot exceed container width; header and footer are global layout components (theme layout).
- **Config:** `resources/js/config/layoutConfig.ts` holds the same breakpoints and class names for the builder.

## Usage

- Edit **tokens.css** and **components.css** to change the whole UI.
- Components use these classes together with Tailwind; design-system is the single source for tokens and component look.
- **Dynamic values** (e.g. progress bar width %, popover position, theme hex from API) stay inline or in JS; only static visual styling lives here. Banner background image URL is passed as CSS variable `--webu-banner-bg`.
- **Template demos** (Blade) use `public/css/template-demos.css`; for shared tokens you can align that file with `tokens.css` or import it.

## Acceptance checklist

- [x] All 6 files exist and are imported in `app.css` (tokens → base → layout → components → utilities → animations).
- [x] Ecommerce components (Header, Footer, ProductCard, ProductGrid, Cart, Checkout, ProductDetails, PlaceholderSection, CategoryGrid, HeroBanner) use `webu-*` root and element classes; visual styling lives in `components.css`.
- [x] Landing components (Navbar, HeroSection, Footer, TrustedBy, FAQSection, PricingSection, CategoryGallery, FeaturesBento, TestimonialsSection, UseCases, ProductShowcase, SocialProof, FinalCTA, ScrollToTop, AnimatedSection) use `webu-*`; section styles in `components.css`.
- [x] Booking calendar styles in `components.css` (`.webu-booking-calendar-*`); `cms-booking-calendar.css` is a stub.
- [x] Tokens use theme fallbacks (`var(--primary, …)`) so theme switch works; `--webu-token-*` for CMS builder.
- [x] No component contains `<style>` or imports `.css`/`.scss`; inline styles only where dynamic (e.g. `--webu-banner-bg`, `transitionDelay`).
- [x] Editing `tokens.css` and `components.css` changes the entire UI; CMS/builder and bindings unchanged.

## Component Playground

Open **`/design-system`** to see all Webu components in one place: Header (default, minimal, mega), Hero/Banner (with and without background image), Category cards, Product cards and all variants (classic, minimal, modern, premium, compact), Product grid (with demo products), CTA banner, Newsletter placeholder, Cart (empty), Checkout, Placeholder section, Footer. Use it to visually inspect and refine styling; changes to `components.css` and `tokens.css` apply immediately. The page links to **Build with AI** (`/ai-layout-playground`) for AI layout generation. The route sends `X-Robots-Tag: noindex, nofollow` and should be excluded from sitemaps.
