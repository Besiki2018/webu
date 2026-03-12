# Chat editing preparation — targeting

When a component is selected, chat needs **selectedComponentId**, **selectedComponentSchema**, **selectedProps**, plus **editable fields** and **allowed updates**. Chat uses the **same update pipeline** as the sidebar.

## Selection context

From **`useChatTargeting()`** or **`getSelectionContext(storeState)`**:

| Field | Description |
|-------|-------------|
| **selectedComponentId** | ID of the selected node (or null). |
| **selectedNode** | The full `BuilderComponentInstance` (or null). |
| **selectedComponentSchema** | Registry schema for `node.componentKey` (or null). |
| **selectedProps** | Current props (from store or node.props). |
| **editableFields** | `{ key, type, label? }[]` from schema.props / schema.fields. |
| **allowedUpdates** | `{ path, type }[]` — paths chat is allowed to update (same as editable field keys). |

## Chat usage

1. **Get context** — `const ctx = useChatTargeting()` (or `getSelectionContext(useBuilderStore.getState())`).
2. **Check selection** — If `ctx.selectedComponentId == null`, prompt user to select an element.
3. **Editable fields** — Use `ctx.editableFields` to know what can be edited (e.g. title, subtitle, buttonText, variant).
4. **Allowed updates** — Use `ctx.allowedUpdates`; only send updates for these paths.
5. **Apply edits** — Use the **same pipeline** as the sidebar:

   ```ts
   import { updateComponentProps } from '@/builder/updates';

   const result = updateComponentProps(ctx.selectedComponentId, { path: 'title', value: 'New title' });
   if (!result.ok) {
     // result.error, result.message (e.g. field_not_found)
   }
   ```

   Backend can send tool params as `{ componentId, field, value }` or `{ componentId, path, value }`; the client accepts both and calls `updateComponentProps(componentId, { path: path ?? field, value })`. See **Phase 4 — Chat Editing Engine** in `WEBU_AI_BUILDER_ROADMAP.md`.

Validation (component exists, field in schema) is done inside **updateComponentProps**; chat does not bypass it.

## Same update pipeline

- **Sidebar** → `updateComponentProps(node.id, { path: key, value })`.
- **Chat** → When the backend sends a successful `tool_result` for tool `updateComponentProps`, the client calls `updateComponentProps(componentId, { path, value })` with params from the matching `tool_call`. Same validation, same store update, same rerender. See **Phase 7 — Chat editing** (`PHASE7_CHAT_EDITING.md`) for the tool contract and example commands.
