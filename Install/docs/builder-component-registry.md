# Builder Component Registry

## Purpose

Builder V2 uses one registry for both rendering and editing.

The registry lives in:

- `resources/js/builder/components/registry.ts`
- `resources/js/builder/components/defaults.ts`
- `resources/js/builder/components/schemas.ts`
- `resources/js/builder/components/renderers/index.tsx`

## Definition Shape

Each component definition includes:

- `key`
- `label`
- `category`
- `defaultProps`
- `schema`
- `renderer`

Optional capabilities such as `allowedChildren` and `slots` can extend the same definition object.

## Why The Registry Matters

The registry removes schema drift:

- Canvas rendering resolves the component renderer from the registry.
- The inspector resolves editable fields from the same registry entry.
- Library insertion uses the same registry defaults to create new nodes.
- AI suggestions are expected to target document nodes that map back to registered components.

## Current V2 Component Set

The V2 registry currently includes:

- `hero`
- `banner`
- `newsletter`
- `footer`
- `section`
- `text`
- `image`
- `button`
- `legacy-section`

`legacy-section` exists as a safe fallback for CMS-imported content that has not yet been migrated to a richer V2-specific component.

## Extension Rules

When adding a new V2 component:

1. Add default props in `defaults.ts`.
2. Add the editable schema in `schemas.ts`.
3. Add or export the renderer in `renderers/`.
4. Register the definition in `registry.ts`.
5. Ensure inserted nodes use the registry key and defaults.

Do not create inspector-only schemas or renderer-only prop contracts outside the registry.
