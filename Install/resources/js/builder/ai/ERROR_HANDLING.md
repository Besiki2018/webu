# Part 11 — Error Handling

## Unmapped layout blocks → generic section

If a layout block **cannot be mapped** to a component (unknown block type or resolved key not in registry), the pipeline **falls back to a generic section**.

- **Constant:** `DEFAULT_GENERIC_SECTION_REGISTRY_ID` (in `builder/componentRegistry.ts`).
- **Current implementation:** Uses the **features** component (`webu_general_features_01`) as the generic section — flexible title + items, works for arbitrary content. When a dedicated **GenericSection** component is added to the registry, point `DEFAULT_GENERIC_SECTION_REGISTRY_ID` to it.
- **Where:** `sectionMapper.resolveComponentKey()` returns `DEFAULT_GENERIC_SECTION_REGISTRY_ID` when the block’s type has no mapping or the mapped key is not in the registry.

Example: vision returns a block type that isn’t in the slug → component map → section appears as the generic section (features) so the page still renders and stays editable.
