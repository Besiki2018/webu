# Unified Webu Site Agent v1 – Migration Notes

## Overview

The Unified Webu Site Agent consolidates the previous split between:
- **generate-website** (heuristic/preset-driven)
- **ai-site-editor** (analyze → interpret → execute)
- **ai-project-edit** (workspace file editing)

From the user's perspective, there is now **one agent** for site editing. Chat requests are routed through the unified orchestrator.

## Architecture

### New Services

| Service | Path | Role |
|--------|------|------|
| **UnifiedWebuSiteAgentOrchestrator** | `app/Services/UnifiedAgent/UnifiedWebuSiteAgentOrchestrator.php` | Main orchestrator: context → interpret → execute → response |
| **UnifiedProjectContext** | `app/Services/UnifiedAgent/UnifiedProjectContext.php` | Merged context snapshot (CMS + workspace + selected target) |
| **ContextCollector** | `app/Services/UnifiedAgent/ContextCollector.php` | Collects CMS pages, global components, theme, workspace scan |
| **GeorgianCommandNormalizer** | `app/Services/UnifiedAgent/GeorgianCommandNormalizer.php` | Georgian-first locale, typos, builder/ecommerce lexicon |
| **AgentVerificationService** | `app/Services/UnifiedAgent/AgentVerificationService.php` | Verification before success (text, button, global component) |

### API

- **POST** `/panel/projects/{project}/unified-agent/edit` – Single entry point for edit requests.

### Frontend

- **useAiSiteEditor.runUnifiedEdit()** – Calls the unified agent in one request.
- **Chat.tsx** – Uses `runUnifiedEdit` instead of the 3-step analyze → interpret → execute flow.

## What Changed

1. **Chat flow** – Edit requests go to `runUnifiedEdit`, which POSTs to `/unified-agent/edit`. No separate analyze/interpret/execute calls.
2. **Backend** – The orchestrator collects context internally, calls `AiInterpretCommandService`, then `AiAgentExecutorService`. Same underlying services, single orchestration path.
3. **Georgian** – `GeorgianCommandNormalizer` resolves locale and normalizes prompts before interpretation.

## What Stayed the Same

- **AiInterpretCommandService** – Still used for deterministic rules and AI interpretation.
- **AiAgentExecutorService** – Still applies change sets to CMS.
- **AiSiteEditorAnalyzeService** – Still used by `ContextCollector` for page structure.
- **Legacy endpoints** – `/ai-site-editor/analyze`, `/ai-interpret-command`, `/ai-site-editor/execute` remain for backward compatibility (e.g. pending plan confirmation, tooling).
- **ai-project-edit** – Still used when `shouldPreferProjectEdit()` routes to workspace editing.
- **generate-website** – Still separate; generation migration into the orchestrator is planned.

## Verification

`AgentVerificationService` provides verification helpers. The orchestrator does not yet enforce verification before success; that is a follow-up task. The executor already returns `no_effect` when no visible change occurs.

## Georgian Support

- **GeorgianCommandNormalizer** – Typos (ქარტულად → ქართულად, დიზიანი → დიზაინი), builder terms (ჰედერ, ფუტერ, მაღაზია), ecommerce terms.
- **AiInterpretCommandService** – Existing Georgian handling (deterministic rules, prompt locale) is unchanged.
- Locale is resolved as Georgian-first when the prompt contains Georgian script or romanized hints.

## Next Steps (Planned)

1. **Iterative agent loop** – Read → execute → verify → re-plan when verification fails.
2. **Generation in orchestrator** – Move `GenerateWebsiteProjectService` (or `AiDesignDirectorOrchestrator`) into the orchestrator for site creation.
3. **Mandatory verification** – Enforce `AgentVerificationService` before returning success.
4. **Integration tests** – More scenarios (Georgian text replacement, header/footer, selected element).
5. **Browser e2e** – Create site from Georgian prompt, change headline, header/footer, CTA.
