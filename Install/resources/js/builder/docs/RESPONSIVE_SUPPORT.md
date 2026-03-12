# Responsive props support

Responsive values use breakpoints **desktop**, **tablet**, **mobile** and the type **ResponsiveValue&lt;T&gt;** so a single prop can have different values per breakpoint.

## Structure

- **Breakpoints:** `desktop` | `tablet` | `mobile` (largest to smallest viewport).
- **Type:** `ResponsiveValue<T>` (e.g. `ResponsiveValue<number>`, `ResponsiveValue<string>`).

## Example fields

- **padding** — `ResponsiveValue<string>` (e.g. `"1rem"` base, `"0.5rem"` mobile)
- **fontSize** — `ResponsiveValue<number>` or `ResponsiveValue<string>`
- **gridColumns** — `ResponsiveValue<number>`
- **visibility** — `ResponsiveValue<boolean>` or per-breakpoint hide/show

## Type (core)

```ts
interface ResponsiveValue<T = unknown> {
  base?: T;      // default when no breakpoint override
  desktop?: T;
  tablet?: T;
  mobile?: T;
  [breakpoint: string]: T | undefined;
}
```

## Utils (builder/utils)

- **RESPONSIVE_BREAKPOINTS** — `['desktop', 'tablet', 'mobile']`
- **getResponsiveValue(value, breakpoint)** — resolve value for breakpoint: `value[breakpoint] ?? value.base`; if `value` is plain, return as-is.
- **getResponsiveValueOr(value, breakpoint, fallback)** — same with fallback when undefined.
- **setResponsiveValue(current, breakpoint, value)** — return a new ResponsiveValue with that breakpoint set.
- **isResponsiveValue(x)** — type guard.
- **isResponsiveBreakpoint(s)** — type guard for breakpoint strings.

## Store

- **currentBreakpoint** — `'desktop' | 'tablet' | 'mobile'` from `useBuilderStore()`.
- Canvas or components use **getResponsiveValue(prop, currentBreakpoint)** to get the effective value for the active breakpoint.

## Usage in components

```ts
import { getResponsiveValueOr } from '@/builder/utils';
import { useBuilderStore } from '@/builder/store';

// In component:
const currentBreakpoint = useBuilderStore((s) => s.currentBreakpoint);
const padding = getResponsiveValueOr(props.padding, currentBreakpoint, '1rem');
const fontSize = getResponsiveValueOr(props.fontSize, currentBreakpoint, 16);
```

## Stored shape

Responsive overrides can live in **node.props** (e.g. `props.padding = { base: '1rem', mobile: '0.5rem' }`) or under **node.props.responsive** (e.g. `props.responsive.desktop.padding_top`). The merge and update pipeline support both; **getResponsiveValue** works on the ResponsiveValue shape (base + desktop/tablet/mobile).
