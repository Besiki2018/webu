/**
 * ChangeSet schema for AI website editing commands.
 * All operations are structured so they can be validated and applied (or reversed for undo).
 */
import { z } from 'zod';

// ---------------------------------------------------------------------------
// Operation schemas (discriminated union)
// ---------------------------------------------------------------------------

export const updateSectionOpSchema = z.object({
  op: z.literal('updateSection'),
  sectionId: z.string(),
  patch: z.record(z.unknown()),
});

export const insertSectionOpSchema = z.object({
  op: z.literal('insertSection'),
  sectionId: z.string().optional(),
  sectionType: z.string(),
  props: z.record(z.unknown()).optional(),
  afterSectionId: z.string().optional(),
  index: z.number().int().min(0).optional(),
});

export const deleteSectionOpSchema = z.object({
  op: z.literal('deleteSection'),
  sectionId: z.string(),
});

export const reorderSectionOpSchema = z.object({
  op: z.literal('reorderSection'),
  sectionId: z.string(),
  toIndex: z.number().int().min(0),
});

export const updateThemeOpSchema = z.object({
  op: z.literal('updateTheme'),
  patch: z.record(z.unknown()),
});

export const updateTextOpSchema = z.object({
  op: z.literal('updateText'),
  sectionId: z.string().optional(),
  path: z.string().optional(),
  value: z.string(),
});

/** Set any schema prop by path (e.g. padding, backgroundColor). Use editableFields/chatTargets prop names. */
export const setFieldOpSchema = z.object({
  op: z.literal('setField'),
  sectionId: z.string(),
  path: z.union([z.string(), z.array(z.string())]),
  value: z.unknown(),
});

export const replaceImageOpSchema = z.object({
  op: z.literal('replaceImage'),
  sectionId: z.string(),
  imageKey: z.string().optional(),
  url: z.string().optional(),
  alt: z.string().optional(),
});

export const updateButtonOpSchema = z.object({
  op: z.literal('updateButton'),
  sectionId: z.string(),
  label: z.string().optional(),
  href: z.string().optional(),
  variant: z.string().optional(),
});

export const addProductOpSchema = z.object({
  op: z.literal('addProduct'),
  count: z.number().int().min(1).optional(),
  category: z.string().optional(),
  props: z.record(z.unknown()).optional(),
});

export const removeProductOpSchema = z.object({
  op: z.literal('removeProduct'),
  productId: z.string().optional(),
  index: z.number().int().min(0).optional(),
});

export const translatePageOpSchema = z.object({
  op: z.literal('translatePage'),
  targetLocale: z.string(),
  sourceLocale: z.string().optional(),
});

export const generateContentOpSchema = z.object({
  op: z.literal('generateContent'),
  sectionId: z.string().optional(),
  instruction: z.string(),
  patch: z.record(z.unknown()).optional(),
});

export const operationSchema = z.discriminatedUnion('op', [
  updateSectionOpSchema,
  insertSectionOpSchema,
  deleteSectionOpSchema,
  reorderSectionOpSchema,
  updateThemeOpSchema,
  updateTextOpSchema,
  setFieldOpSchema,
  replaceImageOpSchema,
  updateButtonOpSchema,
  addProductOpSchema,
  removeProductOpSchema,
  translatePageOpSchema,
  generateContentOpSchema,
]);

// ---------------------------------------------------------------------------
// ChangeSet
// ---------------------------------------------------------------------------

export const changeSetSchema = z.object({
  operations: z.array(operationSchema),
  summary: z.array(z.string()),
});

export type ChangeSet = z.infer<typeof changeSetSchema>;
export type ChangeSetOperation = z.infer<typeof operationSchema>;
export type UpdateSectionOp = z.infer<typeof updateSectionOpSchema>;
export type InsertSectionOp = z.infer<typeof insertSectionOpSchema>;
export type DeleteSectionOp = z.infer<typeof deleteSectionOpSchema>;
export type ReorderSectionOp = z.infer<typeof reorderSectionOpSchema>;
export type UpdateThemeOp = z.infer<typeof updateThemeOpSchema>;
export type UpdateTextOp = z.infer<typeof updateTextOpSchema>;
export type SetFieldOp = z.infer<typeof setFieldOpSchema>;
export type ReplaceImageOp = z.infer<typeof replaceImageOpSchema>;
export type UpdateButtonOp = z.infer<typeof updateButtonOpSchema>;
export type AddProductOp = z.infer<typeof addProductOpSchema>;
export type RemoveProductOp = z.infer<typeof removeProductOpSchema>;
export type TranslatePageOp = z.infer<typeof translatePageOpSchema>;
export type GenerateContentOp = z.infer<typeof generateContentOpSchema>;

/** Validate a ChangeSet; returns result with success or ZodError. */
export function validateChangeSet(data: unknown): z.SafeParseReturnType<unknown, ChangeSet> {
  return changeSetSchema.safeParse(data);
}

/** Parse and throw on invalid ChangeSet. */
export function parseChangeSet(data: unknown): ChangeSet {
  return changeSetSchema.parse(data);
}
