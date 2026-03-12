# Phase 6 — Props From Builder State

Components receive props from builder state only. No hidden content.

## Contract

- **Props** = saved component props + default props.
- **Render** = `<Component {...props} />`

## Where it is enforced

| Path | File | Flow |
|------|------|------|
| Schema-driven canvas | `builder/renderer/CanvasRenderer.tsx` | `savedProps` = node.props; `mergedProps` = mergeDefaults(entry.defaults, savedProps); `componentProps` = mapBuilderProps(mergedProps); `<Component {...componentProps} />` |
| Legacy canvas (central) | `builder/visual/BuilderCanvas.tsx` | `savedProps` = parseSectionProps(section.props ?? section.propsText); `mergedProps` = mergeDefaults(centralEntry.defaults, savedProps); `componentProps` = mapBuilderProps(mergedProps); `<Component {...componentProps} />` |
| Defaults merge | `builder/utils/mergeDefaults.ts` | mergeDefaults(defaults, props) → result = defaults + props (props override) |
| Registry mapBuilderProps | `builder/registry/componentRegistry.ts` | Each entry’s mapBuilderProps applies entry defaults when a value is missing so the component always gets full props. |

## Verification

- CanvasRenderer: see comment block “Props contract (Phase 6)” and the use of mergeDefaults + mapBuilderProps.
- BuilderCanvas: central-entry branch uses mergeDefaults(centralEntry.defaults, parseSectionProps(...)) then mapBuilderProps; render is <Component {...componentProps} />.
- All builder components must render purely from props (see builderCompatibility.ts).
