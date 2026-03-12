# Phase 7 — Chat Editing

Chat edits component props using the **same update pipeline** as the sidebar. No special chat-only logic.

## Update pipeline

- **Sidebar:** `updateComponentProps(node.id, { path: key, value })` (see `SidebarInspector.tsx`).
- **Chat:** When the backend sends a successful `tool_result` for tool `updateComponentProps`, the client calls **the same** `updateComponentProps(componentId, { path, value })` (see `hooks/useBuilderChat.ts` → `handleToolResult`).

Validation (component in tree, field in schema) is done inside `updateComponentProps`; chat does not bypass it.

## Tool contract (backend → client)

- **Tool name:** `updateComponentProps`
- **Params** (from the matching `tool_call.params`):
  - `componentId` (or `component_id`) — string, node id in `componentTree`
  - `path` — string or string[], schema field key (e.g. `title`, `buttonText`, `image`, `backgroundColor`, `subtitle`, `padding`)
  - `value` — value to set (string, number, boolean, etc.)

When the client receives `tool_result` with `tool === 'updateComponentProps'` and `success === true`, it looks up the corresponding `tool_call` by `id`, reads `params`, and calls `updateComponentProps(componentId, { path, value })`.

## Example commands chat must support

| User intent              | Schema path(s)     | Example value   |
|--------------------------|--------------------|-----------------|
| Change hero title       | `title`            | `"New headline"` |
| Change button text      | `buttonText`       | `"Get Started"` |
| Replace hero image      | `image`            | image URL       |
| Change section background color | `backgroundColor` | `"#1a1a2e"` |
| Update subtitle         | `subtitle`         | `"New subtitle"` |
| Increase padding        | `padding`          | `"2rem"`        |

Paths and types come from the registry schema (`getEntry(componentKey).schema`). Selection context for chat (e.g. which component is selected and which fields are editable) is provided by `useChatTargeting()` / `getSelectionContext()` — see `CHAT_TARGETING.md`.

## Where it is implemented

| Responsibility | Location |
|----------------|----------|
| Apply prop updates (sidebar + chat) | `builder/updates/updateComponentProps.ts` |
| Sidebar: call update on field change | `builder/inspector/SidebarInspector.tsx` |
| Chat: apply when tool_result is updateComponentProps | `hooks/useBuilderChat.ts` → `handleToolResult` |
| Selection context for chat (editable fields, allowed paths) | `builder/updates/chatTargeting.ts` |
