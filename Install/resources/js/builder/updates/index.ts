/**
 * Builder updates — update pipeline.
 * updateComponentProps(componentId, payload): validate component → validate field → patch props → update store → rerender.
 * Sidebar and Chat must both use this function.
 * Chat targeting: useChatTargeting() / getSelectionContext() for editable fields and allowed updates.
 */

export { updateComponentProps } from './updateComponentProps';
export type { UpdatePayload, UpdateResult } from './updateComponentProps';

export { getSelectionContext, useChatTargeting } from './chatTargeting';
export type { SelectionContext, EditableField, AllowedUpdate } from './chatTargeting';
