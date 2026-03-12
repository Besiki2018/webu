# CMS Telemetry Collector (P6-G1-01)

Scope for CMS builder and runtime telemetry collection.

## P6-G1-01 — Collector scope

- **CmsTelemetryCollectorService** — Collects and forwards builder/runtime telemetry events (schema `cms.telemetry.event.v1`).
- Builder events: **cms_builder.save_draft**, cms_builder.open, cms_builder.publish_page, etc.
- Runtime events: **cms_runtime.route_hydrated**, cms_runtime.hydrate_failed.

## Deferred pipeline (P6-G1-02, P6-G1-03, P6-G1-04)

- P6-G1-02 — Storage pipeline and retention.
- P6-G1-03 — Aggregation and dashboards.
- P6-G1-04 — Privacy and sampling.
