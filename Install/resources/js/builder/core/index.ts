/**
 * Builder core — base types and interfaces.
 * Foundation layer: shared contracts for registry, schema, renderer, store, inspector, updates.
 * No UI; types and interfaces only.
 */

export type {
  BuilderComponentSchema,
  BuilderFieldDefinition as BuilderSchemaFieldDefinition,
  BuilderFieldOption as BuilderSchemaFieldOption,
  BuilderFieldGroupDefinition,
  BuilderVariantDefinition,
  BuilderResponsiveSupport,
  BuilderComponentDefaults,
  BuilderComponentVariant,
  BuilderComponentRegistryEntry,
  BuilderComponentInstance,
  ResponsiveValue,
  BuilderUpdatePayload,
  BuilderUpdateOperation,
} from './types';

export type { BuilderPageModel, BuilderPageNode } from './pageModel';
export {
  toSerializableNode,
  serializePageModel,
  parsePageModel,
} from './pageModel';

// Field type system — schema-driven fields (key, type, group, options)
export {
  BUILDER_FIELD_TYPES,
  BUILDER_FIELD_GROUPS,
  isBuilderFieldType,
  isBuilderFieldGroup,
} from './fieldTypes';
export type {
  BuilderFieldType,
  BuilderFieldGroup,
  BuilderFieldDefinition,
  BuilderFieldOption,
} from './fieldTypes';
