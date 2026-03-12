# Universal Component Library Activation (P5-F5-01 / P5-F5-02)

Baseline for universal and vertical builder component activation in **Cms.tsx**.

## P5-F5-01 — Universal taxonomy and library rendering

- **BuilderUniversalTaxonomyGroupKey** — Type for taxonomy group keys (general, ecommerce, booking, portfolio, real_estate, restaurant, hotel, design).
- **BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER** — Defines the order of taxonomy groups in the builder library.
- **BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS** — Labels per group for the UI.
- **builderSectionAvailabilityMatrix** — Centralized project-type availability matrix; **isBuilderSectionAllowedByProjectTypeAvailabilityMatrix** gates which sections are shown per project type and **project_type_allowed** / module availability.

## P5-F5-02 — Project-type and module gating

- **Cms.tsx** enforces **isBuilderSectionAllowedByProjectTypeAvailabilityMatrix** using **builderSectionAvailabilityMatrix**, **isModuleProjectTypeAllowed**, **isModuleAvailable**, and **requiredModules** per rule.

## Related

- **P5-F5-03** — Universal binding namespace compatibility (see UNIVERSAL_BINDING_NAMESPACE_COMPATIBILITY_P5_F5_03.md).
- **P5-F5-04** — Follow-up scope for component library activation and deferred features.
