# Builder Mutation System

## Purpose

Builder V2 routes every edit through one mutation pipeline so canvas, inspector, layers, AI, history, and autosave stay consistent.

The entrypoint is:

- `resources/js/builder/mutations/dispatchBuilderMutation.ts`

## Mutation Lifecycle

Each mutation follows the same sequence:

1. Validate the payload against the current `builderDocument`.
2. Normalize the payload when needed.
3. Apply the mutation to the canonical document tree.
4. Bump document version and timestamp for document-changing mutations.
5. Push a history entry when the mutation changes the document.
6. Mark the store dirty until autosave or publish succeeds.

Autosave is triggered separately by observing dirty versioned state in `useBuilderAutosave`.

## Supported Mutation Types

- `PATCH_NODE_PROPS`
- `PATCH_NODE_STYLES`
- `INSERT_NODE`
- `DELETE_NODE`
- `MOVE_NODE`
- `DUPLICATE_NODE`
- `WRAP_NODE`
- `UNWRAP_NODE`
- `SELECT_NODE`
- `HOVER_NODE`
- `CHANGE_DEVICE_PRESET`
- `UNDO`
- `REDO`

## History Rules

History is snapshot-based and independent from DOM state.

- Structural changes always create history entries.
- Prop and style edits can be grouped with `meta.groupKey`.
- Undo and redo restore both the document snapshot and selection snapshot.

## Integrity Guarantees

- Page root nodes cannot be deleted or moved.
- Nodes cannot be moved into their own subtree.
- Insertions require an existing parent and a unique node id.
- Delete remaps selection to a deterministic fallback when the selected subtree is removed.

## Performance Notes

Hot mutations use structural sharing rather than cloning the whole document on every edit.

- Only touched nodes are cloned.
- Untouched node references are preserved.
- Canvas nodes subscribe by `nodeId`, which limits rerenders to changed branches.
