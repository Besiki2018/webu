# CMS AI Generation Output Schema v1

P4-E1-02: strict AI output payload contract for builder-native artifacts.

Output structure uses builder_nodes[] per page. Mapping to current page revision/content model:

- page_json and page_css are derived from builder-native output.
- page_revisions.content_json stores the canonical content.
- Schema: cms-ai-generation-output.v1.schema.json and cms-canonical-page-node.v1.schema.json.
- meta.validation_expectations define validation rules.
