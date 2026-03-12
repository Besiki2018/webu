# Bugfixer audit (AI Auto Bug Fixer)

This directory holds all artifacts for the self-healing / auto bug-fix pipeline.

## Structure

- **events/** – Normalized bug events (`{bugId}.json`). Source for repro, patch, and tickets.
- **repro/** – Per-bug repro packs: `repro.spec.ts`, `fixtures.json`, `instructions.md`.
- **patches/** – Applied diffs per bug: `{bugId}/applied.diff`.
- **verify/** – Verification step logs: `{bugId}/{step}.log` (lint, typecheck, unit, build, e2e).
- **tickets/** – Generated tickets when fix fails twice: `{bugId}.md`.
- **reports/** – Success reports when a fix is verified: `{bugId}.json`.
- **config.json** – Runtime config: `autoFixEnabled`, `severityThreshold`, `humanApprovalRequired`.

## Commands

- **Intake from log file:**  
  `php artisan bugfixer:process-logs`  
  (also ingests `audit/ai-errors/*.json` into events)

- **Manual fix for one bug:**  
  `npm run bugfixer:fix -- <bugId>`

- **Create ticket only (e.g. medium):**  
  `npx tsx src/bugfixer/cli/createTicket.ts <bugId>`

## Admin

- **Dashboard:** `/admin/bugfixer` (alias: `/admin/qa`)  
  List events, view detail, download repro/patch/ticket, run auto-fix, change settings.

## Rules

- No unverified fixes: every patch must pass lint, typecheck, unit, build, and e2e.
- Max 5 files and 200 diff lines per attempt; max 2 attempts, then ticket only.
- Forbidden paths (auth, tenancy, secrets) are never patched by the pipeline.
