/**
 * Builder command: Generate site from structure or blueprint.
 *
 * Generation path is explicit:
 * - direct structure when structure is provided
 * - blueprint assembly when only blueprint is provided
 * - emergency fallback only when requested explicitly
 */

import type { ProjectBlueprint } from '../ai/blueprintTypes';
import { buildSiteFromBlueprint } from '../ai/buildSiteFromBlueprint';
import type { BuildGenerationDiagnostics, BlueprintGenerationLogEntry } from '../ai/blueprintTypes';
import {
  createEmergencyFallbackBlueprint,
  mapBuilderProjectTypeToBlueprintProjectType,
} from '../ai/createBlueprint';
import {
  appendGenerationDiagnosticsEvent,
  buildGenerationDiagnostics,
  createGenerationLogEntry,
  GenerationTraceError,
  isGenerationTraceError,
} from '../ai/generationTracing';
import {
  getAllowedComponentCatalogIndex,
  type AiComponentCatalogIndex,
} from '../ai/componentCatalog';
import {
  buildTreeFromStructure,
  type SiteStructureSection,
} from '../aiSiteGeneration';
import { clonePlainData, stableSerialize } from '../ai/stableSerialize';
import {
  formatGeneratedSiteValidationIssues,
  validateGeneratedSite,
} from '../ai/validateGeneratedSite';
import { resolveComponentRegistryKey } from '../componentRegistry';
import type { ProjectType } from '../projectTypes';
import { isProjectType } from '../projectTypes';
import { useBuilderStore } from '../store/builderStore';

export const GENERATE_SITE_COMMAND = 'generate_site';

export type GenerateSiteMode = 'blueprint' | 'direct-structure' | 'emergency-fallback';

export interface GenerateSiteParams {
  projectType?: ProjectType | string;
  blueprint?: ProjectBlueprint;
  structure?: SiteStructureSection[];
  generationMode?: GenerateSiteMode;
}

export interface GenerateSiteTrace {
  requestedMode: GenerateSiteMode | null;
  resolvedMode: GenerateSiteMode | 'error';
  projectType: ProjectType;
  hasBlueprint: boolean;
  structureCount: number;
}

export interface GenerateSiteResult {
  ok: boolean;
  projectType: ProjectType;
  nodeCount: number;
  generationMode?: GenerateSiteMode;
  trace: GenerateSiteTrace;
  error?: string;
  diagnostics?: BuildGenerationDiagnostics;
}

type BuilderStoreSnapshot = {
  projectType: ProjectType;
  componentTree: ReturnType<typeof useBuilderStore.getState>['componentTree'];
  selectedComponentId: string | null;
  hoveredComponentId: string | null;
  currentBreakpoint: ReturnType<typeof useBuilderStore.getState>['currentBreakpoint'];
  builderMode: ReturnType<typeof useBuilderStore.getState>['builderMode'];
  selectedProps: Record<string, unknown> | null;
};

type TreeSignatureNode = {
  id: string;
  componentKey: string;
  variant: string | null;
  props: Record<string, unknown>;
  children: TreeSignatureNode[];
  responsive: Record<string, Record<string, unknown>> | null;
  responsiveOverrides: Record<string, Record<string, unknown>> | null;
};

function resolveProjectType(projectType: ProjectType | string | undefined, blueprint?: ProjectBlueprint): ProjectType {
  if (isProjectType(projectType)) {
    return projectType;
  }

  if (blueprint && isProjectType(blueprint.projectType)) {
    return blueprint.projectType;
  }

  switch (blueprint?.projectType) {
    case 'saas':
      return 'saas';
    case 'ecommerce':
      return 'ecommerce';
    case 'portfolio':
      return 'portfolio';
    case 'restaurant':
      return 'restaurant';
    case 'business':
      return 'business';
    case 'landing':
    default:
      return 'landing';
  }
}

function hasStructure(structure: SiteStructureSection[] | undefined): structure is SiteStructureSection[] {
  return Array.isArray(structure) && structure.length > 0;
}

function blueprintToPrompt(blueprint: ProjectBlueprint): string {
  if (typeof blueprint.sourcePrompt === 'string' && blueprint.sourcePrompt.trim() !== '') {
    return blueprint.sourcePrompt
  }

  return [
    blueprint.tone,
    blueprint.projectType,
    blueprint.businessType,
    `for ${blueprint.audience}`,
    blueprint.pageGoal,
  ].filter(Boolean).join(' ');
}

function buildTrace(
  params: GenerateSiteParams,
  projectType: ProjectType,
  resolvedMode: GenerateSiteTrace['resolvedMode'],
): GenerateSiteTrace {
  return {
    requestedMode: params.generationMode ?? null,
    resolvedMode,
    projectType,
    hasBlueprint: Boolean(params.blueprint),
    structureCount: Array.isArray(params.structure) ? params.structure.length : 0,
  };
}

function logGenerateSiteTrace(message: string, trace: GenerateSiteTrace, extra?: Record<string, unknown>): void {
  if (typeof console === 'undefined') {
    return;
  }

  const payload = {
    ...trace,
    ...extra,
  };

  if (trace.resolvedMode === 'error' && typeof console.error === 'function') {
    console.error(`[builder.generateSite] ${message}`, payload);
    return;
  }

  if (typeof console.info === 'function') {
    console.info(`[builder.generateSite] ${message}`, payload);
  }
}

function buildDirectStructureDiagnostics(input: {
  projectType: ProjectType
  registryIndex: AiComponentCatalogIndex
  structure?: SiteStructureSection[]
  generationLog?: BlueprintGenerationLogEntry[]
  rootCause?: string | null
}): BuildGenerationDiagnostics {
  const sections = Array.isArray(input.structure) ? input.structure : []
  const selectedSectionTypes = sections.map((section) => (
    input.registryIndex.byKey[resolveComponentRegistryKey(section.componentKey) ?? section.componentKey]?.sectionType
      ?? section.componentKey
  ))

  return buildGenerationDiagnostics({
    generationMode: 'direct-structure',
    selectedProjectType: input.projectType,
    selectedSectionTypes,
    selectedSections: selectedSectionTypes,
    selectedComponentKeys: sections.map((section) => section.componentKey),
    validationPassed: input.rootCause == null,
    emergencyFallbackUsed: false,
    fallbackUsed: false,
    rootCause: input.rootCause ?? null,
    failedStep: input.rootCause ? 'validation' : null,
    events: input.generationLog ?? [],
  })
}

function resolveDiagnosticsGenerationMode(params: GenerateSiteParams): BuildGenerationDiagnostics['generationMode'] {
  if (hasStructure(params.structure)) {
    return 'direct-structure';
  }

  if (params.generationMode === 'emergency-fallback') {
    return 'emergency-fallback';
  }

  return 'blueprint';
}

function captureBuilderStoreSnapshot(): BuilderStoreSnapshot {
  const state = useBuilderStore.getState();

  return {
    projectType: state.projectType,
    componentTree: clonePlainData(state.componentTree),
    selectedComponentId: state.selectedComponentId,
    hoveredComponentId: state.hoveredComponentId,
    currentBreakpoint: state.currentBreakpoint,
    builderMode: state.builderMode,
    selectedProps: state.selectedProps ? clonePlainData(state.selectedProps) : null,
  };
}

function restoreBuilderStoreSnapshot(snapshot: BuilderStoreSnapshot): void {
  useBuilderStore.setState(snapshot);
}

function normalizeTreeForSignature(tree: ReturnType<typeof useBuilderStore.getState>['componentTree']) {
  return tree.map((node): TreeSignatureNode => ({
    id: node.id,
    componentKey: node.componentKey,
    variant: node.variant ?? null,
    props: node.props ?? {},
    children: Array.isArray(node.children) ? normalizeTreeForSignature(node.children) : [],
    responsive: node.responsive ?? null,
    responsiveOverrides: node.responsiveOverrides ?? null,
  }));
}

function buildTreeSignature(
  projectType: ProjectType,
  tree: ReturnType<typeof useBuilderStore.getState>['componentTree'],
): string {
  return stableSerialize({
    projectType,
    tree: normalizeTreeForSignature(tree),
  });
}

function appendDiagnosticsEvent(
  diagnostics: BuildGenerationDiagnostics,
  entry: BlueprintGenerationLogEntry,
  overrides: Partial<Pick<BuildGenerationDiagnostics, 'failedStep' | 'rootCause'>> = {},
): BuildGenerationDiagnostics {
  return appendGenerationDiagnosticsEvent(diagnostics, entry, overrides) ?? diagnostics;
}

function applyGeneratedTreeToStore(input: {
  projectType: ProjectType;
  tree: ReturnType<typeof useBuilderStore.getState>['componentTree'];
  diagnostics: BuildGenerationDiagnostics;
  setProjectType: ReturnType<typeof useBuilderStore.getState>['setProjectType'];
  setComponentTree: ReturnType<typeof useBuilderStore.getState>['setComponentTree'];
}): {
  diagnostics: BuildGenerationDiagnostics;
  skippedApply: boolean;
} {
  const snapshot = captureBuilderStoreSnapshot();
  const currentSignature = buildTreeSignature(snapshot.projectType, snapshot.componentTree);
  const nextSignature = buildTreeSignature(input.projectType, input.tree);

  if (snapshot.projectType === input.projectType && currentSignature === nextSignature) {
    return {
      skippedApply: true,
      diagnostics: appendDiagnosticsEvent(
        input.diagnostics,
        createGenerationLogEntry(
          'tree',
          'tree apply skipped because structure is unchanged',
          { nodeCount: input.tree.length },
          'success',
        ),
      ),
    };
  }

  try {
    input.setProjectType(input.projectType);
    input.setComponentTree(input.tree);

    return {
      skippedApply: false,
      diagnostics: appendDiagnosticsEvent(
        input.diagnostics,
        createGenerationLogEntry(
          'tree',
          'tree applied to builder state',
          { nodeCount: input.tree.length },
          'success',
        ),
      ),
    };
  } catch (error) {
    restoreBuilderStoreSnapshot(snapshot);

    const message = error instanceof Error ? error.message : String(error);
    throw new GenerationTraceError(
      `Failed to apply generated site: ${message}`,
      appendDiagnosticsEvent(
        input.diagnostics,
        createGenerationLogEntry(
          'tree',
          'tree apply failed',
          { error: message },
          'failure',
        ),
        {
          failedStep: 'tree',
          rootCause: message,
        },
      ),
    );
  }
}

function failGenerateSite(
  params: GenerateSiteParams,
  projectType: ProjectType,
  error: string,
  registryIndex?: AiComponentCatalogIndex,
  diagnostics?: BuildGenerationDiagnostics,
): GenerateSiteResult {
  const effectiveRegistryIndex = registryIndex ?? getAllowedComponentCatalogIndex(projectType);
  const selectedSectionTypes = hasStructure(params.structure)
    ? params.structure.map((section) => (
      effectiveRegistryIndex.byKey[resolveComponentRegistryKey(section.componentKey) ?? section.componentKey]?.sectionType
        ?? section.componentKey
    ))
    : [];
  const resolvedDiagnostics = diagnostics ?? buildGenerationDiagnostics({
    generationMode: resolveDiagnosticsGenerationMode(params),
    selectedProjectType: projectType,
    selectedSectionTypes,
    selectedSections: selectedSectionTypes,
    selectedComponentKeys: hasStructure(params.structure)
      ? params.structure.map((section) => section.componentKey)
      : [],
    validationPassed: false,
    emergencyFallbackUsed: params.generationMode === 'emergency-fallback',
    fallbackUsed: params.generationMode === 'emergency-fallback',
    failedStep: null,
    rootCause: error,
  });
  const trace = buildTrace(params, projectType, 'error');
  logGenerateSiteTrace('failed', trace, { error });
  return {
    ok: false,
    projectType,
    nodeCount: 0,
    trace,
    error,
    diagnostics: resolvedDiagnostics,
  };
}

/**
 * Builds a component tree from explicit structure or a normalized blueprint and sets store state.
 * Silent fallback is intentionally disabled: callers must provide structure, blueprint, or opt into emergency fallback.
 */
export async function runGenerateSite(params: GenerateSiteParams): Promise<GenerateSiteResult> {
  const projectType = resolveProjectType(params.projectType, params.blueprint);
  const state = useBuilderStore.getState();
  const { setProjectType, setComponentTree } = state;

  try {
    if (hasStructure(params.structure)) {
      const registryIndex = getAllowedComponentCatalogIndex(projectType);
      const trace = buildTrace(params, projectType, 'direct-structure');
      const tree = buildTreeFromStructure({ projectType, structure: params.structure });
      const generationLog: BlueprintGenerationLogEntry[] = [
        createGenerationLogEntry('sections', 'sections selected from direct structure', params.structure, 'success'),
        createGenerationLogEntry('tree', 'tree built from direct structure', tree.map((node) => ({
          id: node.id,
          componentKey: node.componentKey,
        })), 'success'),
      ];
      const validation = validateGeneratedSite({
        projectType,
        tree,
        registryIndex,
        generationMode: 'direct-structure',
      });
      if (!validation.ok) {
        const error = formatGeneratedSiteValidationIssues(validation.issues);
        generationLog.push(createGenerationLogEntry('validation', 'validation failed', {
          issues: validation.issues,
        }, 'failure'));
        return failGenerateSite(
          params,
          projectType,
          error,
          registryIndex,
          buildDirectStructureDiagnostics({
            projectType,
            registryIndex,
            structure: params.structure,
            generationLog,
            rootCause: error,
          }),
        );
      }
      generationLog.push(createGenerationLogEntry('validation', 'validation passed', { issues: [] }, 'success'));
      const applied = applyGeneratedTreeToStore({
        projectType,
        tree,
        diagnostics: buildDirectStructureDiagnostics({
          projectType,
          registryIndex,
          structure: params.structure,
          generationLog,
        }),
        setProjectType,
        setComponentTree,
      });
      logGenerateSiteTrace('applied', trace, {
        nodeCount: tree.length,
        skippedApply: applied.skippedApply,
      });
      return {
        ok: true,
        projectType,
        nodeCount: tree.length,
        generationMode: 'direct-structure',
        trace,
        diagnostics: applied.diagnostics,
      };
    }

    if (params.blueprint) {
      const blueprintResult = await buildSiteFromBlueprint({
        prompt: blueprintToPrompt(params.blueprint),
        blueprint: params.blueprint,
        builderProjectTypeOverride: isProjectType(params.projectType) ? params.projectType : null,
        generationMode: params.generationMode ?? 'blueprint',
      });

      if (blueprintResult.usedEmergencyFallback && params.generationMode !== 'emergency-fallback') {
        const error = 'Generation failed: blueprint could not resolve to a site structure without explicit emergency fallback.';
        return failGenerateSite(
          params,
          blueprintResult.projectType,
          error,
          undefined,
          {
            ...blueprintResult.diagnostics,
            failedStep: 'fallback',
            rootCause: error,
          },
        );
      }

      const resolvedGenerationMode = blueprintResult.usedEmergencyFallback
        ? 'emergency-fallback'
        : 'blueprint';
      const trace = buildTrace(params, blueprintResult.projectType, resolvedGenerationMode);
      const applied = applyGeneratedTreeToStore({
        projectType: blueprintResult.projectType,
        tree: blueprintResult.tree,
        diagnostics: blueprintResult.diagnostics,
        setProjectType,
        setComponentTree,
      });
      logGenerateSiteTrace('applied', trace, {
        nodeCount: blueprintResult.tree.length,
        blueprintProjectType: params.blueprint.projectType,
        skippedApply: applied.skippedApply,
      });
      return {
        ok: true,
        projectType: blueprintResult.projectType,
        nodeCount: blueprintResult.tree.length,
        generationMode: resolvedGenerationMode,
        trace,
        diagnostics: applied.diagnostics,
      };
    }

    if (params.generationMode === 'emergency-fallback') {
      const fallbackBlueprint = createEmergencyFallbackBlueprint(
        mapBuilderProjectTypeToBlueprintProjectType(projectType)
      );
      const fallbackResult = await buildSiteFromBlueprint({
        prompt: `emergency fallback ${projectType}`,
        blueprint: fallbackBlueprint,
        builderProjectTypeOverride: projectType,
        generationMode: 'emergency-fallback',
      });
      const trace = buildTrace(params, fallbackResult.projectType, 'emergency-fallback');
      const applied = applyGeneratedTreeToStore({
        projectType: fallbackResult.projectType,
        tree: fallbackResult.tree,
        diagnostics: fallbackResult.diagnostics,
        setProjectType,
        setComponentTree,
      });
      logGenerateSiteTrace('applied', trace, {
        nodeCount: fallbackResult.tree.length,
        skippedApply: applied.skippedApply,
      });
      return {
        ok: true,
        projectType: fallbackResult.projectType,
        nodeCount: fallbackResult.tree.length,
        generationMode: 'emergency-fallback',
        trace,
        diagnostics: applied.diagnostics,
      };
    }

    return failGenerateSite(
      params,
      projectType,
      'Generation failed: no site blueprint or direct structure was provided. Emergency fallback must be requested explicitly.',
      undefined,
    );
  } catch (err) {
    if (isGenerationTraceError(err)) {
      return failGenerateSite(
        params,
        projectType,
        err.message,
        undefined,
        err.diagnostics,
      );
    }
    const message = err instanceof Error ? err.message : String(err);
    return failGenerateSite(params, projectType, message, undefined);
  }
}
