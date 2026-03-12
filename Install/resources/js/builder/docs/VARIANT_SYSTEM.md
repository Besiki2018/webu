# Component variant system

Components like Header1, Header2, Header3 become a **single component** (e.g. Header) with a **variant** that selects the layout.

## Storage

- **`node.variant`** — canonical variant for the instance (e.g. `'header-1'`, `'hero-2'`, `'default'`, `'center'`, `'mega'`).
- Optionally mirrored in **`node.props.variant`** for the component’s prop API.

## Data model

```ts
interface BuilderComponentInstance {
  id: string;
  componentKey: string;   // e.g. webu_header_01 (one registry entry for Header)
  variant?: string;       // e.g. 'header-1' | 'header-2' | 'default' | 'center' | 'mega'
  props: Record<string, unknown>;
  children?: BuilderComponentInstance[];
  // ...
}
```

## Rendering

1. **Canvas** passes **`node.variant ?? node.props.variant`** into the merged props so the component receives `props.variant`.
2. **Component** chooses layout with **`switch (variant)`** (or equivalent):

   ```ts
   function Header(props: HeaderProps) {
     const variant = props.variant ?? 'default';
     switch (variant) {
       case 'center': return <HeaderCenter {...props} />;
       case 'mega':   return <HeaderMega {...props} />;
       default:       return <HeaderDefault {...props} />;
     }
   }
   ```

3. **Sidebar** shows and edits the `variant` field from schema; value comes from **`node.variant ?? node.props.variant`**.
4. **Update pipeline** (`updateComponentProps`) keeps **`node.variant`** in sync when the user changes the variant field.

## Registry

- One registry entry per **component** (e.g. `header` / `webu_header_01`), not per variant.
- Schema defines **variant** as a field (e.g. type `select` with options `default`, `center`, `mega`).
- Defaults include **`variant: 'default'`** (or the first variant).

## Summary

| Before              | After                          |
|---------------------|--------------------------------|
| Header1, Header2, Header3 | Header with `variant = default \| center \| mega` |
| Multiple components | Single component, `switch(variant)` |
| —                   | Variant stored in **node.variant** |
