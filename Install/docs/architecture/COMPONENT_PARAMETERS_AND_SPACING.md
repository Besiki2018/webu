# Component Parameter System and Advanced Spacing (Webu Builder)

## Overview

All builder components are configurable through the **Component Settings** sidebar. Parameters are grouped into **Content**, **Layout**, **Style**, and **Advanced** and drive both the preview and the final site output. No visible content should be hardcoded in components; everything comes from parameters.

## Parameter Architecture

Each component is defined by a **parameter schema** that controls editable content and settings:

```
component
├ componentName (section key, e.g. webu_header_01)
├ parameters
│   ├ content    – text, images, links, menu source, data bindings
│   ├ dataSource – product source, category, menu source (can live in content)
│   ├ layout     – layout_variant, columns, layout style (Layout tab)
│   ├ style      – colors, typography; responsive (desktop/tablet/mobile)
│   └ advanced   – padding, margin, z-index, custom CSS class
```

- **Content**: Logo, menu items, headlines, CTAs, etc. All visible text and data are editable via content parameters.
- **dataSource**: Product source, category, number of products, menu source. Can be defined in `content` (e.g. `menu_source`, `products_per_page`); control-definition schema may reference a `dataSource` block for clarity.
- **Layout**: Layout variant, grid columns, layout style. Exposed in the **Layout** tab when the control definition has a `layout` block; path `layout.*` maps to group `layout`.
- **Style**: Visual overrides; can be split by breakpoint (Desktop / Tablet / Mobile).
- **Advanced**: Elementor-style spacing (padding/margin per side), z-index, and custom CSS class. Applied to the **component wrapper** (section element).

Schema source: **control definitions** in `resources/schemas/control-definitions/*.json`. The backend merges these into the section library and adds **global advanced spacing** (padding, margin, z-index, custom_class) to every component.

## Global Parameter Panel Structure

The builder sidebar shows:

- **Content** – All content fields (headline, menu, logo, etc.).
- **Style** – Design overrides; when responsive is enabled, Desktop/Tablet/Mobile breakpoints.
- **Advanced** – Padding, Margin, Z-Index, Custom CSS class. Responsive spacing: each breakpoint (Desktop, Tablet, Mobile) has its own padding/margin values.

Tabs and groups are derived from `control_group` / path: top-level fields → Content, `responsive.*` → Style (with breakpoint), `advanced.*` → Advanced.

## Advanced Tab (Elementor-style)

Every component gets an **Advanced** tab with:

| Control   | Description |
|----------|-------------|
| **Padding** | Top, Right, Bottom, Left (CSS values, e.g. `16px`, `1rem`). |
| **Margin**  | Top, Right, Bottom, Left. |
| **Z-Index** | Integer; applied to wrapper. |
| **Custom CSS class** | Extra class names on the component wrapper. |

- Stored in `advanced.*` and in `responsive.desktop` / `responsive.tablet` / `responsive.mobile` for per-breakpoint spacing.
- Values are applied to the **section wrapper** in the Blade view (`generated.blade.php`) and should be applied the same way in any React/storefront renderer.
- A “link values” toggle (all sides together) is implemented in the sidebar: when on, changing one side updates all four; when off, each side is independent.

## Responsive Controls

Spacing (and other style) settings support breakpoints:

- **Desktop** – `responsive.desktop.*`
- **Tablet** – `responsive.tablet.*`
- **Mobile** – `responsive.mobile.*`

Each breakpoint stores its own padding/margin (and other style) values. The preview uses the current device mode to pick the right set.

## Dynamic Menu Selection (Header)

The **Header** component has a **Menu Source** parameter:

- **Parameter**: `menu_source` (content).
- **UI**: Dropdown “Select Menu” in the sidebar.
- **Options**: From **Navigation Menus** (site menus; e.g. header, footer). The CMS page receives `navigationMenus` from the backend; the inspector shows a select when the field path is `menu_source`.
- When a menu is selected, the header should render that menu’s items (backend or frontend resolves by `menu_source` key).

## Apply Advanced Styles to Component Wrapper

Rendering pipeline:

1. **Blade (template demos)**  
   Section wrapper: `<section class="webu-section ... {{ advanced.custom_class }}" style="padding-top:...; margin-top:...; z-index:...">`.  
   Values are taken from `data.responsive.desktop` with fallback to `data.advanced` (desktop-only on server; responsive can be applied client-side if needed).

2. **Storefront / React**  
   When rendering sections, apply the same `advanced` and `responsive` spacing and `custom_class` to the section/wrapper element so behavior matches the Blade preview.

Rule: **Margin and padding from Advanced/Responsive always apply to the component wrapper**, not to inner content only.

## Auto Component Parameter Detection

- Control definitions are loaded from `resources/schemas/control-definitions/*.json` (keyed by `component_key` or `type`).
- The backend merges each definition into the section library and adds global advanced spacing so **every** component gets Content / Style / Advanced (with responsive) without editing component code.
- **Rule**: If content appears in the component UI, it should be editable via parameters; add the field to the component’s control definition.

## Rendering Pipeline

```
Component
├ Parameters (from section props / draft)
├ Styles (responsive + advanced applied to wrapper)
├ Data Source (e.g. menu by menu_source, products by source)
└ Render Output
```

Parameters override default values. Section props are stored in the page revision and passed through as `section.props` → merged into `section.data` for the view.

## Files Reference

| Area | Location |
|------|----------|
| Control definitions (JSON) | `resources/schemas/control-definitions/*.json` |
| Control schema (meta) | `resources/schemas/control-schema.json` |
| Merge + global spacing | `app/Http/Controllers/ProjectCmsController.php` (`buildCanonicalSchemaFromControlDefinition`, `getGlobalAdvancedSpacingProperties`) |
| Inspector (sidebar) | `resources/js/Pages/Project/Cms.tsx` (schema fields, tabs, menu_source dropdown) |
| Wrapper styles (Blade) | `resources/views/template-demos/generated.blade.php` (section style/class from `data.responsive` / `data.advanced`) |
| Navigation menus API | `App\Cms\Services\CmsPanelMenuService`; passed as `navigationMenus` to Cms page |
