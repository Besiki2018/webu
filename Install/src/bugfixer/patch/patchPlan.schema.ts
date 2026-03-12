import { z } from 'zod';

export const patchChangeSchema = z.object({
  file: z.string(),
  type: z.enum(['edit', 'add', 'delete']),
  diff: z.string(),
});

export const patchPlanSchema = z.object({
  summary: z.string(),
  rootCause: z.string(),
  changes: z.array(patchChangeSchema),
  testsToRun: z.array(z.string()),
  risk: z.enum(['low', 'medium', 'high']),
  rollbackPlan: z.string(),
});

export type PatchChange = z.infer<typeof patchChangeSchema>;
export type PatchPlan = z.infer<typeof patchPlanSchema>;

export function parsePatchPlan(json: unknown): PatchPlan {
  return patchPlanSchema.parse(json);
}

export function parsePatchPlanSafe(json: unknown): { success: true; data: PatchPlan } | { success: false; error: z.ZodError } {
  const result = patchPlanSchema.safeParse(json);
  if (result.success) return { success: true, data: result.data };
  return { success: false, error: result.error };
}
