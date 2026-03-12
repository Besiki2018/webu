# Phase 4 — Sidebar Inspector Uses Schema

## Sidebar / inspector location

- **Primary implementation:** `Install/resources/js/Pages/Project/Cms.tsx`
- **Relevant pieces:** `selectedSectionSchemaProperties` (from `sectionSchemaByKey`), `collectSchemaPrimitiveFields`, `renderSchemaFieldEditorControl`, `buildCanonicalControlGroupFieldSets`, `renderCanonicalControlGroupFieldSets`

The builder sidebar (settings panel) that edits section props is inside the CMS page; when a section is selected, it shows controls derived from the section’s schema.

---

## Field definitions source: schema (registry)

Field definitions do **not** come from manually written inputs. They come from:

1. **Section schema**  
   For the selected section, schema is taken from `sectionSchemaByKey.get(selectedSectionDraft.type)`.  
   `sectionSchemaByKey` is built from **builderSectionLibrary**, where each item’s `schema_json` is either:
   - the server-provided `item.schema_json`, or
   - **fallback:** `getComponentSchemaJson(normalizedKey)` from the **builder component registry** (`builder/componentRegistry.ts`).

2. **Schema shape**  
   Registry schema from `getComponentSchemaJson()` has a **properties** object: nested keys with per-field definitions. Each definition includes:
   - `type` (JSON Schema: string, number, integer, boolean)
   - `title`, `default`, `enum`, `description`
   - **`builder_field_type`** (builder field type: text, richtext, color, image, link, menu, select, boolean, number, spacing, alignment, etc.)
   - `control_group`, `responsive`, `chat_editable`, etc.

3. **From properties to fields**  
   `collectSchemaPrimitiveFields(selectedSectionSchemaProperties)` walks **schema.properties** (and nested `properties`) and builds a list of **SchemaPrimitiveField** (path, type, label, definition, control_meta). So the list of fields is **schema-driven**, not hardcoded per component.

4. **Control selection**  
   For each field, the sidebar renders a control by:
   - Preferring **schema-driven control type** from `field.definition.builder_field_type` when present (via `getSchemaFieldControlType(field)`).
   - Falling back to heuristics (path/format/label) when `builder_field_type` is missing (e.g. legacy or server-only schema).

So: **field definitions come from schema.properties** (registry or server); **control type** comes from **schema’s builder_field_type** when available.

---

## Target logic (schema-driven control generation)

The intended pattern is implemented:

- **For each field** in the collected schema fields (after filtering for inspector target and tabs), the sidebar calls **renderSchemaFieldEditorControl(field, ...)**.
- **Control type** is chosen from:
  - **Schema:** `getSchemaFieldControlType(field)` reads `field.definition.builder_field_type` and maps it to a control kind.
  - **Fallback:** Existing heuristics (e.g. `isSchemaColorField`, `isSchemaImageField`, path/format) when `builder_field_type` is absent.

Supported control types (schema `builder_field_type` → UI) include:

| Schema builder_field_type | Control / behavior |
|---------------------------|--------------------|
| text                      | Text input         |
| richtext                  | Textarea           |
| color                     | Color picker + text|
| image                     | Media (image)      |
| video                     | Media (video)      |
| icon                      | Icon (treated as text if no picker) |
| link                      | Link object (label + URL) |
| menu                      | Menu / nav source select |
| select / layout-variant / style-variant | Select dropdown |
| boolean                   | Toggle (true/false select) |
| number                    | Number input       |
| spacing                   | Spacing (padding/margin group) |
| alignment                 | Alignment select   |

Rendering flow:

- `selectedSectionEditableSchemaFieldsForDisplay` → filtered by target and tab
- `buildCanonicalControlGroupFieldSets(fields)` → group by content/style/advanced/responsive/etc.
- For each group and each field → `renderField(field)` → `renderSchemaFieldEditorControl({ field, ... })`
- Inside `renderSchemaFieldEditorControl`, **schema control type is used first** (color, image, link when `builder_field_type` is set), then fallbacks.

---

## Refactor applied (Phase 4)

- **`getSchemaFieldControlType(field)`** added in Cms.tsx. It returns a normalized control type from `field.definition.builder_field_type` (or `builderFieldType`) when present, otherwise `null`.
- **Color and image:** Control type is now **schema-driven when possible**: `isColorField = schemaControlType === 'color' || (schemaControlType === null && isSchemaColorField(field))`, and similarly for image. So if the registry schema says `builder_field_type: 'color'` or `'image'`, that is used instead of path/format heuristics.
- **Link:** When schema says `builder_field_type: 'link'`, the link control is shown even if the current value is still a string; the UI coerces to `{ label, url }` for display and edits.

No separate hardcoded UI branch per component: the same loop over schema-derived fields and the same `renderSchemaFieldEditorControl` are used for all section types; only the schema (and optional variant merge) differs per section.

---

## Summary

| Requirement | Status |
|-------------|--------|
| Field definitions from schema (not manual) | Yes — from schema.properties (registry or server) |
| For each field, render control by type      | Yes — loop over collected fields; control from schema builder_field_type + fallbacks |
| Supported types (text, textarea, color, image, icon, link, menu, select, toggle, number, spacing, alignment) | Yes — mapped in getSchemaFieldControlType and in render (color, image, link, select, boolean, number, spacing) |
| Schema-driven control generation            | Yes — builder_field_type preferred when present |
