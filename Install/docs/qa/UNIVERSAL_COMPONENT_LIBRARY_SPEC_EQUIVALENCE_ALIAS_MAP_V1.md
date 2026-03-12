# Universal Component Library Spec Equivalence Alias Map v1

This document describes the **cms-component-library-spec-equivalence-alias-map.v1.schema.json** and the **JSON alias map** artifact used by the CMS component library to resolve source spec component keys to canonical builder keys.

## Artifacts

- **cms-component-library-spec-equivalence-alias-map.v1.schema.json** — JSON Schema for the alias map file.
- **UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json** — Runtime alias resolver data (70 mappings).
- **cms-component-library-spec-equivalence-alias-map-export.v1.schema.json** — Schema for the CLI export payload (summary, stats, fingerprints, mappings).
- **Runtime alias resolver helper** — `CmsComponentLibrarySpecEquivalenceAliasMapService` loads the JSON and provides `resolveCanonicalBuilderKeys`, `findSourceComponentKeysForCanonicalBuilderKey`, etc.
- **CLI validate/debug command** — `php artisan cms:component-library-alias-map-validate` validates the map and supports **--export-json** to emit a **JSON export report** for CI.

## Export schema and usage

The **cms-component-library-spec-equivalence-alias-map-export.v1.schema.json** defines the shape of the output when running:

```bash
php artisan cms:component-library-alias-map-validate --export-json
```

The export includes `summary`, `stats`, `fingerprints`, and `mappings`, and is used for CI and tooling.
