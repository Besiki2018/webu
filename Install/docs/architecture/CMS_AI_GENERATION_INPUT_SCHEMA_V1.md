# CMS AI Generation Input Schema v1

P4-E1-01: This document describes current Webu platform payload shapes used for AI generation input.

Payload is built by ProjectCmsController and includes:

- platform_context.project
- platform_context.site
- platform_context.template_blueprint
- platform_context.site_settings_snapshot

Template blueprint includes theme_settings, default_pages, default_sections. Site settings include seo_title, items_json and related fields.
