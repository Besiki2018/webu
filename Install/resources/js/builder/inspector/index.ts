/**
 * Builder inspector — sidebar inspector.
 * Schema-driven: reads componentRegistry[componentKey].schema, loops schema.props,
 * generates controls by field type (text → input, color → color picker, etc.).
 */

export { SidebarInspector } from './SidebarInspector';
export type { SidebarInspectorProps, SchemaFieldDef } from './SidebarInspector';
