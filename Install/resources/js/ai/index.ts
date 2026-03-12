/**
 * Webu AI Editing Command Library
 * Converts natural-language commands into structured ChangeSet operations.
 */

export {
  changeSetSchema,
  validateChangeSet,
  parseChangeSet,
  type ChangeSet,
  type ChangeSetOperation,
  type UpdateSectionOp,
  type InsertSectionOp,
  type DeleteSectionOp,
  type ReorderSectionOp,
  type UpdateThemeOp,
  type UpdateTextOp,
  type ReplaceImageOp,
  type UpdateButtonOp,
  type AddProductOp,
  type RemoveProductOp,
  type TranslatePageOp,
  type GenerateContentOp,
} from './changes/changeSet.schema';

export {
  applyChangeSetToSections,
  type SectionItem,
} from './changes/applyChangeSet';

export {
  interpretCommand,
  getFullSystemPrompt,
  type AiCompleteFn,
  type InterpretCommandOptions,
  type InterpretCommandOutput,
  type InterpretCommandResult,
  type InterpretCommandError,
} from './commands/interpretCommand';

export {
  SYSTEM_PROMPT,
  buildUserPrompt,
  ALLOWED_OPERATIONS,
  CHANGE_SET_JSON_EXAMPLE,
} from './commands/prompts';

export type { PageContext, SectionInfo, SelectedElementContext } from './commands/context';
export { summarizePageContextForPrompt } from './commands/context';

export { COMMAND_CATEGORIES, COMMAND_CATEGORY_LIST } from './commands/categories';
export type { CommandCategoryKey } from './commands/categories';

export {
  ALL_COMMAND_PATTERNS,
  QUICK_COMMAND_SUGGESTIONS,
  CONTENT_EDITING_PATTERNS,
  LAYOUT_EDITING_PATTERNS,
  THEME_EDITING_PATTERNS,
  SECTION_MANAGEMENT_PATTERNS,
  ECOMMERCE_PATTERNS,
  SEO_PATTERNS,
  LANGUAGE_PATTERNS,
} from './commands/patterns';
export type { CommandPattern } from './commands/patterns';

export {
  createPageStateSnapshot,
  createUndoStack,
  pushUndo,
  popUndo,
  canUndo,
  type PageStateSnapshot,
  type UndoEntry,
  type UndoStack,
} from './undo/undoSupport';

// AI agent tools (project workspace file operations + preview reload)
export {
  executeTool,
  TOOL_NAMES,
  getExecutionLog,
  clearExecutionLog,
  type ToolName,
  type AiToolContext,
  type ToolResult,
} from './toolExecutor';
export type { ToolExecutionLogEntry } from './logs/executionLog';
export * from './tools';

// Project codebase scanner (for AI context before edits)
export {
  scanCodebase,
  invalidateScanCache,
  structureToContextSummary,
} from './codebaseScanner';
export type { ScanOptions, ProjectStructure, CodebaseScanOutput, CodebaseScanResult, CodebaseScanError } from './codebaseScanner';
export { EMPTY_STRUCTURE } from './codebaseScanner/types';

// AI Site Planner (structured website plan from user prompt)
export { generateSitePlan, FALLBACK_PLAN } from './sitePlanner';
export type {
  SitePlan,
  PagePlan,
  GenerateSitePlanOptions,
  GenerateSitePlanResult,
} from './sitePlanner';

// Design Intelligence (layout, spacing, typography rules for AI generation)
export {
  CONTAINER_WIDTHS,
  CONTAINER_CLASS,
  CONTAINER_STRUCTURE,
  SPACING,
  TYPOGRAPHY,
  GRID,
  BREAKPOINTS,
  SECTION_STRUCTURES,
  DESIGN_RULES_SPEC,
  getDesignRulesForPrompt,
} from './designSystem';
export type { DesignRulesSpec, ContainerWidths, SpacingRule, TypographyScale, GridSystem, Breakpoints, SectionComposition } from './designSystem';

// AI Component Generator (generate missing section components via Agent Tools)
export { ensureSectionExists, normalizeSectionName } from './componentGenerator';
export type {
  EnsureSectionExistsOptions,
  EnsureSectionExistsResult,
  EnsureSectionExistsReused,
  EnsureSectionExistsCreated,
  EnsureSectionExistsError,
} from './componentGenerator';

// Layout Refiner (spacing, container, typography refinement)
export { runLayoutRefiner, runLayoutRefinerWithAI, applyLayoutRefinement, applyLayoutRefinementWithAI } from './layoutRefiner';
export type { LayoutRefinerInput, LayoutRefinerInputWithAI, LayoutRefinerResult } from './layoutRefiner';

// AI Autopilot (full website generation from single prompt)
export { runAutopilot } from './autopilot';
export type { AutopilotOptions, AutopilotExecutionLog } from './autopilot';

// AI Memory and Design Learning
export {
  loadDesignMemory,
  saveDesignMemory,
  getDesignPatternsForType,
  loadLayoutMemory,
  saveLayoutMemory,
  inferWebsiteTypeFromPrompt,
} from './memory';
export type { DesignMemoryRecord, LayoutMemoryRecord } from './memory';
