# Universal Binding Namespace Compatibility (P5-F5-03)

Compatibility rules for binding namespaces across components.

## Canonical namespace compatibility and validator coverage

- **CmsCanonicalBindingResolver** — Resolves binding namespaces for universal builder components and maps content to canonical namespaces (e.g. `content.properties`, `content.rooms`).
- **CmsBindingExpressionValidator** — Validates binding expressions and ensures compatibility with the **CanonicalControlGroup** surface in Cms.tsx (content, layout, style, advanced, responsive, states, data, bindings, meta).

## Component binding namespaces

- `webu_hotel_room_availability_01` — hotel room availability (e.g. content.rooms).
- `webu_realestate_map_01` — real estate map bindings (e.g. content.properties).
- `webu_book_slots_01` — booking slots and reservation bindings.
