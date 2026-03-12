# CMS AI Generation Compatibility Policy v1

P4-E1-04: Compatibility outcomes for schema versions.

Outcomes: compatible, compatible_with_warnings, incompatible.

Referenced schemas:
- cms-ai-generation-input.v1.schema.json
- cms-ai-generation-output.v1.schema.json
- cms-canonical-page-node.v1.schema.json
- cms-canonical-component-registry-entry.v1.schema.json

Contract and validation: meta.contracts.* and meta.validation_expectations.*.

Mapping to storage: page_json, page_css, current page revision/content model. Validation is implemented in CmsAiSchemaValidationService.
